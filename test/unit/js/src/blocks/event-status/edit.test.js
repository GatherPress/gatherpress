/**
 * External dependencies
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import Edit from '@src/blocks/event-status/edit';

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
} ) );

jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: jest.fn( ( props ) => ( {
		...props,
		className: `wp-block-gatherpress-event-status ${ props?.className || '' }`,
	} ) ),
	InspectorControls: ( { children } ) => <div>{ children }</div>,
} ) );

jest.mock( '@wordpress/components', () => ( {
	PanelBody: ( { children } ) => <div>{ children }</div>,
	ToggleControl: ( { label, checked, onChange } ) => (
		<button
			aria-pressed={ checked }
			onClick={ () => onChange( ! checked ) }
		>
			{ label }
		</button>
	),
} ) );

describe( 'EventStatus block Edit component', () => {
	const defaultAttributes = { hideScheduled: true };
	const mockSetAttributes = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders the status badge with scheduled status', () => {
		useSelect.mockImplementation( ( callback ) =>
			callback( () => ( {
				getEditedPostAttribute: () => 'scheduled',
			} ) )
		);

		render(
			<Edit
				attributes={ defaultAttributes }
				setAttributes={ mockSetAttributes }
				context={ {} }
			/>
		);

		expect( screen.getByText( 'Scheduled' ) ).toBeInTheDocument();
	} );

	it( 'renders the status badge with cancelled status', () => {
		useSelect.mockImplementation( ( callback ) =>
			callback( () => ( {
				getEditedPostAttribute: () => 'cancelled',
			} ) )
		);

		render(
			<Edit
				attributes={ defaultAttributes }
				setAttributes={ mockSetAttributes }
				context={ {} }
			/>
		);

		expect( screen.getByText( 'Cancelled' ) ).toBeInTheDocument();
	} );

	it( 'renders the status badge with postponed status from context', () => {
		useSelect.mockImplementation( ( callback ) =>
			callback( ( store ) => {
				if ( 'core' === store ) {
					return {
						getEntityRecord: () => ( {
							gatherpress_status: 'postponed',
						} ),
					};
				}
				return {};
			} )
		);

		render(
			<Edit
				attributes={ defaultAttributes }
				setAttributes={ mockSetAttributes }
				context={ { postId: 123, postType: 'gatherpress_event' } }
			/>
		);

		expect( screen.getByText( 'Postponed' ) ).toBeInTheDocument();
	} );

	it( 'updates hideScheduled attribute when toggle is clicked', () => {
		useSelect.mockImplementation( ( callback ) =>
			callback( () => ( {
				getEditedPostAttribute: () => 'scheduled',
			} ) )
		);

		render(
			<Edit
				attributes={ defaultAttributes }
				setAttributes={ mockSetAttributes }
				context={ {} }
			/>
		);

		const toggle = screen.getByText( 'Hide when scheduled' );
		toggle.click();

		expect( mockSetAttributes ).toHaveBeenCalledWith( {
			hideScheduled: false,
		} );
	} );
} );
