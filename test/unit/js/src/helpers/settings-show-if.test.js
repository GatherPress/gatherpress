/**
 * External dependencies
 */
import { describe, expect, it, beforeEach } from '@jest/globals';

/**
 * Internal dependencies
 */
import {
	initShowIfDependencies,
	__testables,
} from '@src/helpers/settings-show-if';

const { matches, readControlValue, resolveControllers, wireMarker } =
	__testables;

const HIDDEN_CLASS = 'gatherpress--is-hidden';

/**
 * Build a minimal settings-table form fragment with a controlling field
 * and a dependent row carrying a show_if marker. Mirrors the markup the
 * PHP side produces (a `<form>` containing a `<tr>` per option, each with
 * a `<td>` holding the field plus the marker for show_if rows).
 *
 * @param {Object}  options                   Setup options.
 * @param {string}  options.controllingType   The controlling input type ('select', 'text', or 'checkbox').
 * @param {string}  options.controllingValue  The controlling input's initial value (or 'true'/'false' for checkbox).
 * @param {Object}  options.conditions        The show_if condition map.
 * @param {boolean} [options.initiallyHidden] Whether the dependent row starts with the hidden class.
 * @return {Object} Refs to the inserted DOM nodes for assertions.
 */
function buildFixture( {
	controllingType,
	controllingValue,
	conditions,
	initiallyHidden = true,
} ) {
	document.body.innerHTML = `
		<form>
			<table>
				<tr class="gatherpress-settings-row">
					<td>${ renderControllingInput( controllingType, controllingValue ) }</td>
				</tr>
				<tr class="gatherpress-settings-row${
	initiallyHidden ? ` ${ HIDDEN_CLASS }` : ''
}">
					<td>
						<input type="text" name="gatherpress_settings[google_maps_api_key]" value="prev-key" />
						<input type="hidden" class="gatherpress-show-if-marker"
							data-show-if='${ JSON.stringify( conditions ) }' />
					</td>
				</tr>
			</table>
		</form>
	`;

	// Pick the LAST element matching the controlling name — production
	// markup puts a hidden fallback BEFORE the live control (select /
	// checkbox), so the last match is the one actually wired to events.
	const candidates = document.querySelectorAll(
		'[name="gatherpress_settings[map_platform]"]'
	);

	return {
		controlling: candidates[ candidates.length - 1 ],
		dependent: document.querySelectorAll( 'tr' )[ 1 ],
		marker: document.querySelector( '.gatherpress-show-if-marker' ),
	};
}

/**
 * Helper to render a controlling input element for the fixture.
 *
 * Mirrors the production templates: select / checkbox render a hidden
 * `<input>` fallback with the same `name` BEFORE the real control (see
 * select.php / checkbox.php). The hidden fallback exists so disabled
 * controls (which drop out of POST) still carry their inherited value.
 * The show_if resolver must pick the LAST element with the matching name
 * — mirroring PHP's "last value wins for repeated names" — so it lands
 * on the live control rather than the hidden fallback.
 *
 * @param {string} type  'select', 'text', or 'checkbox'.
 * @param {string} value The initial value (or 'true'/'false' for checkbox).
 * @return {string} The HTML string for the controlling input.
 */
function renderControllingInput( type, value ) {
	if ( 'select' === type ) {
		return `
			<input type="hidden" name="gatherpress_settings[map_platform]" value="0" />
			<select name="gatherpress_settings[map_platform]">
				<option value="osm"${ 'osm' === value ? ' selected' : '' }>OSM</option>
				<option value="google"${ 'google' === value ? ' selected' : '' }>Google</option>
				<option value="mapbox"${ 'mapbox' === value ? ' selected' : '' }>Mapbox</option>
			</select>
		`;
	}
	if ( 'checkbox' === type ) {
		const checked = 'true' === value ? ' checked' : '';
		const fallback = 'true' === value ? '1' : '0';
		return `
			<input type="hidden" name="gatherpress_settings[map_platform]" value="${ fallback }" />
			<input type="checkbox" name="gatherpress_settings[map_platform]"${ checked } />
		`;
	}
	return `<input type="text" name="gatherpress_settings[map_platform]" value="${ value }" />`;
}

