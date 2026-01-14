/* global jQuery, gatherpressAI */

jQuery( document ).ready( function( $ ) {
	const $prompt = $( '#gp-ai-prompt' );
	const $submit = $( '#gp-ai-submit' );
	const $messages = $( '#gp-ai-messages' );
	const $status = $( '#gp-ai-status' );
	const $inputContainer = $( '.gp-ai-input-container' );

	// Create counter container.
	const $counterContainer = $( '<div>' ).addClass( 'gp-ai-counter-container' );

	// Create counter display element.
	const $counter = $( '<div>' )
		.addClass( 'gp-ai-counter' )
		.text( '0/10 prompts • 0/40,000 characters' );

	// Create reset button.
	const $resetButton = $( '<button>' )
		.addClass( 'button gp-ai-reset-button' )
		.text( 'Reset Conversation' )
		.on( 'click', handleReset );

	$counterContainer.append( $counter );
	$counterContainer.append( $resetButton );

	// Create warning notification element.
	const $warning = $( '<div>' )
		.addClass( 'gp-ai-warning' )
		.hide();

	// Insert counter container and warning before input container.
	$inputContainer.before( $counterContainer );
	$inputContainer.before( $warning );

	// Current state (will be updated from responses).
	let currentState = {
		prompt_count: 0,
		char_count: 0,
		max_prompts: 10,
		max_chars: 40000,
	};

	// Load initial state on page load.
	$.ajax( {
		url: gatherpressAI.ajaxUrl,
		type: 'POST',
		data: {
			action: 'gatherpress_ai_process_prompt',
			nonce: gatherpressAI.nonce,
			get_state: 'true',
		},
		success( response ) {
			if ( response.success && response.data.state ) {
				currentState = response.data.state;
				updateCounter();
			}
		},
	} );

	// Handle submit button click
	$submit.on( 'click', function( e ) {
		e.preventDefault();
		processPrompt();
	} );

	// Handle Enter key (Shift+Enter for new line)
	$prompt.on( 'keydown', function( e ) {
		if ( 'Enter' === e.key && ! e.shiftKey ) {
			e.preventDefault();
			processPrompt();
		}
	} );

	/**
	 * Process the user's prompt
	 */
	function processPrompt() {
		const prompt = $prompt.val().trim();

		// Debug: Log that processPrompt was called.
		console.error( 'GatherPress AI: processPrompt() called with prompt:', prompt );

		if ( ! prompt ) {
			console.error( 'GatherPress AI: processPrompt() - prompt is empty, returning' );
			return;
		}

		// Get file input element (Chunk 3+).
		const fileInput = document.getElementById( 'gp-ai-image-upload' );
		const hasFiles = fileInput && fileInput.files && fileInput.files.length > 0;

		// Capture files BEFORE clearing input (Chunk 3+).
		const selectedFiles = hasFiles ? Array.from( fileInput.files ) : [];

		// Add user message to chat
		let userMessageText = prompt;

		// Include file info in user message if files are selected (Chunk 3).
		if ( hasFiles ) {
			const fileNames = selectedFiles.map( ( file ) => file.name ).join( ', ' );
			userMessageText += ` [${ selectedFiles.length } image(s): ${ fileNames }]`;
		}

		addMessage( userMessageText, 'user' );

		// Clear input and file input
		$prompt.val( '' );
		if ( fileInput ) {
			fileInput.value = '';
		}

		// Disable submit button
		$submit.prop( 'disabled', true );
		$status.show();

		// Prepare request data.
		let requestData;
		let contentType;
		let processData;

		if ( hasFiles && selectedFiles.length > 0 ) {
			// Use FormData for file uploads (Chunk 3+).
			const formData = new FormData();
			formData.append( 'action', 'gatherpress_ai_process_prompt' );
			formData.append( 'nonce', gatherpressAI.nonce );
			formData.append( 'prompt', prompt );

			// Add image files using captured files (Chunk 3+).
			for ( let i = 0; i < selectedFiles.length; i++ ) {
				formData.append( 'images', selectedFiles[ i ] );
			}

			requestData = formData;
			contentType = false;
			processData = false;
		} else {
			// Use regular POST data for text-only prompts (jQuery defaults).
			requestData = {
				action: 'gatherpress_ai_process_prompt',
				nonce: gatherpressAI.nonce,
				prompt: prompt,
			};
			// Don't set contentType/processData - let jQuery use defaults.
		}

		// Build AJAX options.
		const ajaxOptions = {
			url: gatherpressAI.ajaxUrl,
			type: 'POST',
			data: requestData,
			success( response ) {
				if ( response.success ) {
					const data = response.data;

					// Update state if provided.
					if ( data.state ) {
						currentState = data.state;
						updateCounter();
					}

					// If conversation was auto-reset, add notification message first.
					if ( data.was_reset ) {
						addMessage(
							'Conversation reset: You reached the limit (' +
							`${ currentState.max_prompts } prompts or ${ currentState.max_chars.toLocaleString() } characters). ` +
							'Your conversation history has been cleared and the conversation has started fresh.',
							'success'
						);
					}

					// Display attachment IDs if present (Chunk 3).
					if ( data.attachment_ids && Array.isArray( data.attachment_ids ) && data.attachment_ids.length > 0 ) {
						const attachmentInfo = `✅ Uploaded ${ data.attachment_ids.length } image(s): Attachment IDs ${ data.attachment_ids.join( ', ' ) }`;
						addMessage( attachmentInfo, 'success' );
					}

					// Debug: Log image URLs to console for debugging.
					if ( data.debug_image_urls ) {
						console.log( 'GatherPress AI Debug: Image URLs being sent to AI:', data.debug_image_urls );
						data.debug_image_urls.forEach( function( debugInfo ) {
							console.log( 'GatherPress AI Debug: Attachment ID', debugInfo.attachment_id, 'URL:', debugInfo.url );
						} );
					}

					// Add AI response
					addMessage( data.response, 'assistant', data.actions, data.model_info, data.token_usage );
				} else {
					addMessage( 'Error: ' + ( response.data.message || 'Unknown error' ), 'error' );
				}
			},
			error( xhr, status, error ) {
				addMessage( 'Error: ' + error, 'error' );
			},
			complete() {
				$submit.prop( 'disabled', false );
				$status.hide();
			},
		};

		// Only set contentType/processData for file uploads.
		if ( typeof contentType !== 'undefined' ) {
			ajaxOptions.contentType = contentType;
		}
		if ( typeof processData !== 'undefined' ) {
			ajaxOptions.processData = processData;
		}

		// Send to backend
		$.ajax( ajaxOptions );
	}

	/**
	 * Update the counter display with current state and show/hide warnings.
	 */
	function updateCounter() {
		const promptText = `${ currentState.prompt_count }/${ currentState.max_prompts } prompts`;
		const charText = `${ currentState.char_count.toLocaleString() }/${ currentState.max_chars.toLocaleString() } characters`;
		$counter.text( `${ promptText } • ${ charText }` );

		// Show warning if approaching limits (8/10 prompts or 80% characters).
		const promptPercent = ( currentState.prompt_count / currentState.max_prompts ) * 100;
		const charPercent = ( currentState.char_count / currentState.max_chars ) * 100;
		const isApproachingLimit = 80 <= promptPercent || 80 <= charPercent;

		if ( isApproachingLimit && 0 < currentState.prompt_count ) {
			$counter.addClass( 'is-warning' );
			$warning.html(
				'<strong>Warning:</strong> You are approaching the conversation limit ' +
				`(${ currentState.max_prompts } prompts or ${ currentState.max_chars.toLocaleString() } characters). ` +
				'Consider resetting to start fresh.'
			);
			$warning.show();
		} else {
			$counter.removeClass( 'is-warning' );
			$warning.hide();
		}
	}

	/**
	 * Handle reset button click.
	 *
	 * @param {Event} e The click event.
	 */
	function handleReset( e ) {
		e.preventDefault();

		// Disable button during reset.
		$resetButton.prop( 'disabled', true );

		// Send reset request.
		$.ajax( {
			url: gatherpressAI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gatherpress_ai_process_prompt',
				nonce: gatherpressAI.nonce,
				reset: 'true',
			},
			success( response ) {
				if ( response.success && response.data.state ) {
					// Update state.
					currentState = response.data.state;
					updateCounter();

					// Clear all messages.
					$messages.empty();

					// Show reset notification message.
					addMessage(
						'Conversation reset. Your conversation history has been cleared and the counter has been reset.',
						'success'
					);

					// Show initial message again.
					addMessage(
						'Hi! I\'m your AI assistant for managing GatherPress events. What would you like me to help you with?',
						'assistant'
					);
				}
			},
			error() {
				addMessage( 'Error: Failed to reset conversation', 'error' );
			},
			complete() {
				$resetButton.prop( 'disabled', false );
			},
		} );
	}

	/**
	 * Add a message to the chat
	 *
	 * @param {string} content    The message content
	 * @param {string} type       The message type (user, assistant, error, success)
	 * @param {Array}  actions    Optional array of actions taken
	 * @param {Object} modelInfo  Optional object with provider and model info
	 * @param {Object} tokenUsage Optional object with token usage stats
	 */
	function addMessage( content, type, actions, modelInfo, tokenUsage ) {
		const $message = $( '<div>' )
			.addClass( 'gp-ai-message' )
			.addClass( type );

		// Add model/provider info at the top if available (for assistant messages).
		if ( modelInfo && 'assistant' === type ) {
			const $modelInfo = $( '<div>' )
				.addClass( 'gp-ai-model-info' )
				.css( {
					'margin-bottom': '10px',
					'padding-bottom': '10px',
					'border-bottom': '1px solid rgba(0, 0, 0, 0.1)',
					'font-size': '13px',
					'font-weight': 'bold',
					color: '#646970',
				} )
				.text( `Using ${ modelInfo.provider } ${ modelInfo.model }` );
			$message.append( $modelInfo );

			// Add token usage debug info if available.
			if ( tokenUsage ) {
				const costText = tokenUsage.estimated_cost
					? ` (~$${ tokenUsage.estimated_cost.toFixed( 4 ) })`
					: '';
				const $tokenInfo = $( '<div>' )
					.addClass( 'gp-ai-token-info' )
					.css( {
						'margin-bottom': '10px',
						'padding-bottom': '10px',
						'border-bottom': '1px solid rgba(0, 0, 0, 0.1)',
						'font-size': '12px',
						color: '#646970',
					} )
					.text(
						`Tokens: ${ tokenUsage.prompt_tokens.toLocaleString() } prompt, ` +
						`${ tokenUsage.completion_tokens.toLocaleString() } completion, ` +
						`${ tokenUsage.total_tokens.toLocaleString() } total${ costText }`
					);
				$message.append( $tokenInfo );
			}
		}

		const $content = $( '<div>' )
			.addClass( 'gp-ai-message-content' )
			.text( content );

		$message.append( $content );

		// Add actions if provided
		if ( actions && 0 < actions.length ) {
			const $actionsContainer = $( '<div>' ).addClass( 'gp-ai-actions' );
			$actionsContainer.append( '<p><strong>Actions taken:</strong></p>' );

			actions.forEach( function( action ) {
				const $action = $( '<div>' ).addClass( 'gp-ai-action' );

				let actionText = '';
				const result = action.result;

				switch ( action.ability ) {
					case 'gatherpress/create-event':
						actionText = `Created event: "${ action.args.title }"`;
						if ( result.edit_url ) {
							actionText += ` <a href="${ result.edit_url }" class="gp-ai-action-link" target="_blank">[Edit]</a>`;
						}
						break;
					case 'gatherpress/create-venue':
						actionText = `Created venue: "${ action.args.name }"`;
						if ( result.edit_url ) {
							actionText += ` <a href="${ result.edit_url }" class="gp-ai-action-link" target="_blank">[Edit]</a>`;
						}
						break;
					case 'gatherpress/update-event':
						actionText = `Updated event (ID: ${ action.args.event_id })`;
						if ( result.edit_url ) {
							actionText += ` <a href="${ result.edit_url }" class="gp-ai-action-link" target="_blank">[Edit]</a>`;
						}
						break;
					case 'gatherpress/update-venue':
						actionText = `Updated venue (ID: ${ action.args.venue_id })`;
						if ( result.edit_url ) {
							actionText += ` <a href="${ result.edit_url }" class="gp-ai-action-link" target="_blank">[Edit]</a>`;
						}
						break;
					case 'gatherpress/list-venues':
						actionText = `Listed ${ result.data.length } venue(s)`;
						break;
					case 'gatherpress/list-events':
						const eventCount = result.data.events ? result.data.events.length : result.data.count || 0;
						actionText = `Listed ${ eventCount } event(s)`;
						break;
					default:
						actionText = `Executed: ${ action.ability }`;
				}

				$action.html( '• ' + actionText );
				$actionsContainer.append( $action );
			} );

			$message.append( $actionsContainer );
		}

		$messages.append( $message );

		// Scroll to bottom
		$messages.scrollTop( $messages[ 0 ].scrollHeight );
	}

	// Show initial message
	if ( 0 === $messages.children().length ) {
		addMessage( 'Hi! I\'m your AI assistant for managing GatherPress events. What would you like me to help you with?', 'assistant' );
	}
} );
