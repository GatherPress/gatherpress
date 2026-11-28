/**
 * WordPress dependencies
 */
import { createReduxStore, register } from '@wordpress/data';

/**
 * Initial state for the email modal store.
 */
const DEFAULT_STATE = {
	isOpen: false,
	isSaving: false,
	change: null,
};

/**
 * Actions for the email modal store.
 */
const actions = {
	/**
	 * Open the modal, optionally for the change the last save detected.
	 *
	 * @since 0.33.0
	 * @since TBD Accepts the change.
	 *
	 * @param {Object|null} change Optional. The `gatherpress_change` REST field of the saved event.
	 *
	 * @return {Object} The action.
	 */
	openModal( change = null ) {
		return {
			type: 'SET_MODAL_OPEN',
			isOpen: true,
			change: change?.keys ? change : null,
		};
	},

	closeModal() {
		return {
			type: 'SET_MODAL_OPEN',
			isOpen: false,
			change: null,
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

	/**
	 * The change the open modal is about.
	 *
	 * @since TBD
	 *
	 * @param {Object} state Store state.
	 *
	 * @return {Object|null} The change, or null for an ordinary event update.
	 */
	getChange( state ) {
		return state.change;
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
				change: action.change,
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
