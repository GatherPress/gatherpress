/**
 * External dependencies
 */
import {
	act,
	render,
	fireEvent,
	screen,
	waitFor,
} from '@testing-library/react';
import { beforeEach, describe, expect, jest, test } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies
 */
jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
	_n: ( single, plural, number ) => ( 1 === number ? single : plural ),
	sprintf: ( format, ...args ) =>
		format.replace( /%d/g, () => String( args.shift() ) ),
} ) );

const mockApiFetch = jest.fn();

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: ( args ) => mockApiFetch( args ),
} ) );

const mockIsEditedPostDirty = jest.fn( () => false );
const mockGetCurrentPostType = jest.fn( () => 'gatherpress_event' );
const mockGetPostType = jest.fn( () => ( {
	rest_base: 'gatherpress_events',
	rest_namespace: 'wp/v2',
} ) );
const mockReceiveEntityRecords = jest.fn();

jest.mock( '@wordpress/data', () => ( {
	useSelect: ( callback ) =>
		callback( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					isEditedPostDirty: mockIsEditedPostDirty,
					getCurrentPostType: mockGetCurrentPostType,
				};
			}

			return {
				getPostType: mockGetPostType,
			};
		} ),
	useDispatch: () => ( {
		receiveEntityRecords: mockReceiveEntityRecords,
	} ),
} ) );

jest.mock( '@wordpress/url', () => ( {
	addQueryArgs: ( path, args ) =>
		`${ path }?${ Object.entries( args )
			.map( ( [ key, value ] ) => `${ key }=${ value }` )
			.join( '&' ) }`,
} ) );

jest.mock( '@wordpress/components', () => ( {
	Button: ( { children, onClick, disabled, isBusy } ) => (
		<button
			type="button"
			onClick={ onClick }
			disabled={ disabled }
			data-busy={ !! isBusy }
		>
			{ children }
		</button>
	),
	RadioControl: ( { label, selected, options, onChange } ) => (
		<select
			aria-label={ label }
			value={ selected }
			onChange={ ( event ) => onChange( event.target.value ) }
		>
			{ options.map( ( option ) => (
				<option key={ option.value } value={ option.value }>
					{ option.label }
				</option>
			) ) }
		</select>
	),
	SelectControl: ( { label, value, options, onChange } ) => (
		<select
			aria-label={ label }
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
		>
			{ options.map( ( option ) => (
				<option key={ option.value } value={ option.value }>
					{ option.label }
				</option>
			) ) }
		</select>
	),
} ) );

jest.mock( '@wordpress/date', () => ( {
	dateI18n: ( format, date ) => `formatted:${ date }`,
	getSettings: () => ( { formats: { datetime: 'F j, Y g:i a' } } ),
} ) );

/**
 * Internal dependencies
 */
import ApplyScope from '@src/panels/event-settings/recurrence/apply-scope';

/**
 * Build an occurrence row as the `occurrences` REST route returns it.
 *
 * @param {string} recurrenceId Occurrence identifier.
 * @param {string} start        Local start.
 *
 * @return {Object} The row.
 */
function occurrence( recurrenceId, start ) {
	return {
		series_post_id: 42,
		recurrence_id: recurrenceId,
		datetime_start: start,
		status: 'scheduled',
	};
}

const rows = [
	occurrence( '20260903T180000', '2026-09-03 18:00:00' ),
	occurrence( '20260917T180000', '2026-09-17 18:00:00' ),
];

/**
 * Three dates of one unsplit series, all owned by the post being edited.
 *
 * Three rather than two so a split at the default selection still leaves a
 * later date to split again from, which is what a second split in one session
 * needs.
 */
const threeRows = [
	occurrence( '20260903T180000', '2026-09-03 18:00:00' ),
	occurrence( '20260917T180000', '2026-09-17 18:00:00' ),
	occurrence( '20261001T180000', '2026-10-01 18:00:00' ),
];

/**
 * The same three dates as the server reports them once the second and third
 * have been moved onto post 99, which is what a split at the second date does.
 */
const threeRowsAfterFirstSplit = [
	threeRows[ 0 ],
	{ ...threeRows[ 1 ], series_post_id: 99 },
	{ ...threeRows[ 2 ], series_post_id: 99 },
];

