/**
 * External dependencies
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';
import { act, render } from '@testing-library/react';

/**
 * Mocks
 */

// Verify that the useSelect selector returns stable object references for
// venueMeta, savedVenueMeta, and staticMapDescriptors across repeated calls
// with the same state. Unstable references trigger Gutenberg's "Non-equal
// value keys" warning and cause unnecessary re-renders (issue #1735).

// Capture the venue-state selector from the Edit block's first useSelect call
// so we can invoke it directly in tests.
let capturedVenueStateSelector = null;

// Capture the latest props handed to the mocked ResizableBox so tests can
// drive its resize callbacks with crafted elements.
let capturedResizableProps = null;

// A minimal select mock that puts the block into the early-bail path:
// effectiveVenuePostId === 0 because both context.postId and
// core/editor.getCurrentPostId() return falsy values.
const noVenueMockSelect = ( storeName ) => {
	switch ( storeName ) {
		case 'core/editor':
			return {
				getCurrentPostId: () => 0,
				getCurrentPostType: () => '',
				getEditedPostAttribute: () => null,
				getCurrentPost: () => null,
			};
		case 'core':
			return { getEditedEntityRecord: () => null };
		case 'core/block-editor':
			return { getBlockParentsByBlockName: () => [] };
		case 'gatherpress/venue':
			return {
				getVenueLatitude: () => null,
				getVenueLongitude: () => null,
			};
		default:
			return {};
	}
};

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn( ( selector ) => {
		const result = selector( noVenueMockSelect );
		// Identify the venue-state selector by its return shape.
		if (
			null !== result &&
			'object' === typeof result &&
			'venueMeta' in result
		) {
			capturedVenueStateSelector = selector;
		}
		return result;
	} ),
	useDispatch: jest.fn( () => ( {} ) ),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

jest.mock( '@wordpress/element', () => ( {
	useEffect: jest.fn(),
	useState: jest.requireActual( 'react' ).useState,
} ) );

