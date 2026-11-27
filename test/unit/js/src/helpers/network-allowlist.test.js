/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import {
	initNetworkAllowlist,
	toggleAllowlist,
} from '@src/helpers/network-allowlist';

/**
 * Render the allowlist markup the Network tab outputs.
 *
 * @return {HTMLElement} The allowlist form.
 */
function renderAllowlist() {
	const group = ( name ) => `
		<div class="gatherpress-allowlist__group">
			<h3>${ name }
				<button type="button" data-gatherpress_select="all">select all</button>
				<button type="button" data-gatherpress_select="none">deselect all</button>
			</h3>
			<ul class="gatherpress-allowlist__options">
				<li><input type="checkbox" value="${ name }_one" /></li>
				<li><input type="checkbox" value="${ name }_two" /></li>
			</ul>
		</div>`;

	document.body.innerHTML = `
		<form data-gatherpress_component_name="network-allowlist">
			<input type="checkbox" class="enable" />
			<p>
				<button type="button" data-gatherpress_select="all">Select all</button>
				<button type="button" data-gatherpress_select="none">Deselect all</button>
			</p>
			${ group( 'events' ) }
			${ group( 'venues' ) }
		</form>
	`;

	return document.querySelector( 'form' );
}

/**
 * List the checked inherited settings.
 *
 * @param {HTMLElement} root The allowlist form.
 *
 * @return {string[]} The checked values.
 */
function checked( root ) {
	return Array.from(
		root.querySelectorAll( '.gatherpress-allowlist__options :checked' ),
	).map( ( input ) => input.value );
}

describe( 'toggleAllowlist', () => {
	it( 'checks and unchecks every inherited setting from the top links', () => {
		const root = renderAllowlist();
		const [ all, none ] = root.querySelectorAll( 'p [data-gatherpress_select]' );

		toggleAllowlist( root, all );

		expect( checked( root ) ).toEqual( [
			'events_one',
			'events_two',
			'venues_one',
			'venues_two',
		] );
		expect( root.querySelector( '.enable' ).checked ).toBe( false );

		toggleAllowlist( root, none );

		expect( checked( root ) ).toEqual( [] );
	} );

	it( 'limits a group link to its own group', () => {
		const root = renderAllowlist();
		const venues = root.querySelectorAll( '.gatherpress-allowlist__group' )[ 1 ];

		toggleAllowlist( root, venues.querySelector( '[data-gatherpress_select="all"]' ) );

		expect( checked( root ) ).toEqual( [ 'venues_one', 'venues_two' ] );
	} );
} );

describe( 'initNetworkAllowlist', () => {
	it( 'does nothing on pages without the allowlist', () => {
		document.body.innerHTML = '<div></div>';

		expect( () => initNetworkAllowlist() ).not.toThrow();
	} );

	it( 'wires the links to toggle the checkboxes', () => {
		const root = renderAllowlist();

		initNetworkAllowlist();
		root.querySelector( 'p [data-gatherpress_select="all"]' ).click();

		expect( checked( root ) ).toHaveLength( 4 );
	} );
} );
