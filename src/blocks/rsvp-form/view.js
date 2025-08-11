/**
 * WordPress dependencies.
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { initPostContext, getNonce } from '../../helpers/interactivity';
import { getFromGlobal } from '../../helpers/globals';

const { state } = store('gatherpress', {
	state: {
		posts: {},
		rsvpForm: {
			isSubmitting: false,
		},
	},
	actions: {
		async handleRsvpFormSubmit(event) {
			event.preventDefault();

			const element = getElement();
			const form = element.ref;
			const context = getContext();
			const postId = context?.postId || 0;

			// Prevent multiple submissions.
			if (state.rsvpForm.isSubmitting) {
				return;
			}

			state.rsvpForm.isSubmitting = true;

			// Get form data.
			const formData = new FormData(form);
			const data = {
				comment_post_ID: postId,
				author: formData.get('author'),
				email: formData.get('email'),
				gatherpress_event_email_updates:
					formData.get('gatherpress_event_email_updates') === 'on'
						? true
						: false,
			};

			const makeRequest = async (isRetry = false) => {
				const nonce = await getNonce();
				if (!nonce) {
					// If we can't get a nonce, fall back to regular form submission.
					state.rsvpForm.isSubmitting = false;
					form.submit();
					return;
				}

				const response = await fetch(
					getFromGlobal('urls.eventApiUrl') + '/rsvp-form',
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': nonce,
						},
						body: JSON.stringify(data),
					}
				);

				// Check if nonce failed (403 Forbidden).
				if (403 === response.status && !isRetry) {
					// Clear cached nonce and retry once.
					getNonce.clearCache();
					return makeRequest(true);
				}

				return response.json();
			};

			try {
				const result = await makeRequest();

				if (result && result.success) {
					// Success - show message block and disable form.
					const messageContainer = form.querySelector(
						'.gatherpress-rsvp-form-message'
					);
					if (messageContainer) {
						messageContainer.style.display = 'block';
					}

					// Disable all form inputs.
					const inputs = form.querySelectorAll(
						'input, textarea, button, select'
					);
					inputs.forEach((input) => {
						input.disabled = true;
					});

					// Update the responses data if available.
					if (result.responses) {
						initPostContext(state, postId);
						if (state.posts[postId]) {
							state.posts[postId].eventResponses = {
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
							'Sorry, there was an issue processing your RSVP. Please try again.'
					);
				}
			} catch (error) {
				// Network or other errors - fall back to regular form submission.
				// eslint-disable-next-line no-console
				console.warn(
					'Ajax RSVP submission failed, falling back to regular form submission:',
					error
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
			initPostContext(state, postId);

			// Reset submission state.
			state.rsvpForm.isSubmitting = false;
		},
	},
});
