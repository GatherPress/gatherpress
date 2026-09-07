/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import { buildFilterUrl } from '@src/rsvp-admin/filters';

const SCREEN = 'https://example.test/wp-admin/edit.php?post_type=gatherpress_event&page=gatherpress_rsvp';

describe( 'buildFilterUrl', () => {
	it( 'carries the chosen event', () => {
		expect( buildFilterUrl( SCREEN, 11, [] ) ).toContain( 'post_id=11' );
	} );

	it( 'carries the chosen responses as one parameter', () => {
		expect(
			buildFilterUrl( SCREEN, null, [ 'attending', 'waiting_list' ] )
		).toContain( 'response=attending%2Cwaiting_list' );
	} );

	it( 'carries both filters together', () => {
		const url = buildFilterUrl( SCREEN, 11, [ 'attending' ] );

		expect( url ).toContain( 'post_id=11' );
		expect( url ).toContain( 'response=attending' );
	} );

	it( 'omits an empty event rather than sending it blank', () => {
		expect( buildFilterUrl( SCREEN, null, [] ) ).not.toContain( 'post_id' );
	} );

	it( 'omits empty responses rather than sending them blank', () => {
		expect( buildFilterUrl( SCREEN, null, [] ) ).not.toContain( 'response' );
	} );

	it( 'drops a filter that was previously applied', () => {
		const filtered = `${ SCREEN }&post_id=11&response=attending`;

		expect( buildFilterUrl( filtered, null, [] ) ).not.toContain(
			'post_id'
		);
		expect( buildFilterUrl( filtered, null, [] ) ).not.toContain(
			'response'
		);
	} );

	it( 'resets paging, which rarely survives a narrower result', () => {
		expect( buildFilterUrl( `${ SCREEN }&paged=4`, 11, [] ) ).not.toContain(
			'paged'
		);
	} );

	it( 'keeps the parameters that identify the screen', () => {
		const url = buildFilterUrl( `${ SCREEN }&paged=4`, 11, [] );

		expect( url ).toContain( 'post_type=gatherpress_event' );
		expect( url ).toContain( 'page=gatherpress_rsvp' );
	} );

	it( 'keeps an unrelated parameter such as the search term', () => {
		expect( buildFilterUrl( `${ SCREEN }&s=ada`, 11, [] ) ).toContain(
			's=ada'
		);
	} );
} );
