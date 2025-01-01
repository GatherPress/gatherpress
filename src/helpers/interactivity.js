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
