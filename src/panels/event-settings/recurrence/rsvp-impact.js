/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { EVENT_REST_API } from '../../../helpers/namespace';

/**
 * Save-lock key for an in-flight impact request.
 *
 * @since 0.36.0
 * @type {string}
 */
const SAVE_LOCK = 'gatherpress-recurrence-impact';

/**
 * How long to hold the save lock before giving up on the answer.
 *
 * A rejected request releases the lock through its own arm below. A request
 * that never settles has no arm to run, and without a bound the post stays
 * unsavable with only the checking message on screen, escapable solely by
 * reloading the editor and losing whatever else was unsaved. This gives the
 * hang the same outcome the failure already has: say the check did not run,
 * and let the organizer save.
 *
 * @since 0.36.0
 * @type {number}
 */
const GIVE_UP_AFTER_MS = 10000;

/**
 * Tell the organizer how many RSVPs a rule change would strand.
 *
 * When a rule change would move or remove occurrences carrying RSVPs, the
 * organizer is shown how many approved RSVPs sit on the dates the candidate
 * rule would remove, before committing, and the RSVPs are **not** migrated.
 * Migrating them risks re-enrolling attendees in a date they never agreed to,
 * so the product answer is to surface the number rather than to quietly do
 * something with it.
 *
 * The count comes from the read-only `recurrence-impact` route, which compares
 * the candidate rule against the occurrences already projected. Nothing is
 * written, so an organizer who thinks better of the change and reverts it has
 * lost nothing.
 *
 * The answer is only useful if it arrives before the organizer commits, so
 * saving is locked while a request is in flight. Without that, Update could be
 * pressed while the count was still unknown and the rule would save with no
 * warning shown at all.
 *
 * A failed request is reported as a failure rather than as zero. Reading it as
 * zero rendered nothing, which is the same thing the component renders when it
 * has checked and found nothing at risk, so a broken route was indistinguishable
 * from an all-clear. Saving is unlocked on failure, because this route is
 * informational and a site whose route is unavailable must still be able to
 * edit its events, but the organizer is told the check did not run.
 *
 * @since 0.36.0
 *
 * @param {Object} props        Component props.
 * @param {number} props.postId Post being edited.
 * @param {Object} props.rule   Candidate rule values.
 *
 * @return {JSX.Element|null} The notice, or null when nothing would be stranded.
 */
const RsvpImpact = ( { postId, rule } ) => {
	const [ impact, setImpact ] = useState( { status: 'idle', stranded: 0 } );
	const serialized = JSON.stringify( rule );
	const latestRequest = useRef( 0 );
	// Held in a ref rather than named in the dependency list below. This effect
	// should re-run when the post or the rule changes, not when a dispatcher's
	// identity does, and depending on the identity re-runs it on every render
	// that hands back a fresh one, which is a render loop rather than a
	// stale-value bug.
	const editor = useDispatch( 'core/editor' );
	const editorRef = useRef( editor );

	editorRef.current = editor;

	useEffect( () => {
		// A monotonic token, not a boolean. Editing a rule issues one request
		// per change and nothing cancels the previous one, so a slow answer for
		// an abandoned rule could land after a fast answer for the rule on
		// screen and overwrite the warning with the old rule's count. Only the
		// most recently issued request may write state.
		latestRequest.current += 1;

		const generation = latestRequest.current;
		const isCurrent = () => generation === latestRequest.current;

		if ( ! postId ) {
			setImpact( { status: 'idle', stranded: 0 } );
			editorRef.current.unlockPostSaving( SAVE_LOCK );

			return undefined;
		}

		setImpact( { status: 'loading', stranded: 0 } );
		editorRef.current.lockPostSaving( SAVE_LOCK );

		const giveUp = setTimeout( () => {
			if ( ! isCurrent() ) {
				return;
			}

			setImpact( { status: 'error', stranded: 0 } );
			editorRef.current.unlockPostSaving( SAVE_LOCK );
		}, GIVE_UP_AFTER_MS );

		apiFetch( {
			path:
				`${ EVENT_REST_API }/recurrence-impact?post_id=${ postId }` +
				`&recurrence=${ encodeURIComponent( serialized ) }`,
		} )
			.then( ( response ) => {
				if ( ! isCurrent() ) {
					return;
				}

				clearTimeout( giveUp );
				setImpact( {
					status: 'ready',
					stranded: response?.rsvp_count ?? 0,
				} );
				editorRef.current.unlockPostSaving( SAVE_LOCK );
			} )
			.catch( () => {
				if ( ! isCurrent() ) {
					return;
				}

				clearTimeout( giveUp );
				setImpact( { status: 'error', stranded: 0 } );
				editorRef.current.unlockPostSaving( SAVE_LOCK );
			} );

		// A lock outlives the component that took it, so an unmount mid-flight
		// would leave the post unsavable with nothing on screen explaining why.
		return () => {
			clearTimeout( giveUp );
			editorRef.current.unlockPostSaving( SAVE_LOCK );
		};
	}, [ postId, serialized ] );

	if ( 'loading' === impact.status ) {
		return (
			<output className="gatherpress-recurrence-panel__impact">
				{ __(
					'Checking which RSVPs this change affects…',
					'gatherpress'
				) }
			</output>
		);
	}

	if ( 'error' === impact.status ) {
		return (
			<output className="gatherpress-recurrence-panel__impact">
				{ __(
					'The affected RSVPs could not be checked. Saving this change may leave RSVPs on dates it removes.',
					'gatherpress'
				) }
			</output>
		);
	}

	if ( 0 === impact.stranded ) {
		return null;
	}

	return (
		<output className="gatherpress-recurrence-panel__impact">
			{ sprintf(
				/* translators: %d: number of RSVPs on dates this change removes. */
				_n(
					'%d RSVP is on a date this change removes. It stays where it is and is not moved to another date.',
					'%d RSVPs are on dates this change removes. They stay where they are and are not moved to other dates.',
					impact.stranded,
					'gatherpress'
				),
				impact.stranded
			) }
		</output>
	);
};

export default RsvpImpact;
