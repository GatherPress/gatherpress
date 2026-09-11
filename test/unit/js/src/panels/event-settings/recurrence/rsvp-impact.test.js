/**
 * External dependencies
 */
import { act, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, jest, test } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies
 */
jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
	_n: ( single, plural, number ) => ( 1 === number ? single : plural ),
	sprintf: ( format, ...args ) =>
		format.replace( /%d/g, () => String( args.shift() ) ),
} ) );

const mockLockPostSaving = jest.fn();
const mockUnlockPostSaving = jest.fn();

// Deliberately hands back fresh function identities on every call, which is
// the shape that turns a dependency on them into a render loop. The component
// holds them in a ref for exactly that reason, so this mock also pins that.
jest.mock( '@wordpress/data', () => ( {
	useDispatch: () => ( {
		lockPostSaving: ( ...args ) => mockLockPostSaving( ...args ),
		unlockPostSaving: ( ...args ) => mockUnlockPostSaving( ...args ),
	} ),
} ) );

const mockApiFetch = jest.fn();

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: ( args ) => mockApiFetch( args ),
} ) );

/**
 * Internal dependencies
 */
import RsvpImpact from '@src/panels/event-settings/recurrence/rsvp-impact';

const RULE = {
	frequency: 'weekly',
	interval: 2,
	weekdays: [ 2, 4 ],
	end_type: 'count',
	count: 4,
};

beforeEach( () => {
	jest.clearAllMocks();
} );

