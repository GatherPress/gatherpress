/**
 * Internal dependencies.
 */
import { getFromGlobal } from './globals';

export function initPostContext(state, postId) {
	const eventDetails = getFromGlobal('eventDetails');

	if (!state.posts[postId]) {
		state.posts[postId] = {
			eventResponses: {
				attending: eventDetails.responses.attending.count || 0,
				waitingList: eventDetails.responses.waiting_list.count || 0,
				notAttending: eventDetails.responses.not_attending.count || 0,
			},
			currentUser: {
				status: eventDetails.currentUser?.status || 'no_status',
				guests: eventDetails.currentUser?.guests || 0,
				anonymous: eventDetails.currentUser?.anonymous || 0,
			},
			rsvpSelection: 'attending',
		};
	}
}

export function sendRsvpApiRequest(
	postId,
	args,
	state = null,
	onSuccess = null
) {
	if (['no_status', 'waiting_list'].includes(args.status)) {
		return;
	}

	fetch(getFromGlobal('urls.eventApiUrl') + '/rsvp', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': getFromGlobal('misc.nonce'),
		},
		body: JSON.stringify({
			post_id: postId,
			status: args.status,
			guests: args.guests,
			anonymous: args.anonymous,
		}),
	})
		.then((response) => response.json()) // Parse the JSON response.
		.then((res) => {
			if (res.success) {
				if (state) {
					state.activePostId = postId;
					state.posts[postId] = {
						...state.posts[postId],
						eventResponses: {
							attending: res.responses.attending.count,
							waitingList: res.responses.waiting_list.count,
							notAttending: res.responses.not_attending.count,
						},
						currentUser: {
							status: res.status,
							guests: res.guests,
							anonymous: res.anonymous,
						},
						rsvpSelection: res.status,
					};
				}

				if ('function' === typeof onSuccess) {
					onSuccess(res);
				}
			}
		})
		.catch(() => {});
}

export function manageFocusTrap(focusableElements) {
	if (!focusableElements || focusableElements.length === 0) {
		return () => {}; // Return an empty cleanup function if no elements.
	}
	const isElementVisible = (element) => {
		return (
			element.offsetParent !== null && // Excludes elements with `display: none`.
			global.window.getComputedStyle(element).visibility !== 'hidden' && // Excludes elements with `visibility: hidden`.
			global.window.getComputedStyle(element).opacity !== '0' // Excludes fully transparent elements.
		);
	};

	// Filter out hidden elements.
	const visibleFocusableElements = focusableElements.filter(isElementVisible);

	if (visibleFocusableElements.length === 0) {
		return () => {}; // No visible elements, no trap needed.
	}

	const firstFocusableElement = visibleFocusableElements[0];
	const lastFocusableElement =
		visibleFocusableElements[visibleFocusableElements.length - 1];

	const handleFocusTrap = (e) => {
		if ('Tab' === e.key) {
			if (
				e.shiftKey && // Shift + Tab.
				global.document.activeElement === firstFocusableElement
			) {
				e.preventDefault();
				lastFocusableElement.focus();
			} else if (
				!e.shiftKey && // Tab.
				global.document.activeElement === lastFocusableElement
			) {
				e.preventDefault();
				firstFocusableElement.focus();
			}
		}
	};

	const handleEscapeKey = (e) => {
		if ('Escape' === e.key) {
			cleanup(); // Trigger cleanup on Escape key.
		}
	};

	const cleanup = () => {
		global.document.removeEventListener('keydown', handleFocusTrap);
		global.document.removeEventListener('keydown', handleEscapeKey);
	};

	// Attach the event listeners for focus trap.
	global.document.addEventListener('keydown', handleFocusTrap);
	global.document.addEventListener('keydown', handleEscapeKey);

	// Return a cleanup function for the caller.
	return cleanup;
}
