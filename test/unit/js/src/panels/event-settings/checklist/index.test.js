/**
 * WordPress dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';
import { fireEvent, render, screen } from '@testing-library/react';

jest.mock( 'uuid', () => ( {
	v4: jest.fn( () => 'generated-id' ),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
	sprintf: ( format, ...args ) => {
		let sequential = 0;

		// Handles both positional (`%1$d`) and plain (`%s`) placeholders, which
		// is all the panel uses.
		return format.replace( /%(?:(\d+)\$)?[ds]/g, ( match, position ) =>
			undefined === position
				? args[ sequential++ ]
				: args[ Number( position ) - 1 ]
		);
	},
} ) );

jest.mock( '@wordpress/icons', () => ( {
	chevronDown: 'chevronDown',
	chevronUp: 'chevronUp',
	plus: 'plus',
	trash: 'trash',
} ) );

jest.mock( '@wordpress/components', () => ( {
	Button: ( { label, onClick, disabled, children } ) => (
		<button
			aria-label={ label }
			onClick={ onClick }
			disabled={ disabled }
		>
			{ children }
		</button>
	),
	CheckboxControl: ( { 'aria-label': ariaLabel, checked, onChange } ) => (
		<input
			type="checkbox"
			aria-label={ ariaLabel }
			checked={ checked }
			onChange={ ( event ) => onChange( event.target.checked ) }
		/>
	),
	Flex: ( { children } ) => <div>{ children }</div>,
	FlexBlock: ( { children } ) => <div>{ children }</div>,
	FlexItem: ( { children } ) => <div>{ children }</div>,
	TextControl: ( { label, value, onChange } ) => (
		<input
			type="text"
			aria-label={ label }
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
		/>
	),
} ) );

const mockEditPost = jest.fn();
const mockUnlockPostSaving = jest.fn();
let mockSupportsChecklist = true;
let mockStoredMeta;

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( {
		editPost: ( ...args ) => mockEditPost( ...args ),
		unlockPostSaving: ( ...args ) => mockUnlockPostSaving( ...args ),
	} ) ),
	useSelect: jest.fn( ( mapSelect ) =>
		mapSelect( () => ( {
			getEditedPostAttribute: () => mockStoredMeta,
		} ) )
	),
} ) );

jest.mock( '@src/helpers/event', () => ( {
	usePostTypeSupports: jest.fn( () => mockSupportsChecklist ),
} ) );

/**
 * Internal dependencies
 */
import ChecklistPanel from '@src/panels/event-settings/checklist';

/**
 * Render the panel with a given stored checklist value.
 *
 * @param {string|undefined} stored Raw `gatherpress_checklist` meta value.
 *
 * @return {Object} The testing-library render result.
 */
const renderPanel = ( stored ) => {
	mockStoredMeta = undefined === stored ? {} : { gatherpress_checklist: stored };

	return render( <ChecklistPanel /> );
};

describe( 'ChecklistPanel', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockSupportsChecklist = true;
		mockStoredMeta = {};
	} );

	it( 'renders nothing when the post type lacks checklist support', () => {
		mockSupportsChecklist = false;

		const { container } = renderPanel( '[]' );

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'shows the placeholder copy when the checklist is empty', () => {
		renderPanel( '[]' );

		expect(
			screen.getByText( 'Track the steps needed to run this event.' )
		).toBeInTheDocument();
	} );

	it( 'shows the placeholder copy when the meta has not loaded yet', () => {
		renderPanel( undefined );

		expect(
			screen.getByText( 'Track the steps needed to run this event.' )
		).toBeInTheDocument();
	} );

	it( 'shows the progress count when items are stored', () => {
		renderPanel(
			'[{"id":"a","text":"Ask","completed":true},' +
				'{"id":"b","text":"Pay","completed":false}]'
		);

		expect( screen.getByText( '1 of 2 complete' ) ).toBeInTheDocument();
	} );

	it( 'commits the serialized checklist when an item text changes', () => {
		renderPanel( '[{"id":"a","text":"Ask","completed":true}]' );

		fireEvent.change( screen.getByLabelText( 'Checklist item: Ask' ), {
			target: { value: 'Ask again' },
		} );

		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: {
				gatherpress_checklist:
					'[{"id":"a","text":"Ask again","completed":true}]',
			},
		} );
		expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'commits the serialized checklist when an item is ticked off', () => {
		renderPanel( '[{"id":"a","text":"Ask","completed":false}]' );

		fireEvent.click( screen.getByLabelText( 'Mark "Ask" complete' ) );

		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: {
				gatherpress_checklist:
					'[{"id":"a","text":"Ask","completed":true}]',
			},
		} );
		expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'appends a new item when Add item is clicked', () => {
		renderPanel( '[{"id":"a","text":"Ask","completed":false}]' );

		fireEvent.click( screen.getByText( 'Add item' ) );

		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: {
				gatherpress_checklist:
					'[{"id":"a","text":"Ask","completed":false},' +
					'{"id":"generated-id","text":"","completed":false}]',
			},
		} );
	} );

	it( 'removes the item when the remove button is clicked', () => {
		renderPanel(
			'[{"id":"a","text":"Ask","completed":false},' +
				'{"id":"b","text":"Pay","completed":false}]'
		);

		fireEvent.click( screen.getAllByLabelText( 'Remove item' )[ 0 ] );

		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: {
				gatherpress_checklist:
					'[{"id":"b","text":"Pay","completed":false}]',
			},
		} );
	} );

	it( 'moves an item up when the up button is clicked', () => {
		renderPanel(
			'[{"id":"a","text":"Ask","completed":false},' +
				'{"id":"b","text":"Pay","completed":false}]'
		);

		fireEvent.click( screen.getAllByLabelText( 'Move up' )[ 1 ] );

		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: {
				gatherpress_checklist:
					'[{"id":"b","text":"Pay","completed":false},' +
					'{"id":"a","text":"Ask","completed":false}]',
			},
		} );
	} );

	it( 'moves an item down when the down button is clicked', () => {
		renderPanel(
			'[{"id":"a","text":"Ask","completed":false},' +
				'{"id":"b","text":"Pay","completed":false}]'
		);

		fireEvent.click( screen.getAllByLabelText( 'Move down' )[ 0 ] );

		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: {
				gatherpress_checklist:
					'[{"id":"b","text":"Pay","completed":false},' +
					'{"id":"a","text":"Ask","completed":false}]',
			},
		} );
	} );

	it( 'disables the up button on the first item and the down button on the last', () => {
		renderPanel(
			'[{"id":"a","text":"Ask","completed":false},' +
				'{"id":"b","text":"Pay","completed":false},' +
				'{"id":"c","text":"Send","completed":false}]'
		);

		const upButtons = screen.getAllByLabelText( 'Move up' );
		const downButtons = screen.getAllByLabelText( 'Move down' );

		expect( upButtons[ 0 ] ).toBeDisabled();
		expect( upButtons[ 1 ] ).toBeEnabled();
		expect( upButtons[ 2 ] ).toBeEnabled();
		expect( downButtons[ 0 ] ).toBeEnabled();
		expect( downButtons[ 1 ] ).toBeEnabled();
		expect( downButtons[ 2 ] ).toBeDisabled();
	} );
} );
