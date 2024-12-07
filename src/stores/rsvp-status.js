/**
 * WordPress dependencies.
 */
import { createReduxStore, register } from '@wordpress/data';

const DEFAULT_STATE = {
	status: 'no_status',
};

const VALID_STATUSES = [
	'no_status',
	'attending',
	'waiting_list',
	'not_attending',
];

const actionTypes = {
	SET_STATUS: 'SET_STATUS',
};

const actions = {
	setStatus(status) {
		const validatedStatus = VALID_STATUSES.includes(status)
			? status
			: 'no_status';
		return {
			type: actionTypes.SET_STATUS,
			status: validatedStatus,
		};
	},
};

function reducer(state = DEFAULT_STATE, action) {
	switch (action.type) {
		case actionTypes.SET_STATUS:
			return {
				...state,
				status: action.status,
			};
		default:
			return state;
	}
}

const selectors = {
	getStatus(state) {
		return state.status;
	},
};

const store = createReduxStore('gatherpress/rsvp-status', {
	reducer,
	actions,
	selectors,
	persist: false,
});

register(store);
