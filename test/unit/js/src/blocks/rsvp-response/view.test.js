/**
 * External dependencies
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';

/**
 * Mock the Interactivity API with a namespace-merging store so every
 * module contributing to the `gatherpress` namespace shares one registry,
 * mirroring the real runtime.
 */
jest.mock(
	'@wordpress/interactivity',
	() => {
		const registries = {};

		return {
			store: ( name, config = {} ) => {
				if ( ! registries[ name ] ) {
					registries[ name ] = {
						state: {},
						actions: {},
						callbacks: {},
					};
				}

				const registry = registries[ name ];

				Object.assign( registry.state, config.state );
				Object.assign( registry.actions, config.actions );
				Object.assign( registry.callbacks, config.callbacks );

				return registry;
			},
			getElement: jest.fn(),
			getContext: jest.fn(),
		};
	},
	{ virtual: true }
);

/**
 * WordPress dependencies
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import '@src/blocks/rsvp-response/view';

/**
 * Regression coverage for #2102. When the RSVP response filter collapses to a
 * single selectable option the trigger is disabled, but the disabled state was
 * never exposed to assistive technology. The trigger keeps `role="button"` and
 * `aria-expanded`, so without `aria-disabled` a screen reader announces an
 * operable collapsed button that cannot be operated.
 */
describe( 'rsvp-response processRsvpDropdown trigger disabled state', () => {
	let state;
	let callbacks;

	beforeEach( () => {
		( { state, callbacks } = store( 'gatherpress' ) );

		// Reset shared registry state between tests.
		delete state.posts;
	} );

	/**
	 * Builds the RSVP response dropdown markup in select mode.
	 *
	 * @param {Object} counts Response counts keyed as the block emits them.
	 * @return {Object} The trigger element and the item anchors.
	 */
	function setupDom( counts ) {
		const countsJson = JSON.stringify( counts );

		document.body.innerHTML = `
			<div class="wp-block-gatherpress-rsvp-response" data-counts='${ countsJson }'>
				<div class="wp-block-gatherpress-dropdown" data-dropdown-mode="select">
					<a class="wp-block-gatherpress-dropdown__trigger" href="#" role="button" aria-expanded="false" tabindex="0">Attending (%d)</a>
					<div class="wp-block-gatherpress-dropdown__menu">
						<div class="wp-block-gatherpress-dropdown-item gatherpress--is-attending"><a href="#" data-status="attending">Attending (%d)</a></div>
						<div class="wp-block-gatherpress-dropdown-item gatherpress--is-waiting-list"><a href="#" data-status="waiting_list">Waiting List (%d)</a></div>
						<div class="wp-block-gatherpress-dropdown-item gatherpress--is-not-attending"><a href="#" data-status="not_attending">Not Attending (%d)</a></div>
					</div>
				</div>
			</div>
		`;

		return {
			trigger: document.querySelector(
				'.wp-block-gatherpress-dropdown__trigger'
			),
			items: Array.from(
				document.querySelectorAll(
					'.wp-block-gatherpress-dropdown-item a'
				)
			),
		};
	}

	/**
	 * Runs the callback once per dropdown item, as the runtime does.
	 *
	 * @param {HTMLElement[]} items The item anchors to process.
	 */
	function processItems( items ) {
		getContext.mockReturnValue( { postId: 123 } );

		items.forEach( ( item ) => {
			getElement.mockReturnValue( { ref: item } );
			callbacks.processRsvpDropdown();
		} );
	}

	it( 'marks the trigger aria-disabled when attending is the only option', () => {
		const { trigger, items } = setupDom( {
			attending: 2,
			waiting_list: 0,
			not_attending: 0,
		} );

		processItems( items );

		expect(
			trigger.classList.contains( 'gatherpress--is-disabled' )
		).toBe( true );
		expect( trigger.getAttribute( 'aria-disabled' ) ).toBe( 'true' );
	} );

	it( 'leaves the trigger operable when another option has responses', () => {
		const { trigger, items } = setupDom( {
			attending: 2,
			waiting_list: 1,
			not_attending: 0,
		} );

		processItems( items );

		expect(
			trigger.classList.contains( 'gatherpress--is-disabled' )
		).toBe( false );
		expect( trigger.hasAttribute( 'aria-disabled' ) ).toBe( false );
	} );
} );

/**
 * Coverage for the RSVP response block's share of the per-occurrence store key.
 *
 * The response block owns the filter selection and the attendee counts a
 * visitor reads next to each date. Keyed on the post ID alone, selecting
 * "Waiting List" on one occurrence row of a series switched every other row of
 * the same series with it, and each row's counts overwrote the last.
 */
