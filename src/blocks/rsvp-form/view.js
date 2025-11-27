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

			// Find submit button for loading state.
			const submitButton = form.querySelector( '.gatherpress-submit-button' );
			const loadingClass = 'gatherpress--is-loading';

			state.rsvpForm.isSubmitting = true;

			// Add loading class to submit button.
			if ( submitButton ) {
				submitButton.classList.add( loadingClass );
			}

			// Get form data.
			const formData = new FormData( form );
			const data = {
				comment_post_ID: postId,
				author: formData.get( 'author' ),
				email: formData.get( 'email' ),
				gatherpress_rsvp_guests: formData.get( 'gatherpress_rsvp_guests' ) || '0',
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
					// Handle blocks with form visibility attributes.
					const blocksWithVisibility = form.querySelectorAll( '[data-gatherpress-rsvp-form-visibility]' );
					const isPast = 'past' === form.getAttribute( 'data-gatherpress-event-state' );

					blocksWithVisibility.forEach( ( block ) => {
						const visibilityAttr = block.getAttribute( 'data-gatherpress-rsvp-form-visibility' );
						const visibility = JSON.parse( visibilityAttr );
						const { onSuccess, whenPast } = visibility;

						let shouldShow = null; // null = default (no change)

						// whenPast takes precedence.
						if ( isPast && whenPast ) {
							shouldShow = 'show' === whenPast;
						} else if ( onSuccess ) {
							// After successful submission.
							shouldShow = 'show' === onSuccess;
						}

						// Apply visibility changes.
						if ( true === shouldShow ) {
							block.style.removeProperty( 'display' );
							block.setAttribute( 'aria-hidden', 'false' );
							block.setAttribute( 'aria-live', 'polite' );
							block.setAttribute( 'role', 'status' );
						} else if ( false === shouldShow ) {
							block.style.display = 'none';
							block.setAttribute( 'aria-hidden', 'true' );
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

			// Remove loading class from submit button.
			if ( submitButton ) {
				submitButton.classList.remove( loadingClass );
			}
		},
	},
	callbacks: {
		initRsvpForm() {
			const element = getElement();
			const form = element.ref;
			const context = getContext();
			const postId = context?.postId || 0;

			// Initialize post context.
			initPostContext( state, postId );

			// Reset submission state.
			state.rsvpForm.isSubmitting = false;

			// Check if this is a success page (form was just submitted).
			const urlParams = new URLSearchParams( window.location.search );
			const isSuccess = 'true' === urlParams.get( 'gatherpress_rsvp_success' );
			const isPast = 'past' === form.getAttribute( 'data-gatherpress-event-state' );

			// Set initial visibility for blocks based on their attributes and current state.
			const blocksWithVisibility = form.querySelectorAll( '[data-gatherpress-rsvp-form-visibility]' );
			blocksWithVisibility.forEach( ( block ) => {
				const visibilityAttr = block.getAttribute( 'data-gatherpress-rsvp-form-visibility' );
				const visibility = JSON.parse( visibilityAttr );
				const { onSuccess, whenPast } = visibility;

				let shouldShow = null; // null = default (always visible)

				// When event is past, check whenPast (takes precedence when past).
				if ( isPast && whenPast ) {
					shouldShow = 'show' === whenPast;
				} else if ( ! isPast && whenPast && ! onSuccess ) {
					// When not past but block has ONLY whenPast setting (no onSuccess).
					shouldShow = 'show' !== whenPast;
				} else if ( onSuccess ) {
					// Check onSuccess.
					if ( isSuccess ) {
						shouldShow = 'show' === onSuccess;
					} else {
						// Not success: hide if set to show on success.
						shouldShow = 'show' !== onSuccess;
					}
				}

				// Apply visibility changes.
				if ( true === shouldShow ) {
					block.style.removeProperty( 'display' );
					block.setAttribute( 'aria-hidden', 'false' );
					if ( isSuccess ) {
						block.setAttribute( 'aria-live', 'polite' );
						block.setAttribute( 'role', 'status' );
					}
				} else if ( false === shouldShow ) {
					block.style.display = 'none';
					block.setAttribute( 'aria-hidden', 'true' );
				}
			} );
		},
	},
} );