jest.mock( '@wordpress/block-editor', () => ( {
	BlockControls: ( { children } ) => <div>{ children }</div>,
	InspectorControls: ( { children } ) => <div>{ children }</div>,
	useBlockProps: jest.fn( () => ( {} ) ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	Dropdown: ( { renderToggle, renderContent } ) => (
		<div>
			{ renderToggle( { isOpen: false, onToggle: () => {} } ) }
			{ renderContent() }
		</div>
	),
	Flex: ( { children } ) => <div>{ children }</div>,
	FlexItem: ( { children } ) => <div>{ children }</div>,
	PanelBody: ( { children } ) => <div>{ children }</div>,
	RangeControl: () => null,
	ResizableBox: ( props ) => {
		capturedResizableProps = props;
		return (
			<div
				data-testid="resizable-box"
				data-max-width={ String( props.maxWidth ) }
				data-size-width={ String( props.size?.width ) }
				data-margin-left={ String( props.style?.marginLeft ) }
				data-margin-right={ String( props.style?.marginRight ) }
			>
				{ props.children }
			</div>
		);
	},
	SelectControl: () => null,
	TextControl: () => null,
	ToggleControl: () => null,
	ToolbarButton: () => null,
	ToolbarGroup: ( { children } ) => <div>{ children }</div>,
	Icon: () => null,
	__experimentalToolsPanel: ( { children } ) => <div>{ children }</div>,
	__experimentalToolsPanelItem: ( { children } ) => <div>{ children }</div>,
} ) );

jest.mock( '@wordpress/icons', () => ( {
	Icon: () => null,
	link: null,
	mapMarker: null,
} ) );

jest.mock( '@src/helpers/venue', () => ( {
	isVenuePostType: jest.fn( () => false ),
} ) );

jest.mock( '@src/helpers/editor', () => ( {
	isInFSETemplate: jest.fn( () => false ),
} ) );

jest.mock( '@src/helpers/editor-settings', () => ( {
	getFromSettings: jest.fn( () => null ),
} ) );

jest.mock( '@src/components/MapEmbed', () => () => null );

jest.mock( '@src/components/GoogleMap', () => ( {
	GOOGLE_IFRAME_UNSUPPORTED_MAP_TYPE_SLUGS: [],
	GOOGLE_MAP_TYPE_DEFINITIONS: [],
	toMapsEmbedApiMapType: jest.fn( ( type ) => type ),
} ) );

jest.mock( '@src/supports/block-guard', () => ( {
	useSharedBlockGuardState: jest.fn( () => [ false ] ),
	generateBlockGuardStateKey: jest.fn(
		( type, id ) => `${ type }:${ id }`
	),
} ) );

jest.mock( '@src/blocks/venue-map/helpers', () => ( {
	RegenerateMapButton: () => null,
	parseAspectRatio: jest.fn( () => false ),
	pickDescriptorForCombo: jest.fn( () => undefined ),
	resolveDimensions: jest.fn( () => ( { width: 800, height: 400 } ) ),
	usePlaceholderPolling: jest.fn(),
	// Pure attribute readers — use the real implementations so the mocked
	// Edit derives dimensions exactly like production code.
	parsePxDimension: jest.requireActual( '@src/blocks/venue-map/helpers' )
		.parsePxDimension,
	getDimensionValue: jest.requireActual( '@src/blocks/venue-map/helpers' )
		.getDimensionValue,
} ) );

/**
 * Internal dependencies
 */
import Edit from '@src/blocks/venue-map/edit';
import { isVenuePostType } from '@src/helpers/venue';

const DEFAULT_ATTRIBUTES = {
	zoom: 16,
	type: 'roadmap',
	width: 0,
	height: 300,
	aspectRatio: '2/1',
	scale: 'cover',
	renderMode: 'static',
	align: '',
	href: '',
	linkDestination: 'none',
	linkTarget: '',
	rel: '',
};

describe( 'venue-map Edit useSelect selector stability', () => {
	beforeEach( () => {
		capturedVenueStateSelector = null;
		jest.clearAllMocks();
		// clearAllMocks does not reset mockReturnValue; restore the default
		// so each test starts with isVenuePostType returning false.
		isVenuePostType.mockReturnValue( false );
	} );

	it( 'returns the same venueMeta reference on repeated calls when there is no venue post', () => {
		render(
			<Edit
				attributes={ DEFAULT_ATTRIBUTES }
				setAttributes={ jest.fn() }
				context={ {} }
				clientId=""
			/>
		);

		expect( capturedVenueStateSelector ).not.toBeNull();

		const result1 = capturedVenueStateSelector( noVenueMockSelect );
		const result2 = capturedVenueStateSelector( noVenueMockSelect );

		expect( result1.venueMeta ).toBe( result2.venueMeta );
	} );

	it( 'returns the same savedVenueMeta reference on repeated calls when there is no venue post', () => {
		render(
			<Edit
				attributes={ DEFAULT_ATTRIBUTES }
				setAttributes={ jest.fn() }
				context={ {} }
				clientId=""
			/>
		);

		expect( capturedVenueStateSelector ).not.toBeNull();

		const result1 = capturedVenueStateSelector( noVenueMockSelect );
		const result2 = capturedVenueStateSelector( noVenueMockSelect );

		expect( result1.savedVenueMeta ).toBe( result2.savedVenueMeta );
	} );

	it( 'returns the same staticMapDescriptors reference on repeated calls when there is no venue post', () => {
		render(
			<Edit
				attributes={ DEFAULT_ATTRIBUTES }
				setAttributes={ jest.fn() }
				context={ {} }
				clientId=""
			/>
		);

		expect( capturedVenueStateSelector ).not.toBeNull();

		const result1 = capturedVenueStateSelector( noVenueMockSelect );
		const result2 = capturedVenueStateSelector( noVenueMockSelect );

		expect( result1.staticMapDescriptors ).toBe(
			result2.staticMapDescriptors
		);
	} );

	it( 'returns the same venueMeta reference when the venue post has null meta', () => {
		const nullMetaSelect = ( storeName ) => {
			switch ( storeName ) {
				case 'core/editor':
					return {
						getCurrentPostId: () => 0,
						getCurrentPostType: () => '',
						getEditedPostAttribute: () => null,
						getCurrentPost: () => null,
					};
				case 'core':
					return {
						// Venue post exists but has no meta.
						getEditedEntityRecord: () => ( {
							id: 99,
							meta: null,
						} ),
					};
				case 'core/block-editor':
					return { getBlockParentsByBlockName: () => [] };
				case 'gatherpress/venue':
					return {
						getVenueLatitude: () => null,
						getVenueLongitude: () => null,
					};
				default:
					return {};
			}
		};

		// Render with a context.postId to bypass the early bail.
		render(
			<Edit
				attributes={ DEFAULT_ATTRIBUTES }
				setAttributes={ jest.fn() }
				context={ { postId: 99, postType: 'gatherpress_venue' } }
				clientId=""
			/>
		);

		expect( capturedVenueStateSelector ).not.toBeNull();

		const result1 = capturedVenueStateSelector( nullMetaSelect );
		const result2 = capturedVenueStateSelector( nullMetaSelect );

		expect( result1.venueMeta ).toBe( result2.venueMeta );
	} );

	it( 'returns the same staticMapDescriptors reference when the venue post has no static map meta', () => {
		const noMapSelect = ( storeName ) => {
			switch ( storeName ) {
				case 'core/editor':
					return {
						getCurrentPostId: () => 0,
						getCurrentPostType: () => '',
						getEditedPostAttribute: () => null,
						getCurrentPost: () => null,
					};
				case 'core':
					return {
						// Venue post with meta but no gatherpress_static_map key.
						getEditedEntityRecord: () => ( {
							id: 99,
							meta: { gatherpress_address: '123 Main St' },
						} ),
					};
				case 'core/block-editor':
					return { getBlockParentsByBlockName: () => [] };
				case 'gatherpress/venue':
					return {
						getVenueLatitude: () => null,
						getVenueLongitude: () => null,
					};
				default:
					return {};
			}
		};

		render(
			<Edit
				attributes={ DEFAULT_ATTRIBUTES }
				setAttributes={ jest.fn() }
				context={ { postId: 99, postType: 'gatherpress_venue' } }
				clientId=""
			/>
		);

		expect( capturedVenueStateSelector ).not.toBeNull();

		const result1 = capturedVenueStateSelector( noMapSelect );
		const result2 = capturedVenueStateSelector( noMapSelect );

		expect( result1.staticMapDescriptors ).toBe(
			result2.staticMapDescriptors
		);
	} );

	it( 'returns stable savedVenueMeta and staticMapDescriptors references in the isEditing branch', () => {
		// Trigger the isEditing path: getCurrentPostId() matches context.postId
		// and isVenuePostType() returns true.
		isVenuePostType.mockReturnValue( true );

		const editingSelect = ( storeName ) => {
			switch ( storeName ) {
				case 'core/editor':
					return {
						getCurrentPostId: () => 42,
						getCurrentPostType: () => 'gatherpress_venue',
						// Null meta and null post ensure the || EMPTY_META and
						// || EMPTY_STATIC_MAP_DESCRIPTORS fallbacks are exercised.
						getEditedPostAttribute: () => null,
						getCurrentPost: () => null,
					};
				case 'core':
					return { getEditedEntityRecord: () => null };
				case 'core/block-editor':
					return { getBlockParentsByBlockName: () => [] };
				case 'gatherpress/venue':
					return {
						getVenueLatitude: () => null,
						getVenueLongitude: () => null,
					};
				default:
					return {};
			}
		};

		render(
			<Edit
				attributes={ DEFAULT_ATTRIBUTES }
				setAttributes={ jest.fn() }
				context={ { postId: 42, postType: 'gatherpress_venue' } }
				clientId=""
			/>
		);

		expect( capturedVenueStateSelector ).not.toBeNull();

		const result1 = capturedVenueStateSelector( editingSelect );
		const result2 = capturedVenueStateSelector( editingSelect );

		expect( result1.savedVenueMeta ).toBe( result2.savedVenueMeta );
		expect( result1.staticMapDescriptors ).toBe(
			result2.staticMapDescriptors
		);
	} );
} );

describe( 'venue-map Edit sizing wrappers', () => {
	beforeEach( () => {
		capturedVenueStateSelector = null;
		jest.clearAllMocks();
		isVenuePostType.mockReturnValue( false );
	} );

	it( 'clamps the resize box at 100% of its container', () => {
		const { getByTestId } = render(
			<Edit
				attributes={ {
					...DEFAULT_ATTRIBUTES,
					style: { dimensions: { width: '779px' } },
				} }
				setAttributes={ jest.fn() }
				context={ {} }
				clientId=""
			/>
		);

		const box = getByTestId( 'resizable-box' );
		expect( box.dataset.maxWidth ).toBe( '100%' );
		expect( box.dataset.sizeWidth ).toBe( '779' );
	} );

	it( 'shrinks the block wrapper to fit a fixed-width map and centers it', () => {
		const { useBlockProps } = jest.requireMock(
			'@wordpress/block-editor'
		);

		render(
			<Edit
				attributes={ {
					...DEFAULT_ATTRIBUTES,
					align: 'center',
					style: { dimensions: { width: '779px' } },
				} }
				setAttributes={ jest.fn() }
				context={ {} }
				clientId=""
			/>
		);

		expect( useBlockProps ).toHaveBeenCalledWith( {
			style: {
				width: 'fit-content',
				marginLeft: 'auto',
				marginRight: 'auto',
			},
		} );
	} );

	it( 'keeps alignment margins on the box so a centered map holds position mid-drag', () => {
		const { getByTestId } = render(
			<Edit
				attributes={ {
					...DEFAULT_ATTRIBUTES,
					align: 'center',
					style: { dimensions: { width: '779px' } },
				} }
				setAttributes={ jest.fn() }
				context={ {} }
				clientId=""
			/>
		);

		const box = getByTestId( 'resizable-box' );
		expect( box.dataset.marginLeft ).toBe( 'auto' );
		expect( box.dataset.marginRight ).toBe( 'auto' );
	} );

	it( 'leaves the box unaligned when the map has no alignment', () => {
		const { getByTestId } = render(
			<Edit
				attributes={ {
					...DEFAULT_ATTRIBUTES,
					style: { dimensions: { width: '779px' } },
				} }
				setAttributes={ jest.fn() }
				context={ {} }
				clientId=""
			/>
		);

		const box = getByTestId( 'resizable-box' );
		expect( box.dataset.marginLeft ).toBe( 'undefined' );
		expect( box.dataset.marginRight ).toBe( 'undefined' );
	} );

	it( 'measures a pixel growth ceiling at drag start and resets it on release', () => {
		const { getByTestId } = render(
			<Edit
				attributes={ {
					...DEFAULT_ATTRIBUTES,
					style: { dimensions: { width: '779px' } },
				} }
				setAttributes={ jest.fn() }
				context={ {} }
				clientId=""
			/>
		);

		const box = getByTestId( 'resizable-box' );
		expect( box.dataset.maxWidth ).toBe( '100%' );

		// Fake enough DOM for the measurement: the block wrapper is capped
		// at 645px by a constrained parent whose content box is 1206px.
		const doc = { defaultView: { getComputedStyle: ( n ) => n.styles } };
		const parentEl = {
			clientWidth: 1266,
			ownerDocument: doc,
			styles: { paddingLeft: '30px', paddingRight: '30px' },
		};
		const blockEl = {
			parentElement: parentEl,
			ownerDocument: doc,
			styles: { maxWidth: '645px' },
		};

		act( () =>
			capturedResizableProps.onResizeStart( null, 'right', {
				closest: () => blockEl,
			} )
		);
		// The tighter of column width (1206) and the parent layout's cap
		// (645) wins.
		expect( getByTestId( 'resizable-box' ).dataset.maxWidth ).toBe(
			'645'
		);

		act( () =>
			capturedResizableProps.onResizeStop( null, 'right', null, {
				width: 10,
				height: 5,
			} )
		);
		expect( getByTestId( 'resizable-box' ).dataset.maxWidth ).toBe(
			'100%'
		);
	} );

	it( 'uses the column width when no parent layout caps the block', () => {
		const { getByTestId } = render(
			<Edit
				attributes={ {
					...DEFAULT_ATTRIBUTES,
					style: { dimensions: { width: '779px' } },
				} }
				setAttributes={ jest.fn() }
				context={ {} }
				clientId=""
			/>
		);

		const doc = { defaultView: { getComputedStyle: ( n ) => n.styles } };
		const parentEl = {
			clientWidth: 1266,
			ownerDocument: doc,
			styles: { paddingLeft: '0px', paddingRight: '0px' },
		};
		const blockEl = {
			parentElement: parentEl,
			ownerDocument: doc,
			styles: { maxWidth: 'none' },
		};

		act( () =>
			capturedResizableProps.onResizeStart( null, 'right', {
				closest: () => blockEl,
			} )
		);
		expect( getByTestId( 'resizable-box' ).dataset.maxWidth ).toBe(
			'1266'
		);
	} );

	it( 'keeps the 100% clamp when the drag environment cannot be measured', () => {
		const { useBlockProps } = jest.requireMock(
			'@wordpress/block-editor'
		);

		const { getByTestId } = render(
			<Edit
				attributes={ {
					...DEFAULT_ATTRIBUTES,
					style: { dimensions: { width: '779px' } },
				} }
				setAttributes={ jest.fn() }
				context={ {} }
				clientId=""
			/>
		);

		act( () =>
			capturedResizableProps.onResizeStart( null, 'right', {
				closest: () => null,
			} )
		);
		expect( getByTestId( 'resizable-box' ).dataset.maxWidth ).toBe(
			'100%'
		);

		// The shrink-wrapped wrapper stays on through the drag — releasing
		// it is what shoved parent-centered maps to the left mid-resize.
		expect( useBlockProps ).toHaveBeenLastCalledWith( {
			style: expect.objectContaining( { width: 'fit-content' } ),
		} );
	} );

	it( 'keeps the full-width block wrapper for auto and wide/full maps', () => {
		const { useBlockProps } = jest.requireMock(
			'@wordpress/block-editor'
		);

		render(
			<Edit
				attributes={ DEFAULT_ATTRIBUTES }
				setAttributes={ jest.fn() }
				context={ {} }
				clientId=""
			/>
		);
		expect( useBlockProps ).toHaveBeenCalledWith( {
			style: undefined,
		} );

		jest.clearAllMocks();
		isVenuePostType.mockReturnValue( false );

		render(
			<Edit
				attributes={ {
					...DEFAULT_ATTRIBUTES,
					align: 'full',
					style: { dimensions: { width: '779px' } },
				} }
				setAttributes={ jest.fn() }
				context={ {} }
				clientId=""
			/>
		);
		expect( useBlockProps ).toHaveBeenCalledWith( {
			style: undefined,
		} );
	} );
} );
