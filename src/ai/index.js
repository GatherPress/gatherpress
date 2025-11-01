jQuery(document).ready(function($) {
	const $prompt = $('#gp-ai-prompt');
	const $submit = $('#gp-ai-submit');
	const $messages = $('#gp-ai-messages');
	const $status = $('#gp-ai-status');

	// Handle submit button click
	$submit.on('click', function(e) {
		e.preventDefault();
		processPrompt();
	});

	// Handle Enter key (Shift+Enter for new line)
	$prompt.on('keydown', function(e) {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			processPrompt();
		}
	});

	/**
	 * Process the user's prompt
	 */
	function processPrompt() {
		const prompt = $prompt.val().trim();
		
		if (!prompt) {
			return;
		}

		// Add user message to chat
		addMessage(prompt, 'user');
		
		// Clear input
		$prompt.val('');
		
		// Disable submit button
		$submit.prop('disabled', true);
		$status.show();

		// Send to backend
		$.ajax({
			url: gatherpressAI.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gatherpress_ai_process_prompt',
				nonce: gatherpressAI.nonce,
				prompt: prompt
			},
			success: function(response) {
				if (response.success) {
					const data = response.data;
					
					// Add AI response
					addMessage(data.response, 'assistant', data.actions);
				} else {
					addMessage('Error: ' + (response.data.message || 'Unknown error'), 'error');
				}
			},
			error: function(xhr, status, error) {
				addMessage('Error: ' + error, 'error');
			},
			complete: function() {
				$submit.prop('disabled', false);
				$status.hide();
			}
		});
	}

	/**
	 * Add a message to the chat
	 */
	function addMessage(content, type, actions) {
		const $message = $('<div>')
			.addClass('gp-ai-message')
			.addClass(type);
		
		const $content = $('<div>')
			.addClass('gp-ai-message-content')
			.text(content);
		
		$message.append($content);

		// Add actions if provided
		if (actions && actions.length > 0) {
			const $actionsContainer = $('<div>').addClass('gp-ai-actions');
			$actionsContainer.append('<p><strong>Actions taken:</strong></p>');
			
			actions.forEach(function(action) {
				const $action = $('<div>').addClass('gp-ai-action');
				
				let actionText = '';
				const result = action.result;
				
				switch(action.ability) {
					case 'gatherpress/create-event':
						actionText = `Created event: "${action.args.title}"`;
						if (result.edit_url) {
							actionText += ` <a href="${result.edit_url}" class="gp-ai-action-link" target="_blank">[Edit]</a>`;
						}
						break;
					case 'gatherpress/create-venue':
						actionText = `Created venue: "${action.args.name}"`;
						if (result.edit_url) {
							actionText += ` <a href="${result.edit_url}" class="gp-ai-action-link" target="_blank">[Edit]</a>`;
						}
						break;
					case 'gatherpress/update-event':
						actionText = `Updated event (ID: ${action.args.event_id})`;
						if (result.edit_url) {
							actionText += ` <a href="${result.edit_url}" class="gp-ai-action-link" target="_blank">[Edit]</a>`;
						}
						break;
					case 'gatherpress/update-venue':
						actionText = `Updated venue (ID: ${action.args.venue_id})`;
						if (result.edit_url) {
							actionText += ` <a href="${result.edit_url}" class="gp-ai-action-link" target="_blank">[Edit]</a>`;
						}
						break;
					case 'gatherpress/list-venues':
						actionText = `Listed ${result.data.length} venue(s)`;
						break;
					case 'gatherpress/list-events':
						const eventCount = result.data.events ? result.data.events.length : result.data.count || 0;
						actionText = `Listed ${eventCount} event(s)`;
						break;
					default:
						actionText = `Executed: ${action.ability}`;
				}
				
				$action.html('• ' + actionText);
				$actionsContainer.append($action);
			});
			
			$message.append($actionsContainer);
		}

		$messages.append($message);
		
		// Scroll to bottom
		$messages.scrollTop($messages[0].scrollHeight);
	}

	// Show initial message
	if ($messages.children().length === 0) {
		addMessage('Hi! I\'m your AI assistant for managing GatherPress events. What would you like me to help you with?', 'assistant');
	}
});