describe( 'RsvpImpact', () => {
	test( 'says nothing when the change strands no RSVP', async () => {
		mockApiFetch.mockResolvedValue( { removed: [ 'x' ], rsvp_count: 0 } );

		const { container } = render(
			<RsvpImpact postId={ 42 } rule={ RULE } />,
		);

		await waitFor( () => expect( mockApiFetch ).toHaveBeenCalled() );
		expect( container ).toBeEmptyDOMElement();
	} );

	test( 'reports a single stranded RSVP in the singular', async () => {
		mockApiFetch.mockResolvedValue( { removed: [ 'x' ], rsvp_count: 1 } );

		render( <RsvpImpact postId={ 42 } rule={ RULE } /> );

		await waitFor( () =>
			expect(
				screen.getByText(
					'1 RSVP is on a date this change removes. It stays where it is and is not moved to another date.',
				),
			).toBeInTheDocument(),
		);
	} );

	test( 'reports several stranded RSVPs in the plural, and says they are not moved', async () => {
		mockApiFetch.mockResolvedValue( {
			removed: [ 'x', 'y' ],
			rsvp_count: 3,
		} );

		render( <RsvpImpact postId={ 42 } rule={ RULE } /> );

		await waitFor( () =>
			expect(
				screen.getByText(
					'3 RSVPs are on dates this change removes. They stay where they are and are not moved to other dates.',
				),
			).toBeInTheDocument(),
		);
	} );

	test( 'asks the impact route about the rule currently being edited', async () => {
		mockApiFetch.mockResolvedValue( { removed: [], rsvp_count: 0 } );

		render( <RsvpImpact postId={ 42 } rule={ RULE } /> );

		await waitFor( () =>
			expect( mockApiFetch ).toHaveBeenCalledWith( {
				path:
					'/gatherpress/v1/event/recurrence-impact?post_id=42' +
					`&recurrence=${ encodeURIComponent(
						JSON.stringify( RULE ),
					) }`,
			} ),
		);
	} );

	test( 'asks again when the rule changes', async () => {
		mockApiFetch.mockResolvedValue( { removed: [], rsvp_count: 0 } );

		const { rerender } = render(
			<RsvpImpact postId={ 42 } rule={ RULE } />,
		);

		await waitFor( () => expect( mockApiFetch ).toHaveBeenCalledTimes( 1 ) );

		rerender(
			<RsvpImpact postId={ 42 } rule={ { ...RULE, count: 2 } } />,
		);

		await waitFor( () => expect( mockApiFetch ).toHaveBeenCalledTimes( 2 ) );

		// A new object with identical values is the same rule, and re-asking on
		// every render would put the editor into a request loop.
		rerender(
			<RsvpImpact postId={ 42 } rule={ { ...RULE, count: 2 } } />,
		);

		expect( mockApiFetch ).toHaveBeenCalledTimes( 2 );
	} );

	test( 'asks nothing before the post has an ID', () => {
		const { container } = render(
			<RsvpImpact postId={ 0 } rule={ RULE } />,
		);

		expect( mockApiFetch ).not.toHaveBeenCalled();
		expect( container ).toBeEmptyDOMElement();
	} );

	test( 'says the check did not run when the impact request fails', async () => {
		// Rendering nothing here is what an all-clear looks like, so reading a
		// failure as zero made a broken route indistinguishable from "no RSVPs
		// are at risk" at the moment the organizer commits.
		mockApiFetch.mockRejectedValue( new Error( 'nope' ) );

		render( <RsvpImpact postId={ 42 } rule={ RULE } /> );

		await waitFor( () =>
			expect(
				screen.getByText(
					'The affected RSVPs could not be checked. Saving this change may leave RSVPs on dates it removes.',
				),
			).toBeInTheDocument(),
		);
	} );

	test( 'locks saving until the answer arrives, and releases it either way', async () => {
		// The count is only useful before the organizer commits. Without the
		// lock, Update could be pressed while the answer was still in flight
		// and the rule would save with no warning shown at all.
		let resolvePending;

		const pending = new Promise( ( resolve ) => {
			resolvePending = resolve;
		} );

		mockApiFetch.mockReturnValueOnce( pending );

		render( <RsvpImpact postId={ 42 } rule={ RULE } /> );

		await waitFor( () =>
			expect( mockLockPostSaving ).toHaveBeenCalledWith(
				'gatherpress-recurrence-impact',
			),
		);
		expect( mockUnlockPostSaving ).not.toHaveBeenCalled();
		expect(
			screen.getByText( 'Checking which RSVPs this change affects…' ),
		).toBeInTheDocument();

		await act( async () => {
			resolvePending( { removed: [ 'x' ], rsvp_count: 2 } );
			await pending;
		} );

		expect( mockUnlockPostSaving ).toHaveBeenCalledWith(
			'gatherpress-recurrence-impact',
		);
	} );

	test( 'releases the save lock when the request fails', async () => {
		// This route is informational, so a site whose route is unavailable
		// must still be able to edit its events. The warning above is what
		// keeps that honest.
		mockApiFetch.mockRejectedValue( new Error( 'nope' ) );

		render( <RsvpImpact postId={ 42 } rule={ RULE } /> );

		await waitFor( () =>
			expect( mockUnlockPostSaving ).toHaveBeenCalledWith(
				'gatherpress-recurrence-impact',
			),
		);
	} );

	test( 'gives up and releases the lock when the request never settles', async () => {
		// A rejected request releases the lock through its own arm. A request
		// that never settles has no arm to run, and an unbounded lock leaves
		// the post unsavable with only the checking message on screen, with no
		// way out but reloading the editor and losing the rest of the edit.
		// A hang gets the same outcome a failure gets.
		jest.useFakeTimers();

		try {
			mockApiFetch.mockReturnValueOnce( new Promise( () => {} ) );

			render( <RsvpImpact postId={ 42 } rule={ RULE } /> );

			expect( mockLockPostSaving ).toHaveBeenCalledWith(
				'gatherpress-recurrence-impact',
			);
			expect( mockUnlockPostSaving ).not.toHaveBeenCalled();

			await act( async () => {
				jest.advanceTimersByTime( 10000 );
			} );

			expect(
				screen.getByText(
					'The affected RSVPs could not be checked. Saving this change may leave RSVPs on dates it removes.',
				),
			).toBeInTheDocument();
			expect( mockUnlockPostSaving ).toHaveBeenCalledWith(
				'gatherpress-recurrence-impact',
			);
		} finally {
			jest.useRealTimers();
		}
	} );

	test( 'releases the save lock when it unmounts mid-flight', async () => {
		// A lock outlives the component that took it, so an unmount while a
		// request is pending would leave the post unsavable with nothing on
		// screen explaining why.
		mockApiFetch.mockReturnValueOnce( new Promise( () => {} ) );

		const { unmount } = render(
			<RsvpImpact postId={ 42 } rule={ RULE } />,
		);

		await waitFor( () => expect( mockLockPostSaving ).toHaveBeenCalled() );

		unmount();

		expect( mockUnlockPostSaving ).toHaveBeenCalledWith(
			'gatherpress-recurrence-impact',
		);
	} );

	test( 'reports nothing when the response carries no count', async () => {
		mockApiFetch.mockResolvedValue( undefined );

		const { container } = render(
			<RsvpImpact postId={ 42 } rule={ RULE } />,
		);

		await waitFor( () => expect( mockApiFetch ).toHaveBeenCalled() );
		expect( container ).toBeEmptyDOMElement();
	} );

	test( 'ignores a stale answer that lands after the rule on screen was answered', async () => {
		// Rule A is slow and reports nothing stranded; rule B is fast and
		// reports four. Accepting A's completion afterwards would hide the
		// warning for the rule the organizer is actually looking at, moments
		// before they commit a destructive change.
		let resolveSlow;

		const slow = new Promise( ( resolve ) => {
			resolveSlow = resolve;
		} );

		mockApiFetch.mockReturnValueOnce( slow );
		mockApiFetch.mockResolvedValueOnce( { removed: [ 'x' ], rsvp_count: 4 } );

		const { rerender } = render(
			<RsvpImpact postId={ 42 } rule={ RULE } />,
		);

		rerender(
			<RsvpImpact postId={ 42 } rule={ { ...RULE, count: 2 } } />,
		);

		await waitFor( () =>
			expect(
				screen.getByText(
					'4 RSVPs are on dates this change removes. They stay where they are and are not moved to other dates.',
				),
			).toBeInTheDocument(),
		);

		await act( async () => {
			resolveSlow( { removed: [], rsvp_count: 0 } );
			await slow;
		} );

		expect(
			screen.getByText(
				'4 RSVPs are on dates this change removes. They stay where they are and are not moved to other dates.',
			),
		).toBeInTheDocument();
	} );

	test( 'ignores a stale rejection that lands after the rule on screen was answered', async () => {
		let rejectSlow;

		const slow = new Promise( ( resolve, reject ) => {
			rejectSlow = reject;
		} );

		mockApiFetch.mockReturnValueOnce( slow );
		mockApiFetch.mockResolvedValueOnce( { removed: [ 'x' ], rsvp_count: 2 } );

		const { rerender } = render(
			<RsvpImpact postId={ 42 } rule={ RULE } />,
		);

		rerender(
			<RsvpImpact postId={ 42 } rule={ { ...RULE, count: 3 } } />,
		);

		await waitFor( () =>
			expect(
				screen.getByText(
					'2 RSVPs are on dates this change removes. They stay where they are and are not moved to other dates.',
				),
			).toBeInTheDocument(),
		);

		await act( async () => {
			rejectSlow( new Error( 'network' ) );
			await slow.catch( () => {} );
		} );

		expect(
			screen.getByText(
				'2 RSVPs are on dates this change removes. They stay where they are and are not moved to other dates.',
			),
		).toBeInTheDocument();
	} );
} );
