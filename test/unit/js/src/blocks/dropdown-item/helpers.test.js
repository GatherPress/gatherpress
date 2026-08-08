/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import {
	RSVP_FILTER_CLASS_MAP,
	getLabelText,
	getResolvedLabelPreview,
	getRsvpFilterKey,
} from '@src/blocks/dropdown-item/helpers';

describe( 'getRsvpFilterKey', () => {
	it( 'maps each seeded RSVP filter class to its response key', () => {
		Object.entries( RSVP_FILTER_CLASS_MAP ).forEach(
			( [ className, key ] ) => {
				expect( getRsvpFilterKey( className ) ).toBe( key );
			}
		);
	} );

	it( 'finds the filter class among unrelated classes', () => {
		expect(
			getRsvpFilterKey( 'has-large-font-size gatherpress--is-waiting-list' )
		).toBe( 'waiting_list' );
	} );

	it( 'returns null for a dropdown item that is not an RSVP filter', () => {
		expect( getRsvpFilterKey( 'is-style-outline' ) ).toBeNull();
	} );

	it( 'returns null for undefined, null, and empty class names', () => {
		expect( getRsvpFilterKey( undefined ) ).toBeNull();
		expect( getRsvpFilterKey( null ) ).toBeNull();
		expect( getRsvpFilterKey( '' ) ).toBeNull();
	} );
} );

describe( 'getLabelText', () => {
	it( 'strips the anchor markup the block stores around the label', () => {
		expect( getLabelText( '<a href="#">Attending (%d)</a>' ) ).toBe(
			'Attending (%d)'
		);
	} );

	it( 'returns an empty string for undefined and null', () => {
		expect( getLabelText( undefined ) ).toBe( '' );
		expect( getLabelText( null ) ).toBe( '' );
	} );
} );

describe( 'getResolvedLabelPreview', () => {
	it( 'substitutes the count into the label', () => {
		expect(
			getResolvedLabelPreview( '<a href="#">Attending (%d)</a>', 3 )
		).toBe( 'Attending (3)' );
	} );

	it( 'falls back to zero when the count is missing', () => {
		expect(
			getResolvedLabelPreview( '<a href="#">Attending (%d)</a>', null )
		).toBe( 'Attending (0)' );
		expect(
			getResolvedLabelPreview(
				'<a href="#">Attending (%d)</a>',
				undefined
			)
		).toBe( 'Attending (0)' );
	} );

	it( 'returns null when the author has removed the placeholder', () => {
		expect(
			getResolvedLabelPreview( '<a href="#">Who is coming</a>', 3 )
		).toBeNull();
	} );

	it( 'returns null when the label is empty', () => {
		expect( getResolvedLabelPreview( '<a href="#"></a>', 3 ) ).toBeNull();
		expect( getResolvedLabelPreview( '', 3 ) ).toBeNull();
	} );
} );