describe( 'settings-show-if helper', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
	} );

	describe( 'matches', () => {
		it( 'compares scalar expected against current as strings', () => {
			expect( matches( 'google', 'google' ) ).toBe( true );
			expect( matches( 'osm', 'google' ) ).toBe( false );
		} );

		it( 'coerces booleans and numbers to strings for comparison', () => {
			// Checkbox booleans round-trip through String() so a stored
			// checkbox value can be matched against '1'/'true' as the
			// declared expected.
			expect( matches( true, 'true' ) ).toBe( true );
			expect( matches( false, 'false' ) ).toBe( true );
			expect( matches( 5, '5' ) ).toBe( true );
		} );

		it( 'returns true when current is a member of an expected array', () => {
			expect( matches( 'google', [ 'google', 'mapbox' ] ) ).toBe( true );
			expect( matches( 'mapbox', [ 'google', 'mapbox' ] ) ).toBe( true );
		} );

		it( 'returns false when current is not a member of an expected array', () => {
			expect( matches( 'osm', [ 'google', 'mapbox' ] ) ).toBe( false );
		} );
	} );

	describe( 'readControlValue', () => {
		it( 'returns the value attribute for non-checkbox inputs', () => {
			const input = document.createElement( 'input' );
			input.type = 'text';
			input.value = 'hello';

			expect( readControlValue( input ) ).toBe( 'hello' );
		} );

		it( 'returns the checked state for checkboxes', () => {
			const input = document.createElement( 'input' );
			input.type = 'checkbox';
			input.checked = true;

			expect( readControlValue( input ) ).toBe( true );
		} );
	} );

	describe( 'resolveControllers', () => {
		it( 'resolves controllers that exist on the page', () => {
			buildFixture( {
				controllingType: 'select',
				controllingValue: 'google',
				conditions: { map_platform: 'google' },
			} );

			const resolved = resolveControllers( { map_platform: 'google' } );

			expect( resolved ).toHaveLength( 1 );
			expect( resolved[ 0 ].key ).toBe( 'map_platform' );
			expect( resolved[ 0 ].el ).not.toBeNull();
		} );

		it( 'drops conditions whose controlling input is missing', () => {
			buildFixture( {
				controllingType: 'select',
				controllingValue: 'google',
				conditions: { map_platform: 'google' },
			} );

			const resolved = resolveControllers( {
				map_platform: 'google',
				nonexistent_field: 'whatever',
			} );

			expect( resolved ).toHaveLength( 1 );
			expect( resolved[ 0 ].key ).toBe( 'map_platform' );
		} );

		it( 'picks the LAST element when multiple inputs share the name', () => {
			// Production markup: select / checkbox fields emit a hidden
			// fallback BEFORE the real control (same name). PHP takes the
			// last value for repeated names, so the resolver must mirror
			// that — otherwise change events fire on the live control while
			// the resolver still points at the inert hidden input.
			buildFixture( {
				controllingType: 'select',
				controllingValue: 'google',
				conditions: { map_platform: 'google' },
			} );

			const resolved = resolveControllers( { map_platform: 'google' } );

			expect( resolved ).toHaveLength( 1 );
			expect( resolved[ 0 ].el.tagName ).toBe( 'SELECT' );
		} );
	} );

	describe( 'wireMarker', () => {
		it( 'reveals the dependent row when the condition is initially satisfied', () => {
			const { dependent, marker } = buildFixture( {
				controllingType: 'select',
				controllingValue: 'google',
				conditions: { map_platform: 'google' },
				initiallyHidden: true,
			} );

			wireMarker( marker );

			expect( dependent.classList.contains( HIDDEN_CLASS ) ).toBe( false );
		} );

		it( 'keeps the dependent row hidden when the condition is unsatisfied', () => {
			const { dependent, marker } = buildFixture( {
				controllingType: 'select',
				controllingValue: 'osm',
				conditions: { map_platform: 'google' },
				initiallyHidden: true,
			} );

			wireMarker( marker );

			expect( dependent.classList.contains( HIDDEN_CLASS ) ).toBe( true );
		} );

		it( 'toggles visibility when the controlling field changes', () => {
			const { controlling, dependent, marker } = buildFixture( {
				controllingType: 'select',
				controllingValue: 'osm',
				conditions: { map_platform: 'google' },
				initiallyHidden: true,
			} );

			wireMarker( marker );
			expect( dependent.classList.contains( HIDDEN_CLASS ) ).toBe( true );

			controlling.value = 'google';
			controlling.dispatchEvent( new Event( 'change' ) );
			expect( dependent.classList.contains( HIDDEN_CLASS ) ).toBe( false );

			controlling.value = 'osm';
			controlling.dispatchEvent( new Event( 'change' ) );
			expect( dependent.classList.contains( HIDDEN_CLASS ) ).toBe( true );
		} );

		it( 'supports an array of expected values (OR within one key)', () => {
			const { controlling, dependent, marker } = buildFixture( {
				controllingType: 'select',
				controllingValue: 'mapbox',
				conditions: { map_platform: [ 'google', 'mapbox' ] },
				initiallyHidden: true,
			} );

			wireMarker( marker );
			expect( dependent.classList.contains( HIDDEN_CLASS ) ).toBe( false );

			controlling.value = 'google';
			controlling.dispatchEvent( new Event( 'change' ) );
			expect( dependent.classList.contains( HIDDEN_CLASS ) ).toBe( false );

			controlling.value = 'osm';
			controlling.dispatchEvent( new Event( 'change' ) );
			expect( dependent.classList.contains( HIDDEN_CLASS ) ).toBe( true );
		} );

		it( 'reads checkbox controls via .checked rather than .value', () => {
			const { controlling, dependent, marker } = buildFixture( {
				controllingType: 'checkbox',
				controllingValue: 'true',
				conditions: { map_platform: 'true' },
				initiallyHidden: true,
			} );

			wireMarker( marker );
			expect( dependent.classList.contains( HIDDEN_CLASS ) ).toBe( false );

			controlling.checked = false;
			controlling.dispatchEvent( new Event( 'change' ) );
			expect( dependent.classList.contains( HIDDEN_CLASS ) ).toBe( true );
		} );

		it( 'leaves the row alone when the marker has no enclosing tr', () => {
			document.body.innerHTML = `
				<input type="hidden" class="gatherpress-show-if-marker"
					data-show-if='{"map_platform":"google"}' />
			`;
			const marker = document.querySelector(
				'.gatherpress-show-if-marker'
			);

			// Should not throw despite the missing tr.
			expect( () => wireMarker( marker ) ).not.toThrow();
		} );

		it( 'returns silently when the marker JSON is malformed', () => {
			document.body.innerHTML = `
				<table>
					<tr class="gatherpress-settings-row ${ HIDDEN_CLASS }">
						<td>
							<input type="hidden" class="gatherpress-show-if-marker"
								data-show-if='not-valid-json' />
						</td>
					</tr>
				</table>
			`;
			const marker = document.querySelector(
				'.gatherpress-show-if-marker'
			);
			const row = document.querySelector( 'tr' );

			// Should not throw, and should leave the row's initial hidden
			// state in place (defensive — the marker is produced by
			// wp_json_encode server-side, so this branch is unreachable in
			// practice).
			expect( () => wireMarker( marker ) ).not.toThrow();
			expect( row.classList.contains( HIDDEN_CLASS ) ).toBe( true );
		} );

		it( 'returns when no controllers can be resolved', () => {
			document.body.innerHTML = `
				<table>
					<tr class="gatherpress-settings-row ${ HIDDEN_CLASS }">
						<td>
							<input type="hidden" class="gatherpress-show-if-marker"
								data-show-if='{"missing_field":"x"}' />
						</td>
					</tr>
				</table>
			`;
			const marker = document.querySelector(
				'.gatherpress-show-if-marker'
			);
			const row = document.querySelector( 'tr' );

			wireMarker( marker );

			// Leaves the row in whatever state the server-side initial
			// render set it to (here: hidden).
			expect( row.classList.contains( HIDDEN_CLASS ) ).toBe( true );
		} );
	} );

	describe( 'initShowIfDependencies', () => {
		it( 'wires every marker found on the page', () => {
			// Two rows, both initially hidden — one whose condition matches
			// (should become visible after init), one whose condition does not
			// (should stay hidden).
			document.body.innerHTML = `
				<form>
					<table>
						<tr>
							<td>
								<select name="gatherpress_settings[map_platform]">
									<option value="osm">OSM</option>
									<option value="google" selected>Google</option>
								</select>
							</td>
						</tr>
						<tr id="row-google" class="${ HIDDEN_CLASS }">
							<td>
								<input type="hidden" class="gatherpress-show-if-marker"
									data-show-if='{"map_platform":"google"}' />
							</td>
						</tr>
						<tr id="row-osm" class="${ HIDDEN_CLASS }">
							<td>
								<input type="hidden" class="gatherpress-show-if-marker"
									data-show-if='{"map_platform":"osm"}' />
							</td>
						</tr>
					</table>
				</form>
			`;

			initShowIfDependencies();

			expect(
				document
					.getElementById( 'row-google' )
					.classList.contains( HIDDEN_CLASS )
			).toBe( false );
			expect(
				document
					.getElementById( 'row-osm' )
					.classList.contains( HIDDEN_CLASS )
			).toBe( true );
		} );

		it( 'is a no-op when there are no markers on the page', () => {
			document.body.innerHTML = '<div>no markers here</div>';

			expect( () => initShowIfDependencies() ).not.toThrow();
		} );
	} );
} );
