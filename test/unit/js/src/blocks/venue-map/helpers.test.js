/**
 * External dependencies.
 */
import { afterEach, beforeEach, describe, expect, it, jest } from '@jest/globals';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * Mocks — declared before the component import so jest hoists them.
 */
const mockApiFetch = jest.fn();
const mockInvalidateResolution = jest.fn();
const mockReceiveEntityRecords = jest.fn();
const mockCreateErrorNotice = jest.fn();
let mockCurrentEntityRecord;

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: ( args ) => mockApiFetch( args ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: ( store ) => {
		if ( 'core/notices' === store ) {
			return { createErrorNotice: mockCreateErrorNotice };
		}
		return {
			invalidateResolution: mockInvalidateResolution,
			receiveEntityRecords: mockReceiveEntityRecords,
		};
	},
	useSelect: ( callback ) =>
		callback( () => ( {
			getEntityRecord: () => mockCurrentEntityRecord,
		} ) ),
} ) );

// Lightweight stand-ins for the two @wordpress/components primitives this
// component uses. Importing the real package pulls in @wordpress/rich-text
// which needs a full @wordpress/data runtime wired up, which we don't.
jest.mock( '@wordpress/components', () => ( {
	Button: ( { children, ...props } ) => (
		<button type="button" { ...props }>
			{ children }
		</button>
	),
	Spinner: () => <span data-testid="spinner" />,
} ) );

/**
 * Internal dependencies.
 */
import {
	MAX_POLLS,
	POLL_INTERVAL_MS,
	RegenerateMapButton,
	parseAspectRatio,
	pickDescriptorForCombo,
	resolveDimensions,
	usePlaceholderPolling,
} from '@src/blocks/venue-map/helpers';

