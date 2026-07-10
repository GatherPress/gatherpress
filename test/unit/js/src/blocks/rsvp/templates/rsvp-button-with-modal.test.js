/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import RSVP_BUTTON_WITH_MODAL_TEMPLATES from '@src/blocks/rsvp/templates/rsvp-button-with-modal';
import ATTENDING from '@src/blocks/rsvp/templates/attending';
import NO_STATUS from '@src/blocks/rsvp/templates/no-status';
import NOT_ATTENDING from '@src/blocks/rsvp/templates/not-attending';
import WAITING_LIST from '@src/blocks/rsvp/templates/waiting-list';
import PAST from '@src/blocks/rsvp/templates/past';

describe( 'RSVP Button with Modal templates', () => {
	it( 'exports an object with all template keys', () => {
		expect( RSVP_BUTTON_WITH_MODAL_TEMPLATES ).toBeDefined();
		expect( typeof RSVP_BUTTON_WITH_MODAL_TEMPLATES ).toBe( 'object' );
		expect( RSVP_BUTTON_WITH_MODAL_TEMPLATES ).toHaveProperty( 'no_status' );
		expect( RSVP_BUTTON_WITH_MODAL_TEMPLATES ).toHaveProperty( 'attending' );
		expect( RSVP_BUTTON_WITH_MODAL_TEMPLATES ).toHaveProperty( 'waiting_list' );
		expect( RSVP_BUTTON_WITH_MODAL_TEMPLATES ).toHaveProperty( 'not_attending' );
		expect( RSVP_BUTTON_WITH_MODAL_TEMPLATES ).toHaveProperty( 'past' );
	} );

	it( 'maps no_status key to NO_STATUS template', () => {
		expect( RSVP_BUTTON_WITH_MODAL_TEMPLATES.no_status ).toBe( NO_STATUS );
	} );

	it( 'maps attending key to ATTENDING template', () => {
		expect( RSVP_BUTTON_WITH_MODAL_TEMPLATES.attending ).toBe( ATTENDING );
	} );

	it( 'maps waiting_list key to WAITING_LIST template', () => {
		expect( RSVP_BUTTON_WITH_MODAL_TEMPLATES.waiting_list ).toBe( WAITING_LIST );
	} );

	it( 'maps not_attending key to NOT_ATTENDING template', () => {
		expect( RSVP_BUTTON_WITH_MODAL_TEMPLATES.not_attending ).toBe( NOT_ATTENDING );
	} );

	it( 'maps past key to PAST template', () => {
		expect( RSVP_BUTTON_WITH_MODAL_TEMPLATES.past ).toBe( PAST );
	} );

	it( 'has exactly 5 template keys', () => {
		const keys = Object.keys( RSVP_BUTTON_WITH_MODAL_TEMPLATES );

		expect( keys.length ).toBe( 5 );
	} );

	it( 'all template values are arrays', () => {
		Object.values( RSVP_BUTTON_WITH_MODAL_TEMPLATES ).forEach( ( template ) => {
			expect( Array.isArray( template ) ).toBe( true );
		} );
	} );

	it( 'all templates contain block configuration objects', () => {
		Object.values( RSVP_BUTTON_WITH_MODAL_TEMPLATES ).forEach( ( template ) => {
			// Each template should be a non-empty array.
			expect( template.length ).toBeGreaterThan( 0 );

			// Each item in template should be an array (block configuration).
			template.forEach( ( block ) => {
				expect( Array.isArray( block ) ).toBe( true );
				// First element should be block name (string).
				expect( typeof block[ 0 ] ).toBe( 'string' );
			} );
		} );
	} );
} );
