/**
 * WordPress dependencies.
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { initPostContext, getNonce } from '../../helpers/interactivity';
import { getFromGlobal } from '../../helpers/globals';

const { state } = store( 'gatherpress', {
	state: {
		posts: {},
		rsvpForm: {
			isSubmitting: false,
		},
	},
	actions: {
		async handleRsvpFormSubmit( event ) {
			event.preventDefault();

			const element = getElement();
			const form = element.ref;
			const context = getContext();
			const postId = context?.postId || 0;

			// Prevent multiple submissions.
			if ( state.rsvpForm.isSubmitting ) {
				return;
			}

			state.rsvpForm.isSubmitting = true;

			// Get form data.
			const formData = new FormData( form );
			const data = {
				comment_post_ID: postId,
				author: formData.get( 'author' ),
				email: formData.get( 'email' ),
				gatherpress_rsvp_guests: formData.get( 'gatherpress_rsvp_guests' ) || 0,
			};

			// Handle checkbox fields - they only appear in FormData when checked.
			// When checked, they typically have value 'on' or a custom value.
			// When unchecked, formData.get() returns null.
			data.gatherpress_rsvp_anonymous = formData.get( 'gatherpress_rsvp_anonymous' ) ? '1' : '0';
			data.gatherpress_event_updates_opt_in = formData.get( 'gatherpress_event_updates_opt_in' ) ? '1' : '0';

			// Add any custom fields and schema ID from the form.
			for ( const [ key, value ] of formData.entries() ) {
				// Skip fields we've already explicitly handled above.
				const skipFields = [
					'comment_post_ID',
					'author',
					'email',
					'gatherpress_rsvp_guests',
					'gatherpress_rsvp_anonymous',
					'gatherpress_event_updates_opt_in',
				];

				if ( ! skipFields.includes( key ) ) {
					data[ key ] = value;
				}
			}

			const makeRequest = async ( isRetry = false ) => {
				const nonce = await getNonce();
				if ( ! nonce ) {
					// If we can't get a nonce, fall back to regular form submission.
					state.rsvpForm.isSubmitting = false;
					form.submit();
					return;
				}

				const response = await fetch(
					getFromGlobal( 'urls.eventApiUrl' ) + '/rsvp-form',
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': nonce,
						},
						body: JSON.stringify( data ),
					},
				);

				// Check if nonce failed (403 Forbidden).
				if ( 403 === response.status && ! isRetry ) {
					// Clear cached nonce and retry once.
					getNonce.clearCache();
					return makeRequest( true );
				}

				return response.json();
			};

			try {
				const result = await makeRequest();

				if ( result && result.success ) {
					// Success - show message block and hide form elements.
					const messageContainer = form.querySelector(
						'.gatherpress--rsvp-form-message',
					);
					if ( messageContainer ) {
						messageContainer.style.display = 'block';
						messageContainer.setAttribute( 'aria-hidden', 'false' );
						messageContainer.setAttribute( 'aria-live', 'polite' );
						messageContainer.setAttribute( 'role', 'status' );
					}

					// Hide all form field blocks.
					const formFieldBlocks = form.querySelectorAll(
						'.wp-block-gatherpress-form-field',
					);
					formFieldBlocks.forEach( ( block ) => {
						block.style.display = 'none';
					} );

					// Hide buttons within .wp-block-buttons, except those with gatherpress-modal--trigger-close class.
					// Look for all button containers first.
					const buttonContainers = form.querySelectorAll( '.wp-block-button' );
					buttonContainers.forEach( ( container ) => {
						// Check if the container or its button has the modal close class.
						const button = container.querySelector( 'button, .wp-block-button__link, input[type="submit"], input[type="button"], a' );
						if ( button ) {
							const hasCloseClass = container.classList.contains( 'gatherpress-modal--trigger-close' ) ||
								button.classList.contains( 'gatherpress-modal--trigger-close' );

							if ( ! hasCloseClass ) {
								container.style.display = 'none';
							}
						}
					} );

					// Update the responses data if available.
					if ( result.responses ) {
						initPostContext( state, postId );
						if ( state.posts[ postId ] ) {
							state.posts[ postId ].eventResponses = {
								attending:
									result.responses?.attending?.count || 0,
								waitingList:
									result.responses?.waiting_list?.count || 0,
								notAttending:
									result.responses?.not_attending?.count || 0,
							};
						}
					}
				} else {
					// Error from the server - show alert with server-provided message.
					// eslint-disable-next-line no-alert
					alert(
						result?.message ||
							'Sorry, there was an issue processing your RSVP. Please try again.',
					);
				}
			} catch ( error ) {
				// Network or other errors - fall back to regular form submission.
				// eslint-disable-next-line no-console
				console.warn(
					'Ajax RSVP submission failed, falling back to regular form submission:',
					error,
				);

				// Let the form submit normally as a fallback.
				state.rsvpForm.isSubmitting = false;
				form.submit();
				return;
			}

			state.rsvpForm.isSubmitting = false;
		},
	},
	callbacks: {
		initRsvpForm() {
			const context = getContext();
			const postId = context?.postId || 0;

			// Initialize post context.
			initPostContext( state, postId );

			// Reset submission state.
			state.rsvpForm.isSubmitting = false;
		},
	},
} );
