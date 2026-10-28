/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Edit from '@src/blocks/event-doors/edit';
import { usePostTypeSupports } from '@src/helpers/event';
import { isInFSETemplate } from '@src/helpers/editor';
import { resolveDoorsTimes } from '@src/blocks/event-doors/helpers';
import { useBlockProps } from '@wordpress/block-editor';

let mockTimes = { doorsOpen: '', doorsClose: '' };

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn( ( selector ) =>
		selector( () => ( { getCurrentPostId: () => 12 } ) ),
	),
} ) );

jest.mock( '@wordpress/block-editor', () => ( {
	InspectorControls: ( { children } ) => <div>{ children }</div>,
	useBlockProps: jest.fn( ( props ) => props ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	PanelBody: ( { children } ) => <div>{ children }</div>,
} ) );

jest.mock( '@src/components/DoorsTime', () => () => <div>doors fields</div> );

jest.mock( '@src/helpers/editor', () => ( {
	isInFSETemplate: jest.fn( () => false ),
} ) );

jest.mock( '@src/helpers/event', () => ( {
	DISABLED_FIELD_OPACITY: 0.3,
	usePostTypeSupports: jest.fn( () => false ),
} ) );

jest.mock( '@src/blocks/event-doors/helpers', () => ( {
	resolveDoorsTimes: jest.fn( () => mockTimes ),
	formatDoorsTime: jest.fn( ( time ) => `formatted(${ time })` ),
} ) );

describe( 'Event Doors edit', () => {
	beforeEach( () => {
		mockTimes = { doorsOpen: '', doorsClose: '' };
		useBlockProps.mockClear();
		resolveDoorsTimes.mockClear();
	} );

	it( 'shows the set times and a placeholder for an unset one', () => {
		mockTimes = { doorsOpen: '18:30', doorsClose: '' };

		render( <Edit attributes={ {} } context={ { postId: 5, postType: 'gatherpress_event' } } /> );

		expect( screen.getByText( /Doors open formatted\(18:30\)/ ) ).toBeInTheDocument();
		expect( screen.getByText( /Doors close —/ ) ).toBeInTheDocument();
		expect( useBlockProps ).toHaveBeenCalledWith( { style: { opacity: 1 } } );
		expect( resolveDoorsTimes ).toHaveBeenCalledWith( expect.any( Function ), 'gatherpress_event', 5, false );
	} );

	it( 'dims the block when no time is set outside a template', () => {
		render( <Edit attributes={ {} } context={ {} } /> );

		expect( useBlockProps ).toHaveBeenCalledWith( { style: { opacity: 0.3 } } );
		expect( screen.queryByText( 'doors fields' ) ).not.toBeInTheDocument();
		expect( resolveDoorsTimes ).toHaveBeenCalledWith( expect.any( Function ), undefined, null, false );
	} );

	it( 'keeps full opacity when only the close time is set', () => {
		mockTimes = { doorsOpen: '', doorsClose: '23:00' };

		render( <Edit attributes={ {} } context={ {} } /> );

		expect( useBlockProps ).toHaveBeenCalledWith( { style: { opacity: 1 } } );
		expect( screen.getByText( /Doors open —/ ) ).toBeInTheDocument();
	} );

	it( 'keeps full opacity in a site template', () => {
		isInFSETemplate.mockReturnValueOnce( true );

		render( <Edit attributes={ {} } context={ {} } /> );

		expect( useBlockProps ).toHaveBeenCalledWith( { style: { opacity: 1 } } );
	} );

	it( 'hides the fields when the block shows another event', () => {
		usePostTypeSupports.mockReturnValueOnce( true );

		render( <Edit attributes={ {} } context={ { postId: 5 } } /> );

		expect( screen.queryByText( 'doors fields' ) ).not.toBeInTheDocument();
	} );

	it( 'hides the fields without a post ID', () => {
		usePostTypeSupports.mockReturnValueOnce( true );

		render( <Edit attributes={ {} } context={ {} } /> );

		expect( screen.queryByText( 'doors fields' ) ).not.toBeInTheDocument();
	} );

	it( 'shows the fields when the block shows the edited event', () => {
		usePostTypeSupports.mockReturnValueOnce( true );

		render( <Edit attributes={ { postId: 12 } } context={ { postId: 5 } } /> );

		expect( usePostTypeSupports ).toHaveBeenCalledWith( 'gatherpress-event-date' );
		expect( resolveDoorsTimes ).toHaveBeenCalledWith( expect.any( Function ), undefined, 12, true );
		expect( screen.getByText( 'doors fields' ) ).toBeInTheDocument();
	} );
} );
