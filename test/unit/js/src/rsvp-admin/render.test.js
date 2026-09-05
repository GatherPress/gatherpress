/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';
import { act, fireEvent, render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
import Filters from '@src/rsvp-admin/filters';
import ResponseFilter from '@src/rsvp-admin/response-filter';

const STATUSES = [
	{ value: 'attending', label: 'Attending' },
	{ value: 'not_attending', label: 'Not Attending' },
	{ value: 'waiting_list', label: 'Waiting List' },
];

/**
 * Renders and lets React settle.
 *
 * The popover positions itself and the picker resolves after the first paint;
 * unwaited, those land outside `act()`.
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

/**
 * Clicks and lets React settle, for the same reason.
 *
 * Opening the dropdown is deliberately not wrapped in an awaited `act()`:
 * since @wordpress/components 32.6 the popover keeps a positioning loop
 * running while it is open, so awaiting `act()` never resolves and the test
 * times out. Callers await a `findBy*` query for what the click revealed,
 * which is what React Testing Library recommends anyway.
 *
 * @param {HTMLElement} element The element to click.
 *
 * @return {Promise<void>} Resolves once React has settled.
 */
const clickSettled = async ( element ) => {
	fireEvent.click( element );
};

describe( 'ResponseFilter', () => {
	it( 'names the control by its current selection', async () => {
		await renderSettled(
			<ResponseFilter
				statuses={ STATUSES }
				selected={ [] }
				onChange={ () => {} }
			/>
		);

		expect(
			screen.getByLabelText( 'Filter by response: all' )
		).toBeInTheDocument();
	} );

	it( 'shows the count beside the icon once filtered', async () => {
		await renderSettled(
			<ResponseFilter
				statuses={ STATUSES }
				selected={ [ 'attending', 'waiting_list' ] }
				onChange={ () => {} }
			/>
		);

		expect(
			screen.getByLabelText( 'Filter by response: 2 selected' )
		).toHaveTextContent( '2' );
	} );

	it( 'opens a checkbox for every status', async () => {
		await renderSettled(
			<ResponseFilter
				statuses={ STATUSES }
				selected={ [] }
				onChange={ () => {} }
			/>
		);

		await clickSettled( screen.getByLabelText( 'Filter by response: all' ) );

		// One await for the popover to mount, then the rest synchronously: a
		// findBy* per status spends its own timeout each and blows the budget.
		expect(
			await screen.findByLabelText( STATUSES[ 0 ].label )
		).toBeInTheDocument();

		STATUSES.slice( 1 ).forEach( ( status ) => {
			expect( screen.getByLabelText( status.label ) ).toBeInTheDocument();
		} );
	} );

	it( 'reports the new selection when a box is ticked', async () => {
		const onChange = jest.fn();

		await renderSettled(
			<ResponseFilter
				statuses={ STATUSES }
				selected={ [] }
				onChange={ onChange }
			/>
		);

		await clickSettled( screen.getByLabelText( 'Filter by response: all' ) );
		await clickSettled( await screen.findByLabelText( 'Waiting List' ) );

		expect( onChange ).toHaveBeenCalledWith( [ 'waiting_list' ] );
	} );

	it( 'reports the remainder when a ticked box is unticked', async () => {
		const onChange = jest.fn();

		await renderSettled(
			<ResponseFilter
				statuses={ STATUSES }
				selected={ [ 'attending', 'waiting_list' ] }
				onChange={ onChange }
			/>
		);

		await clickSettled(
			screen.getByLabelText( 'Filter by response: 2 selected' )
		);
		await clickSettled( await screen.findByLabelText( 'Attending' ) );

		expect( onChange ).toHaveBeenCalledWith( [ 'waiting_list' ] );
	} );
} );

describe( 'Filters', () => {
	const defaults = {
		postTypes: [ 'gatherpress_event' ],
		initialPostId: null,
		eventLabel: 'Filter by event',
		statuses: STATUSES,
		initialResponses: [],
	};

	/**
	 * Presses Filter and acknowledges jsdom's refusal to navigate.
	 *
	 * jsdom reports the `location.href` assignment through `console.error`,
	 * and its `location` cannot be stubbed. The URL is covered in
	 * `filters.test.js`; this only drives the handler.
	 *
	 * @return {Promise<void>} Resolves once React has settled.
	 */
	const pressFilter = async () => {
		await clickSettled( screen.getByRole( 'button', { name: 'Filter' } ) );

		expect( console ).toHaveErrored();
	};

	it( 'renders both controls behind one Filter button', async () => {
		await renderSettled( <Filters { ...defaults } /> );

		expect( screen.getByLabelText( 'Filter by event' ) ).toBeInTheDocument();
		expect(
			screen.getByLabelText( 'Filter by response: all' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Filter' } )
		).toBeInTheDocument();
	} );

	it( 'reflects an event carried in from the request', async () => {
		await renderSettled( <Filters { ...defaults } initialPostId={ 11 } /> );

		expect( screen.getByLabelText( 'Filter by event' ) ).toBeInTheDocument();
	} );

	it( 'reflects responses carried in from the request', async () => {
		await renderSettled(
			<Filters { ...defaults } initialResponses={ [ 'attending' ] } />
		);

		expect(
			screen.getByLabelText( 'Filter by response: Attending' )
		).toBeInTheDocument();
	} );

	it( 'submits when Filter is pressed', async () => {
		await renderSettled( <Filters { ...defaults } initialPostId={ 11 } /> );

		await pressFilter();
	} );

	it( 'submits a selection made in the popover', async () => {
		await renderSettled( <Filters { ...defaults } /> );

		await clickSettled( screen.getByLabelText( 'Filter by response: all' ) );
		await clickSettled( await screen.findByLabelText( 'Not Attending' ) );

		expect(
			screen.getByLabelText( 'Filter by response: Not Attending' )
		).toBeInTheDocument();

		await pressFilter();
	} );
} );