describe( 'RegenerateMapButton', () => {
	const defaultProps = {
		venuePostId: 42,
		venuePostType: 'gatherpress_venue',
	};

	beforeEach( () => {
		mockApiFetch.mockReset();
		mockInvalidateResolution.mockReset();
		mockReceiveEntityRecords.mockReset();
		mockCreateErrorNotice.mockReset();
		mockApiFetch.mockResolvedValue( { descriptors: {}, reason: '' } );
		mockCurrentEntityRecord = {
			id: 42,
			meta: { gatherpress_address: '' },
		};
	} );

	it( 'renders the default "Regenerate map" label', () => {
		render( <RegenerateMapButton { ...defaultProps } /> );
		expect(
			screen.getByRole( 'button', { name: /regenerate map/i } )
		).toBeInTheDocument();
	} );

	it( 'renders a custom label when provided', () => {
		render(
			<RegenerateMapButton { ...defaultProps } label="Generate map" />
		);
		expect(
			screen.getByRole( 'button', { name: /generate map/i } )
		).toBeInTheDocument();
	} );

	it( 'is disabled when the disabled prop is true', () => {
		render( <RegenerateMapButton { ...defaultProps } disabled /> );
		expect( screen.getByRole( 'button' ) ).toBeDisabled();
	} );

	it( 'POSTs to the correct REST path on click', async () => {
		render( <RegenerateMapButton { ...defaultProps } /> );
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/gatherpress/v1/venue/42/regenerate-map',
					method: 'POST',
				} )
			);
		} );
	} );

	it( 'forwards current zoom/width/height/aspect_ratio in the POST body so the server renders this combo', async () => {
		render(
			<RegenerateMapButton
				{ ...defaultProps }
				zoom={ 8 }
				width={ 0 }
				height={ 295 }
				aspectRatio="16/9"
			/>
		);
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					data: {
						zoom: 8,
						width: 0,
						height: 295,
						aspect_ratio: '16/9',
					},
				} )
			);
		} );
	} );

	it( 'invalidates the core entity record on success so the editor re-reads meta', async () => {
		render( <RegenerateMapButton { ...defaultProps } /> );
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockInvalidateResolution ).toHaveBeenCalledWith(
				'getEntityRecord',
				[ 'postType', 'gatherpress_venue', 42 ]
			);
		} );
	} );

	it( 'patches fresh descriptors into the core store cache', async () => {
		mockApiFetch.mockResolvedValueOnce( {
			descriptors: {
				'18x300': {
					url: 'https://example.test/42-abc.png',
					hash: 'abc',
					zoom: 18,
					height: 300,
				},
			},
			reason: '',
		} );

		render( <RegenerateMapButton { ...defaultProps } /> );
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockReceiveEntityRecords ).toHaveBeenCalledTimes( 1 );
		} );

		const [ kind, name, records ] =
			mockReceiveEntityRecords.mock.calls[ 0 ];
		expect( kind ).toBe( 'postType' );
		expect( name ).toBe( 'gatherpress_venue' );
		expect( records[ 0 ].id ).toBe( 42 );
		expect(
			records[ 0 ].meta.gatherpress_static_map[ '18x300' ].url
		).toBe( 'https://example.test/42-abc.png' );
		// Existing meta fields must be preserved.
		expect( records[ 0 ].meta.gatherpress_address ).toBe( '' );
	} );

	it( 'skips the store patch when no cached record exists yet', async () => {
		mockCurrentEntityRecord = null;

		render( <RegenerateMapButton { ...defaultProps } /> );
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalled();
		} );

		expect( mockReceiveEntityRecords ).not.toHaveBeenCalled();
	} );

	it( 'does not call the REST endpoint when venuePostId is missing', () => {
		render( <RegenerateMapButton venuePostId={ 0 } venuePostType="" /> );
		fireEvent.click( screen.getByRole( 'button' ) );
		expect( mockApiFetch ).not.toHaveBeenCalled();
	} );

	it( 'skips the store invalidation when no venuePostType is known', async () => {
		render(
			<RegenerateMapButton venuePostId={ 42 } venuePostType="" />
		);
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalled();
		} );

		expect( mockInvalidateResolution ).not.toHaveBeenCalled();
	} );

	it( 'ignores repeat clicks while a request is in flight', async () => {
		let resolveFetch;
		mockApiFetch.mockImplementationOnce(
			() => new Promise( ( resolve ) => ( resolveFetch = resolve ) )
		);

		render( <RegenerateMapButton { ...defaultProps } /> );
		const button = screen.getByRole( 'button' );

		fireEvent.click( button );
		fireEvent.click( button );
		fireEvent.click( button );

		resolveFetch( { descriptors: {}, reason: '' } );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	it( 'surfaces an error notice and re-enables the button when apiFetch rejects', async () => {
		mockApiFetch.mockRejectedValueOnce( new Error( 'Network down' ) );

		render( <RegenerateMapButton { ...defaultProps } /> );
		const button = screen.getByRole( 'button' );
		fireEvent.click( button );

		await waitFor( () => {
			expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
				'Network down',
				{ type: 'snackbar' }
			);
		} );

		// Cached descriptor preserved — the store patch must not fire on failure.
		expect( mockReceiveEntityRecords ).not.toHaveBeenCalled();
		expect( mockInvalidateResolution ).not.toHaveBeenCalled();
		// Button is no longer busy — next click can retry.
		expect( button ).not.toBeDisabled();
	} );

	it( 'falls back to a translated message when the rejected error has no .message', async () => {
		// `{}` has no `message` so `error?.message || __( 'Could not …' )`
		// must pick the translated fallback — the branch we missed last PR.
		mockApiFetch.mockRejectedValueOnce( {} );

		render( <RegenerateMapButton { ...defaultProps } /> );
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
				expect.stringContaining( 'Could not regenerate' ),
				{ type: 'snackbar' }
			);
		} );
	} );

	it( 'patches fresh descriptors into an empty meta object when current.meta is missing', async () => {
		// A cached entity record that never got a `meta` key yet forces the
		// `...( current.meta || {} )` spread to fall through to `{}`.
		mockCurrentEntityRecord = { id: 42 };
		mockApiFetch.mockResolvedValueOnce( {
			descriptors: {
				'18x300': {
					url: 'https://example.test/42-abc.png',
					hash: 'abc',
					zoom: 18,
					height: 300,
				},
			},
			reason: '',
		} );

		render( <RegenerateMapButton { ...defaultProps } /> );
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockReceiveEntityRecords ).toHaveBeenCalledTimes( 1 );
		} );

		const [ , , records ] = mockReceiveEntityRecords.mock.calls[ 0 ];
		expect( records[ 0 ].meta.gatherpress_static_map ).toEqual( {
			'18x300': {
				url: 'https://example.test/42-abc.png',
				hash: 'abc',
				zoom: 18,
				height: 300,
			},
		} );
	} );

	it( 'stores an empty descriptor map when the response omits descriptors', async () => {
		// A success response that forgets the `descriptors` key exercises the
		// `response?.descriptors || {}` fallback so we never serialize
		// `undefined` into the entity-record meta.
		mockApiFetch.mockResolvedValueOnce( { reason: '' } );

		render( <RegenerateMapButton { ...defaultProps } /> );
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockReceiveEntityRecords ).toHaveBeenCalledTimes( 1 );
		} );

		const [ , , records ] = mockReceiveEntityRecords.mock.calls[ 0 ];
		expect( records[ 0 ].meta.gatherpress_static_map ).toEqual( {} );
	} );

	it( 'surfaces an error notice when the server reports generation_failed', async () => {
		mockApiFetch.mockResolvedValueOnce( {
			descriptors: {},
			reason: 'generation_failed',
		} );

		render( <RegenerateMapButton { ...defaultProps } /> );
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
				expect.stringContaining( 'could not render' ),
				{ type: 'snackbar' }
			);
		} );

		// Don't overwrite the cached descriptor with an empty map.
		expect( mockReceiveEntityRecords ).not.toHaveBeenCalled();
		expect( mockInvalidateResolution ).not.toHaveBeenCalled();
	} );
} );

