/**
 * External dependencies
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';
import { render } from '@testing-library/react';

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

// Configurable select implementation; individual tests may override this.
let mockSelectImpl = null;

// Captured props passed to MapEmbed on the most recent render.
let capturedMapEmbedProps = null;

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
		const selectFn = mockSelectImpl || noVenueMockSelect;
		const result = selector( selectFn );
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
	ResizableBox: ( { children } ) => <div>{ children }</div>,
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

jest.mock( '@src/components/MapEmbed', () => ( props ) => {
	capturedMapEmbedProps = props;
	return null;
} );

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
		mockSelectImpl = null;
		capturedMapEmbedProps = null;
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

describe( 'venue-map Edit coordinate resolution', () => {
	const SAVED_VENUE_META = {
		gatherpress_address: '123 Main St',
		gatherpress_latitude: '40.7128',
		gatherpress_longitude: '-74.0060',
	};

	const INTERACTIVE_ATTRIBUTES = {
		...DEFAULT_ATTRIBUTES,
		renderMode: 'interactive',
	};

	/**
	 * Build a select mock for the in-editor venue path.
	 *
	 * @param {Object} options          Mock options.
	 * @param {number} options.storeLat Venue store latitude.
	 * @param {number} options.storeLng Venue store longitude.
	 * @param {Object} options.meta     Edited post meta.
	 * @return {Function} WordPress data select mock.
	 */
	const buildEditingVenueSelect = ( {
		storeLat,
		storeLng,
		meta = SAVED_VENUE_META,
	} ) => {
		return ( storeName ) => {
			switch ( storeName ) {
				case 'core/editor':
					return {
						getCurrentPostId: () => 42,
						getCurrentPostType: () => 'gatherpress_venue',
						getEditedPostAttribute: ( attr ) =>
							'meta' === attr ? meta : null,
						getCurrentPost: () => ( { meta } ),
					};
				case 'core':
					return {
						getEditedEntityRecord: () => ( { meta } ),
					};
				case 'core/block-editor':
					return { getBlockParentsByBlockName: () => [] };
				case 'gatherpress/venue':
					return {
						getVenueLatitude: () => storeLat,
						getVenueLongitude: () => storeLng,
					};
				default:
					return {};
			}
		};
	};

	beforeEach( () => {
		capturedMapEmbedProps = null;
		mockSelectImpl = null;
		jest.clearAllMocks();
		isVenuePostType.mockReturnValue( true );
	} );

	it( 'uses post meta coordinates when the venue store is still at 0/0', () => {
		mockSelectImpl = buildEditingVenueSelect( {
			storeLat: 0,
			storeLng: 0,
		} );

		render(
			<Edit
				attributes={ INTERACTIVE_ATTRIBUTES }
				setAttributes={ jest.fn() }
				context={ { postId: 42, postType: 'gatherpress_venue' } }
				clientId=""
			/>
		);

		expect( capturedMapEmbedProps ).not.toBeNull();
		expect( capturedMapEmbedProps.latitude ).toBe( '40.7128' );
		expect( capturedMapEmbedProps.longitude ).toBe( '-74.0060' );
	} );

	it( 'prefers venue store coordinates once the store holds real values', () => {
		mockSelectImpl = buildEditingVenueSelect( {
			storeLat: 51.5074,
			storeLng: -0.1278,
		} );

		render(
			<Edit
				attributes={ INTERACTIVE_ATTRIBUTES }
				setAttributes={ jest.fn() }
				context={ { postId: 42, postType: 'gatherpress_venue' } }
				clientId=""
			/>
		);

		expect( capturedMapEmbedProps ).not.toBeNull();
		expect( capturedMapEmbedProps.latitude ).toBe( '51.5074' );
		expect( capturedMapEmbedProps.longitude ).toBe( '-0.1278' );
	} );
} );
