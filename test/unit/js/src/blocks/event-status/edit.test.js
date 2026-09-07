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

// The vocabulary PHP publishes through the editor settings, which the status
// helper reads. Stated here so these tests exercise the real lookup.
const mockStatuses = {
	scheduled: {
		label: 'Scheduled',
		description: 'Event is planned and confirmed to take place.',
	},
	canceled: { label: 'Canceled', description: 'Event will not take place.' },
	postponed: { label: 'Postponed', description: 'Event is delayed.' },
	rescheduled: {
		label: 'Rescheduled',
		description: 'Event date and time have been changed.',
	},
	moved: { label: 'Moved', description: 'Event is taking place elsewhere.' },
};

jest.mock( '@src/helpers/editor-settings', () => ( {
	getFromConfig: ( key ) =>
		'eventStatuses' === key ? mockStatuses : undefined,
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

	it( 'renders the status badge with canceled status', () => {
		useSelect.mockImplementation( ( callback ) =>
			callback( () => ( {
				getEditedPostAttribute: () => 'canceled',
			} ) )
		);

		render(
			<Edit
				attributes={ defaultAttributes }
				setAttributes={ mockSetAttributes }
				context={ {} }
			/>
		);

		expect( screen.getByText( 'Canceled' ) ).toBeInTheDocument();
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

	it( 'falls back to the event post type when context names none', () => {
		let requestedPostType;

		useSelect.mockImplementation( ( callback ) =>
			callback( ( store ) => {
				if ( 'core' === store ) {
					return {
						getEntityRecord: ( kind, postType ) => {
							requestedPostType = postType;

							return { gatherpress_status: 'rescheduled' };
						},
					};
				}

				return {};
			} )
		);

		render(
			<Edit
				attributes={ defaultAttributes }
				setAttributes={ mockSetAttributes }
				context={ { postId: 123 } }
			/>
		);

		expect( requestedPostType ).toBe( 'gatherpress_event' );
		expect( screen.getByText( 'Rescheduled' ) ).toBeInTheDocument();
	} );

	it( 'reads as scheduled when the record has not loaded', () => {
		useSelect.mockImplementation( ( callback ) =>
			callback( ( store ) => {
				if ( 'core' === store ) {
					return { getEntityRecord: () => undefined };
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

		expect( screen.getByText( 'Scheduled' ) ).toBeInTheDocument();
	} );

	it( 'reads as scheduled when the record carries no status', () => {
		useSelect.mockImplementation( ( callback ) =>
			callback( ( store ) => {
				if ( 'core' === store ) {
					return { getEntityRecord: () => ( { id: 123 } ) };
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

		expect( screen.getByText( 'Scheduled' ) ).toBeInTheDocument();
	} );

	it( 'labels an unknown status as scheduled', () => {
		useSelect.mockImplementation( ( callback ) =>
			callback( () => ( {
				getEditedPostAttribute: () => 'not-a-status',
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

	it( 'prefers an explicit post id over the surrounding event', () => {
		let requestedPostId;

		useSelect.mockImplementation( ( callback ) =>
			callback( ( store ) => {
				if ( 'core' === store ) {
					return {
						getEntityRecord: ( kind, postType, postId ) => {
							requestedPostId = postId;

							return { gatherpress_status: 'moved' };
						},
					};
				}

				return {};
			} )
		);

		render(
			<Edit
				attributes={ { ...defaultAttributes, postId: 456 } }
				setAttributes={ mockSetAttributes }
				context={ { postId: 123, postType: 'gatherpress_event' } }
			/>
		);

		expect( requestedPostId ).toBe( 456 );
		expect( screen.getByText( 'Moved' ) ).toBeInTheDocument();
	} );

	it( 'uses the surrounding event when the override is cleared', () => {
		let requestedPostId;

		useSelect.mockImplementation( ( callback ) =>
			callback( ( store ) => {
				if ( 'core' === store ) {
					return {
						getEntityRecord: ( kind, postType, postId ) => {
							requestedPostId = postId;

							return { gatherpress_status: 'postponed' };
						},
					};
				}

				return {};
			} )
		);

		render(
			<Edit
				attributes={ { ...defaultAttributes, postId: '' } }
				setAttributes={ mockSetAttributes }
				context={ { postId: 123, postType: 'gatherpress_event' } }
			/>
		);

		expect( requestedPostId ).toBe( 123 );
	} );
} );