describe( 'parseAspectRatio', () => {
	it( 'parses the slash form "16/9" into a float', () => {
		expect( parseAspectRatio( '16/9' ) ).toBeCloseTo( 16 / 9, 4 );
	} );
	it( 'accepts the colon form "4:3"', () => {
		expect( parseAspectRatio( '4:3' ) ).toBeCloseTo( 4 / 3, 4 );
	} );
	it( 'returns null for unparsable input', () => {
		expect( parseAspectRatio( 'nonsense' ) ).toBeNull();
		expect( parseAspectRatio( '' ) ).toBeNull();
		expect( parseAspectRatio( null ) ).toBeNull();
	} );
	it( 'returns null when either side is zero', () => {
		expect( parseAspectRatio( '0/1' ) ).toBeNull();
		expect( parseAspectRatio( '4/0' ) ).toBeNull();
	} );
} );

describe( 'pickDescriptorForCombo', () => {
	const osmDescriptor = {
		url: 'https://example.test/osm.png',
		url_2x: 'https://example.test/osm@2x.png',
		hash: 'abc',
		zoom: 18,
		width: 600,
		height: 300,
	};
	const googleDescriptor = {
		url: 'https://example.test/google.png',
		url_2x: 'https://example.test/google@2x.png',
		hash: 'def',
		zoom: 18,
		width: 600,
		height: 300,
	};

	it( 'returns the active provider descriptor when present', () => {
		const descriptors = {
			osm: { '18x600x300': osmDescriptor },
			google: { '18x600x300': googleDescriptor },
		};

		expect(
			pickDescriptorForCombo( descriptors, '18x600x300', 'google' )
		).toBe( googleDescriptor );
	} );

	it( 'falls back to another provider when the active one is missing the combo', () => {
		const descriptors = {
			osm: { '18x600x300': osmDescriptor },
		};

		expect(
			pickDescriptorForCombo( descriptors, '18x600x300', 'google' )
		).toBe( osmDescriptor );
	} );

	it( 'returns undefined when no provider has the combo', () => {
		const descriptors = {
			osm: { '15x800x400': osmDescriptor },
		};

		expect(
			pickDescriptorForCombo( descriptors, '18x600x300', 'osm' )
		).toBeUndefined();
	} );

	it( 'tolerates a missing or empty descriptor map', () => {
		expect(
			pickDescriptorForCombo( undefined, '18x600x300', 'osm' )
		).toBeUndefined();
		expect(
			pickDescriptorForCombo( {}, '18x600x300', 'osm' )
		).toBeUndefined();
	} );

	it( 'skips the active slug when scanning for fallbacks', () => {
		const descriptors = {
			osm: { '18x600x300': osmDescriptor },
			google: {},
		};

		expect(
			pickDescriptorForCombo( descriptors, '18x600x300', 'google' )
		).toBe( osmDescriptor );
	} );
} );

describe( 'resolveDimensions', () => {
	const defaults = { defaultHeight: 300 };

	it( 'returns both sides unchanged when both are explicit', () => {
		expect(
			resolveDimensions( {
				...defaults,
				width: 800,
				height: 400,
				aspectRatio: '2/1',
			} )
		).toEqual( { width: 800, height: 400 } );
	} );
	it( 'derives width from height × ratio when width is auto', () => {
		expect(
			resolveDimensions( {
				...defaults,
				width: 0,
				height: 400,
				aspectRatio: '2/1',
			} )
		).toEqual( { width: 800, height: 400 } );
	} );
	it( 'derives height from width ÷ ratio when height is auto', () => {
		expect(
			resolveDimensions( {
				...defaults,
				width: 900,
				height: 0,
				aspectRatio: '3/2',
			} )
		).toEqual( { width: 900, height: 600 } );
	} );
	it( 'seeds from defaultHeight when both sides are auto', () => {
		expect(
			resolveDimensions( {
				...defaults,
				width: 0,
				height: 0,
				aspectRatio: '2/1',
			} )
		).toEqual( { width: 600, height: 300 } );
	} );
	it( 'falls back to a 2:1 ratio when the aspect string is unparsable', () => {
		expect(
			resolveDimensions( {
				...defaults,
				width: 0,
				height: 400,
				aspectRatio: 'garbage',
			} )
		).toEqual( { width: 800, height: 400 } );
	} );
} );

