/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';

/**
 * Internal dependencies
 */
import {
	formatDoorsTime,
	resolveDoorsTimes,
} from '@src/blocks/event-doors/helpers';
import { findEventPostById } from '@src/helpers/event';

jest.mock( '@src/helpers/event', () => ( {
	findEventPostById: jest.fn(),
} ) );

jest.mock( '@src/helpers/editor-settings', () => ( {
	getFromSettings: jest.fn( () => 'g:i a' ),
} ) );

const doors = { gatherpress_doors_open: '18:30', gatherpress_doors_close: '23:00' };

/**
 * Builds a select function for the given editor and entity state.
 *
 * @param {Object} root0               State to expose.
 * @param {number} root0.currentPostId The post being edited.
 * @param {Object} root0.editedMeta    The edited post's meta.
 * @param {Object} root0.entity        The entity record getEntityRecord returns.
 *
 * @return {Function} The select function.
 */
const makeSelect = ( { currentPostId = 1, editedMeta, entity } = {} ) =>
	( store ) =>
		'core/editor' === store
			? {
				getCurrentPostId: () => currentPostId,
				getEditedPostAttribute: () => editedMeta,
			}
			: { getEntityRecord: () => entity };

describe( 'resolveDoorsTimes', () => {
	it( 'reads the edited meta for the post being edited', () => {
		expect(
			resolveDoorsTimes( makeSelect( { editedMeta: doors } ), 'gatherpress_event', 1, false ),
		).toEqual( { doorsOpen: '18:30', doorsClose: '23:00' } );
	} );

	it( 'reads the override target for a post ID override', () => {
		findEventPostById.mockReturnValueOnce( { meta: doors } );

		expect( resolveDoorsTimes( makeSelect(), 'page', 7, true ) ).toEqual( {
			doorsOpen: '18:30',
			doorsClose: '23:00',
		} );
	} );

	it( 'reads the queried post inside a Query Loop', () => {
		expect(
			resolveDoorsTimes(
				makeSelect( { entity: { meta: { gatherpress_doors_open: '17:00' } } } ),
				'gatherpress_event',
				9,
				false,
			),
		).toEqual( { doorsOpen: '17:00', doorsClose: '' } );
	} );

	it( 'returns empty times when there is nothing to read', () => {
		const empty = { doorsOpen: '', doorsClose: '' };

		expect( resolveDoorsTimes( makeSelect(), 'gatherpress_event', null, false ) ).toEqual( empty );
		expect( resolveDoorsTimes( makeSelect(), undefined, 9, false ) ).toEqual( empty );
		expect( resolveDoorsTimes( makeSelect(), 'gatherpress_event', 9, false ) ).toEqual( empty );
	} );
} );

describe( 'formatDoorsTime', () => {
	it( 'formats a time with the site time format', () => {
		expect( formatDoorsTime( '18:30' ) ).toBe( '6:30 pm' );
	} );
} );