beforeEach( () => {
	jest.clearAllMocks();
	// A queued mockResolvedValueOnce a test did not consume would otherwise
	// leak into the next test: clearAllMocks() empties recorded calls but not
	// the once-queue or base implementation.
	mockApiFetch.mockReset();
	mockIsEditedPostDirty.mockReturnValue( false );
	mockGetCurrentPostType.mockReturnValue( 'gatherpress_event' );
	mockGetPostType.mockReturnValue( {
		rest_base: 'gatherpress_events',
		rest_namespace: 'wp/v2',
	} );
} );

describe( 'ApplyScope', () => {
	test( 'defaults to retroactive and requests nothing', () => {
		render( <ApplyScope postId={ 42 } /> );

		expect(
			screen.getByLabelText( 'Applying changes' ),
		).toHaveValue( 'retroactive' );
		expect( mockApiFetch ).not.toHaveBeenCalled();
		expect(
			screen.queryByLabelText( 'Split from' ),
		).not.toBeInTheDocument();
	} );

	test( 'lists the upcoming occurrences once forward is chosen', async () => {
		mockApiFetch.mockResolvedValue( rows );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toHaveValue(
				'20260917T180000',
			),
		);
		expect( mockApiFetch ).toHaveBeenCalledWith( {
			path: '/gatherpress/v1/event/occurrences?post_id=42',
		} );
	} );

	test( 'does not request the occurrence list without a post id', () => {
		render( <ApplyScope postId={ 0 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		expect( mockApiFetch ).not.toHaveBeenCalled();
	} );

	test( 'falls back to an empty list when the occurrence fetch fails', async () => {
		mockApiFetch.mockRejectedValue( new Error( 'nope' ) );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () => expect( mockApiFetch ).toHaveBeenCalled() );
		expect( screen.getByLabelText( 'Split from' ).children ).toHaveLength(
			0,
		);
		expect( screen.getByText( 'Split series' ) ).toBeDisabled();
	} );

	test( 'copes with an occurrence response that carries no rows at all', async () => {
		mockApiFetch.mockResolvedValue( undefined );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () => expect( mockApiFetch ).toHaveBeenCalled() );
		expect( screen.getByLabelText( 'Split from' ).children ).toHaveLength(
			0,
		);
		expect( screen.getByText( 'Split series' ) ).toBeDisabled();
	} );

	test( 'splits at the chosen occurrence and reports what moved', async () => {
		mockApiFetch
			.mockResolvedValueOnce( rows )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 4,
				forward_post_id: 99,
				forward_recurring: true,
			} )
			.mockResolvedValueOnce( { id: 42, meta: {} } )
			.mockResolvedValueOnce( rows );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.change( screen.getByLabelText( 'Split from' ), {
			target: { value: '20260917T180000' },
		} );
		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect(
				screen.getByText(
					'4 occurrences moved to a new event. Make your change there.',
				),
			).toBeInTheDocument(),
		);
		// No longer the last call: a successful split is followed by the
		// origin entity refresh.
		expect( mockApiFetch ).toHaveBeenCalledWith( {
			path: '/gatherpress/v1/event/split-series',
			method: 'POST',
			data: { post_id: 42, recurrence_id: '20260917T180000' },
		} );
	} );

	test( 'refreshes the origin entity in the store after a successful split', async () => {
		// The server capped the origin at two occurrences; the stale store
		// still carries the six-count rule the panel seeded from.
		const freshRecord = {
			id: 42,
			meta: {
				gatherpress_recurrence:
					'{"frequency":"weekly","interval":2,"weekdays":[2,4],"end_type":"count","count":2}',
				gatherpress_datetime:
					'{"dateTimeStart":"2026-09-03 18:00:00","dateTimeEnd":"2026-09-03 20:00:00","timezone":"America/New_York"}',
			},
		};

		mockApiFetch
			.mockResolvedValueOnce( rows )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 4,
				forward_post_id: 99,
				forward_recurring: true,
			} )
			.mockResolvedValueOnce( freshRecord )
			.mockResolvedValueOnce( rows );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect(
				screen.getByText(
					'4 occurrences moved to a new event. Make your change there.',
				),
			).toBeInTheDocument(),
		);

		// The origin's entity is refetched with edit context and pushed into
		// the core store, so the panel's meta effect re-parses the capped rule
		// instead of holding the stale pre-split one for the next re-persist.
		await waitFor( () =>
			expect( mockApiFetch ).toHaveBeenCalledWith( {
				path: '/wp/v2/gatherpress_events/42?context=edit',
			} ),
		);
		await waitFor( () =>
			expect( mockReceiveEntityRecords ).toHaveBeenCalledWith(
				'postType',
				'gatherpress_event',
				freshRecord,
			),
		);
	} );

	test( 'does not refresh the origin when nothing was split', async () => {
		mockApiFetch.mockResolvedValueOnce( rows ).mockResolvedValueOnce( {
			split: false,
			reason: 'first_occurrence',
			moved: 0,
			forward_post_id: 0,
			forward_recurring: false,
		} );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect(
				screen.getByText(
					'This is the first occurrence, so applying the change forward is the same as applying it to the whole series. Nothing was split.',
				),
			).toBeInTheDocument(),
		);

		// Nothing changed server side, so nothing is refetched or received.
		expect( mockApiFetch ).toHaveBeenCalledTimes( 2 );
		expect( mockReceiveEntityRecords ).not.toHaveBeenCalled();
	} );

	test( 'keeps the split notice when the origin refresh fails', async () => {
		mockApiFetch
			.mockResolvedValueOnce( rows )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 4,
				forward_post_id: 99,
				forward_recurring: true,
			} )
			.mockRejectedValueOnce( new Error( 'nope' ) )
			.mockResolvedValueOnce( rows );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		// The entity refresh, then the occurrence list on the end of the same
		// chain: a failed entity refresh must not cost the panel the rows the
		// next split is aimed with.
		await waitFor( () =>
			expect( mockApiFetch ).toHaveBeenCalledTimes( 4 ),
		);

		// The split itself succeeded; a failed refresh must not disturb the
		// success handling or surface an error of its own.
		expect(
			screen.getByText(
				'4 occurrences moved to a new event. Make your change there.',
			),
		).toBeInTheDocument();
		expect( mockReceiveEntityRecords ).not.toHaveBeenCalled();
	} );

	test( 'skips the entity refresh when the post type REST base is unresolved', async () => {
		mockGetPostType.mockReturnValue( undefined );

		mockApiFetch
			.mockResolvedValueOnce( rows )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 4,
				forward_post_id: 99,
				forward_recurring: true,
			} )
			.mockResolvedValueOnce( rows );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect(
				screen.getByText(
					'4 occurrences moved to a new event. Make your change there.',
				),
			).toBeInTheDocument(),
		);

		// Two calls plus the occurrence refresh: the entity fetch is the only
		// one an unresolved REST base can skip. The rows still have to be
		// re-read, or the next split is aimed with the owners this one just
		// rewrote.
		expect( mockApiFetch ).toHaveBeenCalledTimes( 3 );
		expect( mockApiFetch ).toHaveBeenLastCalledWith( {
			path: '/gatherpress/v1/event/occurrences?post_id=42',
		} );
		expect( mockReceiveEntityRecords ).not.toHaveBeenCalled();
	} );

	test( 'skips the entity refresh when no post type is resolved at all', async () => {
		mockGetCurrentPostType.mockReturnValue( undefined );

		mockApiFetch
			.mockResolvedValueOnce( rows )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 4,
				forward_post_id: 99,
				forward_recurring: true,
			} )
			.mockResolvedValueOnce( rows );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect(
				screen.getByText(
					'4 occurrences moved to a new event. Make your change there.',
				),
			).toBeInTheDocument(),
		);

		expect( mockApiFetch ).toHaveBeenCalledTimes( 3 );
		expect( mockApiFetch ).toHaveBeenLastCalledWith( {
			path: '/gatherpress/v1/event/occurrences?post_id=42',
		} );
		expect( mockReceiveEntityRecords ).not.toHaveBeenCalled();
	} );

	test( 'builds the refresh path from the post type REST namespace', async () => {
		mockGetPostType.mockReturnValue( {
			rest_base: 'meetups',
			rest_namespace: 'custom/v9',
		} );

		mockApiFetch
			.mockResolvedValueOnce( rows )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 4,
				forward_post_id: 99,
				forward_recurring: true,
			} )
			.mockResolvedValueOnce( { id: 42, meta: {} } )
			.mockResolvedValueOnce( rows );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect( mockApiFetch ).toHaveBeenCalledWith( {
				path: '/custom/v9/meetups/42?context=edit',
			} ),
		);
	} );

	test( 'falls back to the wp/v2 namespace when the post type declares none', async () => {
		mockGetPostType.mockReturnValue( { rest_base: 'gatherpress_events' } );

		mockApiFetch
			.mockResolvedValueOnce( rows )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 4,
				forward_post_id: 99,
				forward_recurring: true,
			} )
			.mockResolvedValueOnce( { id: 42, meta: {} } )
			.mockResolvedValueOnce( rows );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect( mockApiFetch ).toHaveBeenCalledWith( {
				path: '/wp/v2/gatherpress_events/42?context=edit',
			} ),
		);
	} );

	test( 'pushes nothing into the store when the refresh returns no record', async () => {
		mockApiFetch
			.mockResolvedValueOnce( rows )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 4,
				forward_post_id: 99,
				forward_recurring: true,
			} )
			.mockResolvedValueOnce( undefined )
			.mockResolvedValueOnce( rows );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect( mockApiFetch ).toHaveBeenCalledTimes( 4 ),
		);

		expect( mockReceiveEntityRecords ).not.toHaveBeenCalled();
	} );

	test( 'ignores a refreshed record that arrives for a post it has left', async () => {
		mockApiFetch.mockResolvedValueOnce( rows ).mockResolvedValueOnce( {
			split: true,
			reason: '',
			moved: 4,
			forward_post_id: 99,
			forward_recurring: true,
		} );

		const { rerender } = render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		let resolveRecord;

		mockApiFetch.mockReturnValueOnce(
			new Promise( ( resolve ) => {
				resolveRecord = resolve;
			} ),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect( mockApiFetch ).toHaveBeenCalledTimes( 3 ),
		);

		mockApiFetch.mockResolvedValueOnce( [] );

		rerender( <ApplyScope postId={ 99 } /> );

		await act( async () => {
			resolveRecord( { id: 42, meta: {} } );
		} );

		expect( mockReceiveEntityRecords ).not.toHaveBeenCalled();
	} );

	test( 'reports the plain-event degradation when one date moves', async () => {
		mockApiFetch
			.mockResolvedValueOnce( rows )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 1,
				forward_post_id: 99,
				forward_recurring: false,
			} )
			.mockResolvedValueOnce( { id: 42, meta: {} } )
			.mockResolvedValueOnce( rows );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect(
				screen.getByText(
					'1 occurrence moved to a new event. Make your change there.' +
						' It holds a single date, so it is a plain non-recurring event.',
				),
			).toBeInTheDocument(),
		);
	} );

	test( 'reports the retroactive degradation from the first occurrence', async () => {
		mockApiFetch
			.mockResolvedValueOnce( rows )
			.mockResolvedValueOnce( {
				split: false,
				reason: 'first_occurrence',
				moved: 0,
				forward_post_id: 0,
				forward_recurring: false,
			} );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect(
				screen.getByText(
					'This is the first occurrence, so applying the change forward is the same as applying it to the whole series. Nothing was split.',
				),
			).toBeInTheDocument(),
		);
	} );

	test( 'renders the message a refused split names, not a generic failure', async () => {
		// The shape `apiFetch` rejects with for a REST error response: the
		// server's own `WP_Error` message, e.g. the named refusal of a split
		// past the maximum rule count.
		mockApiFetch.mockResolvedValueOnce( rows ).mockRejectedValueOnce( {
			code: 'gatherpress_split_too_long',
			message:
				'A series cannot be split past its first 730 dates. Choose an earlier date to split from.',
		} );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect(
				screen.getByText(
					'A series cannot be split past its first 730 dates. Choose an earlier date to split from.',
				),
			).toBeInTheDocument(),
		);
		expect(
			screen.queryByText( 'Could not split this series.' ),
		).not.toBeInTheDocument();
	} );

	test( 'falls back to a generic message when the failure names none', async () => {
		mockApiFetch.mockResolvedValueOnce( rows ).mockRejectedValueOnce( {} );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect(
				screen.getByText( 'Could not split this series.' ),
			).toBeInTheDocument(),
		);
	} );

	test( 'cannot split until an occurrence is chosen', async () => {
		mockApiFetch.mockResolvedValue( [] );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () => expect( mockApiFetch ).toHaveBeenCalled() );
		expect( screen.getByText( 'Split series' ) ).toBeDisabled();
	} );
	test( 'defaults past the first listed occurrence so the first click is never a guaranteed no-op', async () => {
		mockApiFetch.mockResolvedValue( rows );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		// rows[ 0 ] is the series' first date whenever the series has not
		// started, and splitting there degrades to "Nothing was split", so it
		// is never the default.
		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toHaveValue(
				'20260917T180000',
			),
		);
		expect(
			screen.queryByText(
				'If this is the series\u2019 first date, splitting here applies the change to the whole series.',
			),
		).not.toBeInTheDocument();
	} );

	test( 'explains the degradation before the click when only one occurrence is listed', async () => {
		mockApiFetch.mockResolvedValue( [ rows[ 0 ] ] );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toHaveValue(
				'20260903T180000',
			),
		);
		expect(
			screen.getByText(
				'If this is the series\u2019 first date, splitting here applies the change to the whole series.',
			),
		).toBeInTheDocument();
	} );

	test( 'refuses to split while the editor holds unsaved changes', async () => {
		mockIsEditedPostDirty.mockReturnValue( true );
		mockApiFetch.mockResolvedValue( rows );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		expect( screen.getByText( 'Split series' ) ).toBeDisabled();
		expect(
			screen.getByText(
				'Save or discard your current changes before splitting.',
			),
		).toBeInTheDocument();
	} );

	test( 'allows the split once the editor is clean', async () => {
		mockApiFetch.mockResolvedValue( rows );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		expect( screen.getByText( 'Split series' ) ).not.toBeDisabled();
		expect(
			screen.queryByText(
				'Save or discard your current changes before splitting.',
			),
		).not.toBeInTheDocument();
	} );

	test( 'links to the forward event once a split completes', async () => {
		mockApiFetch
			.mockResolvedValueOnce( rows )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 4,
				forward_post_id: 99,
				forward_recurring: true,
			} )
			.mockResolvedValueOnce( { id: 42, meta: {} } )
			.mockResolvedValueOnce( rows );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect( screen.getByText( 'Edit the new event' ) ).toHaveAttribute(
				'href',
				'post.php?post=99&action=edit',
			),
		);
	} );

	test( 'offers no forward link when nothing was split', async () => {
		mockApiFetch.mockResolvedValueOnce( rows ).mockResolvedValueOnce( {
			split: false,
			reason: 'first_occurrence',
			moved: 0,
			forward_post_id: 0,
			forward_recurring: false,
		} );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect(
				screen.getByText(
					'This is the first occurrence, so applying the change forward is the same as applying it to the whole series. Nothing was split.',
				),
			).toBeInTheDocument(),
		);
		expect(
			screen.queryByText( 'Edit the new event' ),
		).not.toBeInTheDocument();
	} );
	test( 'offers no forward link when the response carries no forward post id', async () => {
		mockApiFetch
			.mockResolvedValueOnce( rows )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 4,
				forward_recurring: true,
			} )
			.mockResolvedValueOnce( { id: 42, meta: {} } )
			.mockResolvedValueOnce( rows );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect(
				screen.getByText(
					'4 occurrences moved to a new event. Make your change there.',
				),
			).toBeInTheDocument(),
		);
		expect(
			screen.queryByText( 'Edit the new event' ),
		).not.toBeInTheDocument();
	} );

	test( 'clears every post-scoped piece of state when the post changes', async () => {
		// The panel survives navigation from event A to event B. Until B's list
		// arrives, A's occurrences, A's split notice and A's "Edit the new
		// event" link would otherwise stay on screen and stay actionable, and
		// the enabled button would submit B's post with A's occurrence.
		mockApiFetch.mockResolvedValueOnce( rows );

		const { rerender } = render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toHaveValue(
				'20260917T180000',
			),
		);

		mockApiFetch
			.mockResolvedValueOnce( {
				split: true,
				moved: 1,
				forward_post_id: 77,
				forward_recurring: true,
			} )
			.mockResolvedValueOnce( { id: 42, meta: {} } )
			.mockResolvedValueOnce( rows );

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect( screen.getByText( 'Edit the new event' ) ).toBeInTheDocument(),
		);
		// The split's whole chain has to have drained before the queue below is
		// primed, or the post-split occurrence refresh consumes the response
		// this test queues for post 99's own list request.
		await waitFor( () =>
			expect( mockApiFetch ).toHaveBeenCalledTimes( 4 ),
		);

		let resolveNext;

		mockApiFetch.mockReturnValueOnce(
			new Promise( ( resolve ) => {
				resolveNext = resolve;
			} ),
		);

		rerender( <ApplyScope postId={ 99 } /> );

		expect( screen.queryByText( 'Edit the new event' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByText(
				'1 occurrence moved to a new event. Make your change there.',
			),
		).not.toBeInTheDocument();
		expect( screen.getByLabelText( 'Split from' ).children ).toHaveLength( 0 );
		expect( screen.getByText( 'Split series' ) ).toBeDisabled();

		await act( async () => {
			resolveNext( [] );
		} );
	} );

	test( 'ignores an occurrence list that arrives for a post it has left', async () => {
		let resolveStale;

		mockApiFetch.mockReturnValueOnce(
			new Promise( ( resolve ) => {
				resolveStale = resolve;
			} ),
		);

		const { rerender } = render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		mockApiFetch.mockResolvedValueOnce( [] );

		rerender( <ApplyScope postId={ 99 } /> );

		await act( async () => {
			resolveStale( rows );
		} );

		expect( screen.getByLabelText( 'Split from' ).children ).toHaveLength( 0 );
	} );

	test( 'ignores a stale occurrence rejection for a post it has left', async () => {
		let rejectStale;

		const stale = new Promise( ( resolve, reject ) => {
			rejectStale = reject;
		} );

		mockApiFetch.mockReturnValueOnce( stale );

		const { rerender } = render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		mockApiFetch.mockResolvedValueOnce( rows );

		rerender( <ApplyScope postId={ 99 } /> );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toHaveValue(
				'20260917T180000',
			),
		);

		await act( async () => {
			rejectStale( new Error( 'nope' ) );
			await stale.catch( () => {} );
		} );

		expect( screen.getByLabelText( 'Split from' ) ).toHaveValue(
			'20260917T180000',
		);
	} );

	test( 'ignores a split result that arrives for a post it has left', async () => {
		mockApiFetch.mockResolvedValueOnce( rows );

		const { rerender } = render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toHaveValue(
				'20260917T180000',
			),
		);

		let resolveSplit;

		mockApiFetch.mockReturnValueOnce(
			new Promise( ( resolve ) => {
				resolveSplit = resolve;
			} ),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		mockApiFetch.mockResolvedValueOnce( [] );

		rerender( <ApplyScope postId={ 99 } /> );

		await act( async () => {
			resolveSplit( {
				split: true,
				moved: 4,
				forward_post_id: 77,
				forward_recurring: true,
			} );
		} );

		expect( screen.queryByText( 'Edit the new event' ) ).not.toBeInTheDocument();
	} );

	test( 'submits the post that owns the chosen occurrence, not the post being edited', async () => {
		// A series already split once holds later occurrences on a sibling post.
		// Submitting the post open in the editor would ask the route to split a
		// post that does not own the chosen date.
		mockApiFetch.mockResolvedValueOnce( [
			occurrence( '20260903T180000', '2026-09-03 18:00:00' ),
			{
				...occurrence( '20260917T180000', '2026-09-17 18:00:00' ),
				series_post_id: 815,
			},
		] );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toHaveValue(
				'20260917T180000',
			),
		);

		mockApiFetch
			.mockResolvedValueOnce( {
				split: true,
				moved: 1,
				forward_post_id: 77,
				forward_recurring: true,
			} )
			.mockResolvedValueOnce( { id: 42, meta: {} } )
			.mockResolvedValueOnce( [] );

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect( mockApiFetch ).toHaveBeenCalledWith( {
				path: '/gatherpress/v1/event/split-series',
				method: 'POST',
				data: { post_id: 815, recurrence_id: '20260917T180000' },
			} ),
		);
	} );

	test( 'falls back to the post being edited when the row names no owner', async () => {
		// A row from an older response, or from a filter that widened the series
		// without stamping an owner. The post open in the editor is the only
		// other answer available, and the route resolves the real owner anyway.
		mockApiFetch.mockResolvedValueOnce( [
			{
				recurrence_id: '20260903T180000',
				datetime_start: '2026-09-03 18:00:00',
				status: 'scheduled',
			},
		] );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toHaveValue(
				'20260903T180000',
			),
		);

		mockApiFetch.mockResolvedValueOnce( {
			split: false,
			moved: 0,
			forward_post_id: 0,
		} );

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect( mockApiFetch ).toHaveBeenCalledWith( {
				path: '/gatherpress/v1/event/split-series',
				method: 'POST',
				data: { post_id: 42, recurrence_id: '20260903T180000' },
			} ),
		);
	} );

	test( 'ignores a failed split that lands after the post has changed', async () => {
		mockApiFetch.mockResolvedValueOnce( rows );

		const { rerender } = render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toHaveValue(
				'20260917T180000',
			),
		);

		let rejectSplit;

		const pending = new Promise( ( resolve, reject ) => {
			rejectSplit = reject;
		} );

		mockApiFetch.mockReturnValueOnce( pending );

		fireEvent.click( screen.getByText( 'Split series' ) );

		mockApiFetch.mockResolvedValueOnce( [] );

		rerender( <ApplyScope postId={ 99 } /> );

		await act( async () => {
			rejectSplit( new Error( 'nope' ) );
			await pending.catch( () => {} );
		} );

		expect(
			screen.queryByText( 'Could not split this series.' ),
		).not.toBeInTheDocument();
		expect( screen.queryByText( 'nope' ) ).not.toBeInTheDocument();
	} );

	test( 'aims a second split at the post the first split gave the date to', async () => {
		// The defect this test exists for: each row names the post that owns
		// its date, the split rewrites those owners, and the panel used to keep
		// the rows it read before the split. A second split in the same session
		// was therefore submitted against the post that no longer produces the
		// chosen date.
		mockApiFetch
			.mockResolvedValueOnce( threeRows )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 2,
				forward_post_id: 99,
				forward_recurring: true,
			} )
			.mockResolvedValueOnce( { id: 42, meta: {} } )
			.mockResolvedValueOnce( threeRowsAfterFirstSplit )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 1,
				forward_post_id: 123,
				forward_recurring: false,
			} )
			.mockResolvedValueOnce( { id: 42, meta: {} } )
			.mockResolvedValueOnce( threeRowsAfterFirstSplit );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toHaveValue(
				'20260917T180000',
			),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		// The whole post-split chain: the entity refresh, then the occurrence
		// list on the end of it.
		await waitFor( () =>
			expect( mockApiFetch ).toHaveBeenCalledTimes( 4 ),
		);
		expect( mockApiFetch ).toHaveBeenNthCalledWith( 2, {
			path: '/gatherpress/v1/event/split-series',
			method: 'POST',
			data: { post_id: 42, recurrence_id: '20260917T180000' },
		} );

		// Neither of the two pieces of state the naive fix would have cost the
		// organizer: the notice the split just wrote, and the occurrence they
		// chose. Re-running the list effect would have cleared the first and
		// reset the second to the default one row past the top.
		expect(
			screen.getByText(
				'2 occurrences moved to a new event. Make your change there.',
			),
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Split from' ) ).toHaveValue(
			'20260917T180000',
		);

		fireEvent.change( screen.getByLabelText( 'Split from' ), {
			target: { value: '20261001T180000' },
		} );
		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect( mockApiFetch ).toHaveBeenNthCalledWith( 5, {
				path: '/gatherpress/v1/event/split-series',
				method: 'POST',
				data: { post_id: 99, recurrence_id: '20261001T180000' },
			} ),
		);
	} );

	test( 'keeps the split notice when the occurrence refresh fails', async () => {
		mockApiFetch
			.mockResolvedValueOnce( rows )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 4,
				forward_post_id: 99,
				forward_recurring: true,
			} )
			.mockResolvedValueOnce( { id: 42, meta: {} } )
			.mockRejectedValueOnce( new Error( 'nope' ) );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect( mockApiFetch ).toHaveBeenCalledTimes( 4 ),
		);

		// The split succeeded. A list that could not be re-read leaves the rows
		// as they were rather than emptying the chooser or reporting a failure
		// the organizer cannot act on.
		expect(
			screen.getByText(
				'4 occurrences moved to a new event. Make your change there.',
			),
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Split from' ).children ).toHaveLength( 2 );
		expect( screen.getByText( 'Split series' ) ).not.toBeDisabled();
	} );

	test( 'empties the chooser when the refreshed list carries no rows at all', async () => {
		mockApiFetch
			.mockResolvedValueOnce( rows )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 4,
				forward_post_id: 99,
				forward_recurring: true,
			} )
			.mockResolvedValueOnce( { id: 42, meta: {} } )
			.mockResolvedValueOnce( undefined );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ).children ).toHaveLength(
				0,
			),
		);
		expect(
			screen.getByText(
				'4 occurrences moved to a new event. Make your change there.',
			),
		).toBeInTheDocument();
	} );

	test( 'ignores a refreshed occurrence list that arrives for a post it has left', async () => {
		let resolveRows;

		mockApiFetch
			.mockResolvedValueOnce( rows )
			.mockResolvedValueOnce( {
				split: true,
				reason: '',
				moved: 4,
				forward_post_id: 99,
				forward_recurring: true,
			} )
			.mockResolvedValueOnce( { id: 42, meta: {} } )
			.mockReturnValueOnce(
				new Promise( ( resolve ) => {
					resolveRows = resolve;
				} ),
			);

		const { rerender } = render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect( mockApiFetch ).toHaveBeenCalledTimes( 4 ),
		);

		mockApiFetch.mockResolvedValueOnce( [] );

		rerender( <ApplyScope postId={ 99 } /> );

		await act( async () => {
			resolveRows( rows );
		} );

		// Post 42's rows landing on post 99's panel would offer occurrences the
		// post being edited does not own, and the next split would be aimed
		// with owners from a different series.
		expect( screen.getByLabelText( 'Split from' ).children ).toHaveLength( 0 );
	} );

	test( 'says the series is already split rather than claiming the whole series', async () => {
		// The refusal for index 0 of a *fragment*. The dropdown spans both
		// halves of a series that has already been split, so this date is only
		// the first that its own post owns; the rest of the series is on a
		// sibling event, and telling the organizer the change applies to the
		// whole series would be false about the dates on the other side.
		mockApiFetch.mockResolvedValueOnce( rows ).mockResolvedValueOnce( {
			split: false,
			reason: 'fragment_first_occurrence',
			moved: 0,
			forward_post_id: 0,
			forward_recurring: false,
		} );

		render( <ApplyScope postId={ 42 } /> );

		fireEvent.change( screen.getByLabelText( 'Applying changes' ), {
			target: { value: 'forward' },
		} );

		await waitFor( () =>
			expect( screen.getByLabelText( 'Split from' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByText( 'Split series' ) );

		await waitFor( () =>
			expect(
				screen.getByText(
					'This event already starts at this date, so there is nothing here to split off: your change applies to every date this event holds. The rest of the series is on another event. Nothing was split.',
				),
			).toBeInTheDocument(),
		);
		expect(
			screen.queryByText(
				'This is the first occurrence, so applying the change forward is the same as applying it to the whole series. Nothing was split.',
			),
		).not.toBeInTheDocument();
	} );
} );
