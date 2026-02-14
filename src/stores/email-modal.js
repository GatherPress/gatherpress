/**
 * WordPress dependencies.
 */
import { createReduxStore, register } from '@wordpress/data';

/**
 * Initial state for the email modal store.
 */
const DEFAULT_STATE = {
	isOpen: false,
	isSaving: false,
};

/**
 * Actions for the email modal store.
 */
const actions = {
	setModalOpen( isOpen ) {
		return {
			type: 'SET_MODAL_OPEN',
			isOpen,
		};
	},

	openModal() {
		return {
			type: 'SET_MODAL_OPEN',
			isOpen: true,
		};
	},

	closeModal() {
		return {
			type: 'SET_MODAL_OPEN',
			isOpen: false,
		};
	},

	setSaving( isSaving ) {
		return {
			type: 'SET_SAVING',
			isSaving,
		};
	},
};

/**
 * Selectors for the email modal store.
 */
const selectors = {
	isModalOpen( state ) {
		return state.isOpen;
	},

	isSaving( state ) {
		return state.isSaving;
	},
};

/**
 * Reducer for the email modal store.
 * @param {Object} state
 * @param {Object} action
 */
const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_MODAL_OPEN':
			return {
				...state,
				isOpen: action.isOpen,
			};
		case 'SET_SAVING':
			return {
				...state,
				isSaving: action.isSaving,
			};
		default:
			return state;
	}
};

/**
 * Email modal store configuration.
 */
const store = createReduxStore( 'gatherpress/email-modal', {
	reducer,
	actions,
	selectors,
} );

register( store );