describe( 'usePlaceholderPolling', () => {
	// Minimal test harness that invokes the hook inside a React component.
	// The component renders nothing — it exists only to run the effect and
	// let us drive re-renders / unmounts via props.
	const Harness = ( props ) => {
		usePlaceholderPolling( props );
		return null;
	};

	beforeEach( () => {
		mockInvalidateResolution.mockReset();
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	const activeProps = {
		active: true,
		venuePostId: 42,
		venuePostType: 'gatherpress_venue',
	};

	it( 'does not schedule an interval when active is false', () => {
		render( <Harness { ...activeProps } active={ false } /> );

		jest.advanceTimersByTime( POLL_INTERVAL_MS * 3 );

		expect( mockInvalidateResolution ).not.toHaveBeenCalled();
	} );

	it( 'does not schedule when venuePostId is missing', () => {
		render( <Harness { ...activeProps } venuePostId={ 0 } /> );

		jest.advanceTimersByTime( POLL_INTERVAL_MS * 3 );

		expect( mockInvalidateResolution ).not.toHaveBeenCalled();
	} );

	it( 'does not schedule when venuePostType is missing', () => {
		render( <Harness { ...activeProps } venuePostType="" /> );

		jest.advanceTimersByTime( POLL_INTERVAL_MS * 3 );

		expect( mockInvalidateResolution ).not.toHaveBeenCalled();
	} );

	it( 'ticks on the expected cadence and forwards the resolver args', () => {
		render( <Harness { ...activeProps } /> );

		jest.advanceTimersByTime( POLL_INTERVAL_MS - 1 );
		expect( mockInvalidateResolution ).not.toHaveBeenCalled();

		jest.advanceTimersByTime( 1 );
		expect( mockInvalidateResolution ).toHaveBeenCalledTimes( 1 );
		expect( mockInvalidateResolution ).toHaveBeenLastCalledWith(
			'getEntityRecord',
			[ 'postType', 'gatherpress_venue', 42 ]
		);

		jest.advanceTimersByTime( POLL_INTERVAL_MS );
		expect( mockInvalidateResolution ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'stops polling after MAX_POLLS ticks', () => {
		render( <Harness { ...activeProps } /> );

		// Advance one tick past the cap. The pre-increment guard clears the
		// interval on the tick that would push pollCount over MAX_POLLS, so
		// exactly MAX_POLLS invalidations fire before the stop.
		jest.advanceTimersByTime( POLL_INTERVAL_MS * ( MAX_POLLS + 5 ) );

		expect( mockInvalidateResolution ).toHaveBeenCalledTimes( MAX_POLLS );
	} );

	it( 'clears the interval on unmount', () => {
		const { unmount } = render( <Harness { ...activeProps } /> );

		unmount();

		jest.advanceTimersByTime( POLL_INTERVAL_MS * 3 );
		expect( mockInvalidateResolution ).not.toHaveBeenCalled();
	} );

	it( 'clears the interval when active flips back to false', () => {
		const { rerender } = render( <Harness { ...activeProps } /> );

		jest.advanceTimersByTime( POLL_INTERVAL_MS );
		expect( mockInvalidateResolution ).toHaveBeenCalledTimes( 1 );

		rerender( <Harness { ...activeProps } active={ false } /> );

		jest.advanceTimersByTime( POLL_INTERVAL_MS * 3 );
		expect( mockInvalidateResolution ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'restarts cleanly with a fresh poll count when venuePostId changes', () => {
		const { rerender } = render( <Harness { ...activeProps } /> );

		// Run halfway to the MAX_POLLS cap against venue 42.
		jest.advanceTimersByTime( POLL_INTERVAL_MS * ( MAX_POLLS / 2 ) );
		expect( mockInvalidateResolution ).toHaveBeenCalledTimes(
			MAX_POLLS / 2
		);

		// Switch to a new venue — the interval should tear down and restart
		// with a fresh pollCount targeting the new ID, not inherit the
		// old counter (which would cap polling prematurely).
		rerender( <Harness { ...activeProps } venuePostId={ 99 } /> );

		// Drive another full MAX_POLLS cycle against venue 99; all should
		// fire with the new ID and then cap out.
		jest.advanceTimersByTime( POLL_INTERVAL_MS * ( MAX_POLLS + 2 ) );
		expect( mockInvalidateResolution ).toHaveBeenCalledTimes(
			( MAX_POLLS / 2 ) + MAX_POLLS
		);
		expect( mockInvalidateResolution ).toHaveBeenLastCalledWith(
			'getEntityRecord',
			[ 'postType', 'gatherpress_venue', 99 ]
		);
	} );
} );