describe( 'rsvp-response view per-occurrence keying', () => {
	const SERIES_POST_ID = 42;
	const OCCURRENCES = [ '20260823T180000', '20260829T180000' ];

	let state;
	let actions;
	let callbacks;

	beforeEach( () => {
		jest.clearAllMocks();

		( { state, actions, callbacks } = store( 'gatherpress' ) );

		state.posts = {};
		document.body.innerHTML = '';
	} );

	/**
	 * Builds one response block row.
	 *
	 * @param {Object} counts The counts the server rendered for that row.
	 *
	 * @return {Object} The row element and its dropdown item anchors.
	 */
	function addRow( counts ) {
		const row = document.createElement( 'div' );

		row.className = 'wp-block-gatherpress-rsvp-response';
		row.dataset.limitEnabled = '1';
		row.dataset.limit = '2';
		row.dataset.counts = JSON.stringify( counts );
		row.innerHTML = `
			<div class="wp-block-gatherpress-dropdown">
				<a class="wp-block-gatherpress-dropdown__trigger" href="#">Attending (%d)</a>
				<div class="wp-block-gatherpress-dropdown__menu">
					<div class="wp-block-gatherpress-dropdown-item gatherpress--is-attending"><a href="#" data-status="attending">Attending (%d)</a></div>
					<div class="wp-block-gatherpress-dropdown-item gatherpress--is-waiting-list"><a href="#" data-status="waiting_list">Waiting List (%d)</a></div>
				</div>
			</div>
			<div class="gatherpress-rsvp-response__more"></div>
		`;

		document.body.append( row );

		return {
			row,
			items: Array.from(
				row.querySelectorAll( '.wp-block-gatherpress-dropdown-item a' )
			),
		};
	}

	it( 'scopes a filter selection to the occurrence row it was made on', () => {
		const rows = OCCURRENCES.map( () => addRow( { attending: 1 } ) );
		const contexts = OCCURRENCES.map( ( recurrenceId ) => ( {
			postId: SERIES_POST_ID,
			recurrenceId,
		} ) );

		actions.linkHandler = jest.fn();

		getElement.mockReturnValue( { ref: rows[ 1 ].items[ 1 ] } );
		getContext.mockReturnValue( contexts[ 1 ] );
		actions.processRsvpSelection( { preventDefault: jest.fn() } );

		expect(
			OCCURRENCES.map(
				( recurrenceId ) =>
					state.posts[ `${ SERIES_POST_ID }:${ recurrenceId }` ]
						?.rsvpSelection ?? 'missing'
			)
		).toEqual( [ 'missing', 'waiting_list' ] );
	} );

	it( 'ignores a selection on an anchor carrying no status', () => {
		const { items } = addRow( { attending: 1 } );

		actions.linkHandler = jest.fn();
		delete items[ 0 ].dataset.status;

		getElement.mockReturnValue( { ref: items[ 0 ] } );
		getContext.mockReturnValue( { postId: SERIES_POST_ID } );
		actions.processRsvpSelection( { preventDefault: jest.fn() } );

		expect( state.posts ).toEqual( {} );
	} );

	it( 'ignores a selection on a row carrying no post', () => {
		const { items } = addRow( { attending: 1 } );

		actions.linkHandler = jest.fn();

		getElement.mockReturnValue( { ref: items[ 0 ] } );
		getContext.mockReturnValue( {} );
		actions.processRsvpSelection( { preventDefault: jest.fn() } );

		// `initPostContext` declines a falsy key, so no slice is created at all
		// and nothing is written under a zero post ID.
		expect( state.posts ).toEqual( {} );
	} );

	it( 'renders each occurrence own counts in its own dropdown', () => {
		const rows = [ addRow( { attending: 1 } ), addRow( { attending: 4 } ) ];
		const contexts = OCCURRENCES.map( ( recurrenceId ) => ( {
			postId: SERIES_POST_ID,
			recurrenceId,
		} ) );

		/**
		 * Runs the watch callback over every item of every row, as the runtime
		 * re-runs it on each element carrying the directive.
		 */
		function pass() {
			rows.forEach( ( { items }, index ) => {
				getContext.mockReturnValue( contexts[ index ] );

				items.forEach( ( item ) => {
					getElement.mockReturnValue( { ref: item } );
					callbacks.processRsvpDropdown();
				} );
			} );
		}

		// Two passes, and the second is the whole test. On the first,
		// `processRsvpDropdown` reads the row's own `data-counts`, writes it
		// into the store, and reads it straight back in the same invocation,
		// so the per-row vector comes from the DOM read and holds even when
		// every row shares one store key. The first pass then *deletes* that
		// attribute, so every later re-run renders from
		// `state.posts[ postKey ].eventResponses` alone. The Interactivity
		// runtime performs those re-runs on any store change, which means any
		// RSVP anywhere on the page.
		pass();
		pass();

		expect(
			rows.map( ( { items } ) => items[ 0 ].textContent.trim() )
		).toEqual( [ 'Attending (1)', 'Attending (4)' ] );
	} );

	it( 'falls back to a zero count for a row with no seeded responses', () => {
		const { items } = addRow( { attending: 1 } );

		// The server rendered no counts for this row, so the fallback arm of
		// every count read is the one that runs.
		delete items[ 0 ].closest( '.wp-block-gatherpress-rsvp-response' )
			.dataset.counts;

		getContext.mockReturnValue( { postId: SERIES_POST_ID } );
		getElement.mockReturnValue( { ref: items[ 0 ] } );
		callbacks.processRsvpDropdown();

		expect( items[ 0 ].textContent.trim() ).toBe( 'Attending (0)' );
	} );

	it( 'hides the show-more toggle per occurrence rather than per post', () => {
		const rows = [ addRow( { attending: 1 } ), addRow( { attending: 9 } ) ];
		const contexts = OCCURRENCES.map( ( recurrenceId ) => ( {
			postId: SERIES_POST_ID,
			recurrenceId,
		} ) );

		OCCURRENCES.forEach( ( recurrenceId, index ) => {
			state.posts[ `${ SERIES_POST_ID }:${ recurrenceId }` ] = {
				rsvpSelection: 'attending',
				eventResponses: { attending: 1 === index ? 9 : 1 },
			};
		} );

		const hidden = rows.map( ( { row }, index ) => {
			const toggle = row.querySelector(
				'.gatherpress-rsvp-response__more'
			);

			getElement.mockReturnValue( { ref: toggle } );
			getContext.mockReturnValue( contexts[ index ] );
			callbacks.showHideToggle();

			return toggle.classList.contains( 'gatherpress--is-hidden' );
		} );

		// The limit is 2: the one-attendee occurrence hides its toggle, the
		// nine-attendee one shows it. A shared key gives both rows the same answer.
		expect( hidden ).toEqual( [ true, false ] );
	} );

	it( 'defaults an unseeded row to the attending filter and a zero count', () => {
		const { row } = addRow( { attending: 1 } );
		const toggle = row.querySelector( '.gatherpress-rsvp-response__more' );

		getElement.mockReturnValue( { ref: toggle } );
		getContext.mockReturnValue( { postId: SERIES_POST_ID } );
		callbacks.showHideToggle();

		expect( toggle.classList.contains( 'gatherpress--is-hidden' ) ).toBe(
			true
		);
	} );

	it( 'renders a dropdown and a toggle for a row that carries no post', () => {
		const { row, items } = addRow( { attending: 1 } );
		const toggle = row.querySelector( '.gatherpress-rsvp-response__more' );

		getContext.mockReturnValue( {} );
		getElement.mockReturnValue( { ref: items[ 0 ] } );
		callbacks.processRsvpDropdown();

		getElement.mockReturnValue( { ref: toggle } );
		callbacks.showHideToggle();

		// The requirement for a block the server rendered without a post is
		// that it still shows that row's own server-rendered count and does not
		// throw. It must also claim no occurrence key, so it cannot be mistaken
		// for one of a series' rows.
		expect( items[ 0 ].textContent.trim() ).toBe( 'Attending (1)' );
		expect( toggle.classList.contains( 'gatherpress--is-hidden' ) ).toBe(
			true
		);
		expect(
			Object.keys( state.posts ).some( ( key ) => key.includes( ':' ) )
		).toBe( false );
	} );

	it( 'leaves the toggle alone when the block sets no limit', () => {
		const { row } = addRow( { attending: 9 } );
		const toggle = row.querySelector( '.gatherpress-rsvp-response__more' );

		row.dataset.limitEnabled = '0';

		getElement.mockReturnValue( { ref: toggle } );
		getContext.mockReturnValue( { postId: SERIES_POST_ID } );
		callbacks.showHideToggle();

		expect( toggle.classList.contains( 'gatherpress--is-hidden' ) ).toBe(
			false
		);
	} );
} );
