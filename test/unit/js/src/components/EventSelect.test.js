/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';
import { act, render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies
 */
import { dispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import EventSelect, {
	buildEventQuery,
	toEventOptions,
} from '@src/components/EventSelect';

/**
 * Builds an event record shaped like the REST response.
 *
 * @param {number} id    Post ID.
 * @param {string} title Rendered title.
 *
 * @return {Object} The record.
 */
const event = ( id, title ) => ( { id, title: { rendered: title } } );

describe( 'buildEventQuery', () => {
	it( 'requests unpublished events, which is what an organizer filters by', () => {
		expect( buildEventQuery( '' ) ).toMatchObject( {
			context: 'edit',
			status: 'any',
		} );
	} );

	it( 'asks for past events as well as upcoming', () => {
		// The endpoint filters to upcoming when this is absent.
		expect( buildEventQuery( '' ).gatherpress_event_query ).toBe( 'all' );
	} );

	it( 'caps the result count so a large site stays usable', () => {
		expect( buildEventQuery( '' ).per_page ).toBe( 10 );
	} );

	it( 'orders by relevance while searching', () => {
		expect( buildEventQuery( 'picnic' ).orderby ).toBe( 'relevance' );
	} );

	it( 'orders by date when there is no search term', () => {
		// `relevance` needs something to be relevant to.
		expect( buildEventQuery( '' ).orderby ).toBe( 'date' );
	} );

	it( 'passes the search term through', () => {
		expect( buildEventQuery( 'picnic' ).search ).toBe( 'picnic' );
	} );
} );

describe( 'toEventOptions', () => {
	it( 'maps records to combobox options', () => {
		expect( toEventOptions( [ event( 1, 'Summer Picnic' ) ], null ) ).toEqual(
			[ { value: 1, label: 'Summer Picnic' } ]
		);
	} );

	it( 'decodes entities in titles', () => {
		expect(
			toEventOptions( [ event( 1, 'Bob &amp; Alice' ) ], null )[ 0 ].label
		).toBe( 'Bob & Alice' );
	} );

	it( 'falls back to the ID when an event has no title', () => {
		expect( toEventOptions( [ { id: 7, title: {} } ], null )[ 0 ].label ).toBe(
			'#7'
		);
	} );

	it( 'falls back to the ID when the title key is absent entirely', () => {
		expect( toEventOptions( [ { id: 8 } ], null )[ 0 ].label ).toBe( '#8' );
	} );

	it( 'keeps the selected event in the list when the search excludes it', () => {
		expect(
			toEventOptions(
				[ event( 2, 'Autumn Walk' ) ],
				event( 9, 'Winter Social' )
			)
		).toEqual( [
			{ value: 9, label: 'Winter Social' },
			{ value: 2, label: 'Autumn Walk' },
		] );
	} );

	it( 'does not duplicate the selected event when the search includes it', () => {
		const selected = event( 2, 'Autumn Walk' );

		expect( toEventOptions( [ selected ], selected ) ).toHaveLength( 1 );
	} );

	it( 'returns an empty list when there are no records', () => {
		expect( toEventOptions( [], null ) ).toEqual( [] );
	} );

	it( 'tolerates records being undefined before the first response', () => {
		expect( toEventOptions( undefined, null ) ).toEqual( [] );
	} );

	it( 'returns the selection alone when the search found nothing', () => {
		expect( toEventOptions( [], event( 9, 'Winter Social' ) ) ).toEqual( [
			{ value: 9, label: 'Winter Social' },
		] );
	} );
} );

describe( 'EventSelect', () => {
	const POST_TYPE = 'gatherpress_event';

	/**
	 * Puts an event in the data store so the picker can resolve it.
	 *
	 * @param {number} id    Post ID.
	 * @param {string} title Rendered title.
	 *
	 * @return {void}
	 */
	const seedEvent = ( id, title ) => {
		dispatch( coreStore ).addEntities( [
			{
				kind: 'postType',
				name: POST_TYPE,
				baseURL: `/wp/v2/${ POST_TYPE }`,
				key: 'id',
			},
		] );
		dispatch( coreStore ).receiveEntityRecords( 'postType', POST_TYPE, [
			{ id, title: { rendered: title } },
		] );
	};

	/**
	 * Renders and lets React settle.
	 *
	 * The control resolves its options after the first paint; unwaited, that
	 * update lands outside `act()`.
	 *
	 * @param {JSX.Element} ui The element to render.
	 *
	 * @return {Promise<void>} Resolves once React has settled.
	 */
	const renderSettled = async ( ui ) => {
		await act( async () => {
			render( ui );
		} );
	};

	it( 'accepts a single post type as a bare string', async () => {
		await renderSettled(
			<EventSelect
				postTypes="gatherpress_event"
				value={ null }
				onChange={ () => {} }
			/>
		);

		expect( screen.getByRole( 'combobox' ) ).toBeInTheDocument();
	} );

	it( 'labels itself when the caller supplies no label', async () => {
		await renderSettled(
			<EventSelect
				postTypes={ [ 'gatherpress_event' ] }
				value={ null }
				onChange={ () => {} }
			/>
		);

		expect( screen.getByLabelText( 'Event' ) ).toBeVisible();
	} );

	it( 'names the selected event rather than leaving the box empty', async () => {
		// The selection arrives as a bare ID, so the label has to be looked
		// up; a filtered screen would otherwise show an empty control.
		seedEvent( 11, 'Summer Picnic' );

		await renderSettled(
			<EventSelect
				postTypes={ [ POST_TYPE ] }
				value={ 11 }
				onChange={ () => {} }
			/>
		);

		expect( screen.getByRole( 'combobox' ) ).toHaveValue( 'Summer Picnic' );
	} );

	it( 'hides the label from view when asked', async () => {
		await renderSettled(
			<EventSelect
				postTypes={ [ 'gatherpress_event' ] }
				label="Filter by event"
				hideLabelFromVision
				value={ null }
				onChange={ () => {} }
			/>
		);

		expect( screen.getByLabelText( 'Filter by event' ) ).toBeInTheDocument();
	} );
} );
