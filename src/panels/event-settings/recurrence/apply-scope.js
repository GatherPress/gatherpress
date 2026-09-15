/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, RadioControl, SelectControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { dateI18n, getSettings } from '@wordpress/date';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { EVENT_REST_API } from '../../../helpers/namespace';

/**
 * The apply-scope chooser, with retroactive as the default.
 *
 * ECP's three-scope model (all / this-and-following / single) is reduced to
 * two options with retroactive as the default, because most edits are
 * corrections rather than schedule changes and every forward edit costs a second
 * event post. "This occurrence only" is not a top-level scope here; it is a
 * per-occurrence operation and is Post-MVP.
 *
 * Retroactive needs no action at all, since it is what an ordinary save
 * already does. This component therefore only ever calls the server for the
 * forward choice, and a panel left on its default performs no request.
 *
 * **The split is refused while the editor holds unsaved changes**, and that is
 * not a nicety. A split moves the forward occurrences onto a *second* post; an
 * edit still sitting in the editor belongs to the origin, so pressing Update
 * after the split writes it to the occurrences that stayed behind, the past.
 * That is the exact inverse of what a forward edit promises: moving the venue
 * in October does not rewrite where we met in March. Carrying an in-flight edit through a
 * split would need the pre-edit state captured before the split runs, which is
 * fragile; refusing to split until the editor is clean costs the organizer one
 * save and cannot strand a change on the wrong side.
 *
 * @since 0.36.0
 *
 * @param {Object} props        Component props.
 * @param {number} props.postId Post being edited.
 *
 * @return {JSX.Element} The apply-scope chooser.
 */
const ApplyScope = ( { postId } ) => {
	const [ scope, setScope ] = useState( 'retroactive' );
	const [ occurrences, setOccurrences ] = useState( [] );
	const [ splitFrom, setSplitFrom ] = useState( '' );
	const [ isSplitting, setIsSplitting ] = useState( false );
	const [ notice, setNotice ] = useState( '' );
	const [ forwardPostId, setForwardPostId ] = useState( 0 );

	const isDirty = useSelect(
		( select ) => select( 'core/editor' ).isEditedPostDirty(),
		[]
	);

	const { postType, restBase, restNamespace } = useSelect( ( select ) => {
		const type = select( 'core/editor' )?.getCurrentPostType?.();
		const typeObject = type ? select( 'core' )?.getPostType?.( type ) : undefined;

		return {
			postType: type,
			restBase: typeObject?.rest_base,
			restNamespace: typeObject?.rest_namespace ?? 'wp/v2',
		};
	}, [] );

	const { receiveEntityRecords } = useDispatch( 'core' );

	const latestRequest = useRef( 0 );

	/**
	 * Read the series' upcoming occurrences.
	 *
	 * Shared by the effect below, which seeds the panel, and by the post-split
	 * refresh, which re-reads the same list for a different reason. The two
	 * differ only in what they do with the rows, so the request itself lives
	 * here and neither can drift from the other's path.
	 *
	 * Memoized on the post alone so the effect below can depend on it without
	 * the identity of a fresh closure re-running the fetch on every render.
	 *
	 * @return {Promise} The occurrence list request.
	 */
	const fetchOccurrences = useCallback(
		() =>
			apiFetch( {
				path: addQueryArgs( `${ EVENT_REST_API }/occurrences`, {
					post_id: postId,
				} ),
			} ),
		[ postId ]
	);

	useEffect( () => {
		// Everything below is scoped to one post, and this component survives
		// navigation from event A to event B. Clearing first means B's panel
		// cannot offer A's occurrences, A's notice or A's "Edit the new event"
		// link while B's request is still in flight. A split fired from that
		// state would submit B's post with A's occurrence.
		latestRequest.current += 1;

		const generation = latestRequest.current;
		const isCurrent = () => generation === latestRequest.current;

		setOccurrences( [] );
		setSplitFrom( '' );
		setNotice( '' );
		setForwardPostId( 0 );
		setIsSplitting( false );

		if ( 'forward' !== scope || ! postId ) {
			return;
		}

		fetchOccurrences()
			.then( ( rows ) => {
				if ( ! isCurrent() ) {
					return;
				}

				setOccurrences( rows ?? [] );
				// The route lists upcoming occurrences only, so the first row is
				// the series' own first date whenever the series has not started
				// and splitting there degrades to "Nothing was split". It is
				// the one selection that can be a guaranteed no-op, so the
				// default steps past it whenever there is somewhere to step to.
				setSplitFrom(
					rows?.[ 1 ]?.recurrence_id ??
						rows?.[ 0 ]?.recurrence_id ??
						''
				);
			} )
			.catch( () => {
				if ( isCurrent() ) {
					setOccurrences( [] );
				}
			} );
	}, [ scope, postId, fetchOccurrences ] );

	/**
	 * Describe what a completed split did, including the two automatic
	 * degradations the organizer must be told about rather than left to
	 * discover.
	 *
	 * @param {Object} result Split result from the `split-series` REST route.
	 *
	 * @return {string} Message to show.
	 */
	const describe = ( result ) => {
		if ( ! result.split ) {
			// Two different refusals, and only the server can tell them apart.
			// A series that lives on one post really is applied whole from its
			// first date. A series already split spans several posts, and this
			// date is only the first of the fragment that owns it: the rest of
			// the series sits on a sibling event this refusal did not touch, so
			// claiming the whole series would tell the organizer something
			// untrue about the dates on the other side of the earlier split.
			if ( 'fragment_first_occurrence' === result.reason ) {
				return __(
					'This event already starts at this date, so there is nothing here to split off: your change applies to every date this event holds. The rest of the series is on another event. Nothing was split.',
					'gatherpress'
				);
			}

			return __(
				'This is the first occurrence, so applying the change forward is the same as applying it to the whole series. Nothing was split.',
				'gatherpress'
			);
		}

		const moved = sprintf(
			/* translators: %d: number of occurrences moved onto the new event. */
			_n(
				'%d occurrence moved to a new event. Make your change there.',
				'%d occurrences moved to a new event. Make your change there.',
				result.moved,
				'gatherpress'
			),
			result.moved
		);

		if ( result.forward_recurring ) {
			return moved;
		}

		return `${ moved } ${ __(
			'It holds a single date, so it is a plain non-recurring event.',
			'gatherpress'
		) }`;
	};

	/**
	 * Re-read the occurrence list after a split moved rows between posts.
	 *
	 * Every row names the post that owns its date, and `handleSplit()` reads
	 * that owner to aim the next split. A split rewrites those owners, so rows
	 * left over from before it point a second split in one session at the post
	 * that no longer produces the chosen date.
	 *
	 * Deliberately not a dependency of the list effect above. That effect
	 * begins by clearing every post-scoped piece of state, so re-running it
	 * here would reset `splitFrom` to the default two rows past the
	 * organizer's choice and wipe the notice the split had just written. This
	 * writes the rows and nothing else, which leaves both intact.
	 *
	 * @param {Function} isCurrent Whether the panel still shows this post.
	 *
	 * @return {Promise|undefined} The refresh request, when one is still wanted.
	 */
	const refreshOccurrences = ( isCurrent ) => {
		// The panel has moved to another post, whose own list request is
		// already in flight. Reading this one would spend a request on rows
		// nothing may use.
		if ( ! isCurrent() ) {
			return undefined;
		}

		return fetchOccurrences()
			.then( ( rows ) => {
				if ( isCurrent() ) {
					setOccurrences( rows ?? [] );
				}
			} )
			.catch( () => {
				// The split succeeded and its notice stands. Rows that could
				// not be re-read stay as they were, which is what the panel
				// had before this refresh existed.
			} );
	};

	/**
	 * Refresh the origin post's stored entity after a split rewrote it.
	 *
	 * The split caps the origin's rule server side (and can rewrite its
	 * datetime when a side demotes) while the editor's copy of the post stays
	 * as it was before the split. The recurrence panel seeds from that stale
	 * `gatherpress_recurrence`, so without this refresh one touch of any rule
	 * control re-persists the pre-split rule and re-projects the moved
	 * occurrences under the origin while the forward post still owns them.
	 * Receiving the fresh record updates `gatherpress_recurrence` and
	 * `gatherpress_datetime` in the store, and the panel's own meta effect
	 * re-parses the capped rule from it.
	 *
	 * The occurrence list is re-read on the end of the same chain rather than
	 * beside it, so the button stays busy until both the store and the rows the
	 * next split is aimed with are consistent again.
	 *
	 * @param {Function} isCurrent Whether the panel still shows this post.
	 *
	 * @return {Promise|undefined} The refresh request, when one is still wanted.
	 */
	const refreshOriginEntity = ( isCurrent ) => {
		// A post type whose REST base has not resolved leaves nothing to
		// fetch; the next full entity resolution catches the editor up. The
		// occurrence list is still re-read, because nothing else corrects the
		// owners a second split in this session would be aimed with.
		if ( ! postType || ! restBase ) {
			return refreshOccurrences( isCurrent );
		}

		return apiFetch( {
			path: addQueryArgs( `/${ restNamespace }/${ restBase }/${ postId }`, {
				context: 'edit',
			} ),
		} )
			.then( ( record ) => {
				if ( isCurrent() && record ) {
					receiveEntityRecords( 'postType', postType, record );
				}
			} )
			.catch( () => {
				// The split itself succeeded. A failed refresh leaves the
				// notice in place and the stale panel to the next resolution.
			} )
			.then( () => refreshOccurrences( isCurrent ) );
	};

	/**
	 * Split the series at the chosen occurrence.
	 *
	 * @return {void}
	 */
	const handleSplit = () => {
		const generation = latestRequest.current;
		const isCurrent = () => generation === latestRequest.current;
		// The row's own owner, not the post open in the editor. A series split
		// once already holds later occurrences on a sibling post, and the split
		// has to cap the rule that actually produces the chosen date.
		const owner =
			occurrences.find(
				( occurrence ) => occurrence.recurrence_id === splitFrom
			)?.series_post_id ?? postId;

		setIsSplitting( true );
		setNotice( '' );
		setForwardPostId( 0 );

		apiFetch( {
			path: `${ EVENT_REST_API }/split-series`,
			method: 'POST',
			data: { post_id: owner, recurrence_id: splitFrom },
		} )
			.then( ( result ) => {
				if ( ! isCurrent() ) {
					return undefined;
				}

				setNotice( describe( result ) );
				setForwardPostId( result.forward_post_id ?? 0 );

				// Nothing changed server side when nothing was split, so
				// there is nothing to refresh: neither the origin's entity
				// nor the owners its occurrence rows name. Returning the
				// refresh keeps the button busy until both are consistent
				// again.
				return result.split
					? refreshOriginEntity( isCurrent )
					: undefined;
			} )
			.catch( ( error ) => {
				if ( isCurrent() ) {
					// A refused split names its reason server side, e.g. a
					// series too long to split that far in. Rendering that
					// message is what makes the refusal actionable; the
					// generic line is only for failures that carry none.
					setNotice(
						error?.message ||
							__( 'Could not split this series.', 'gatherpress' )
					);
				}
			} )
			.finally( () => {
				if ( isCurrent() ) {
					setIsSplitting( false );
				}
			} );
	};

	return (
		<div className="gatherpress-recurrence-panel__scope">
			<RadioControl
				label={ __( 'Applying changes', 'gatherpress' ) }
				selected={ scope }
				options={ [
					{
						label: __( 'Apply retroactively', 'gatherpress' ),
						value: 'retroactive',
					},
					{
						label: __( 'Apply going forward', 'gatherpress' ),
						value: 'forward',
					},
				] }
				onChange={ setScope }
			/>
			{ 'forward' === scope && (
				<>
					<SelectControl
						label={ __( 'Split from', 'gatherpress' ) }
						value={ splitFrom }
						options={ occurrences.map( ( occurrence ) => ( {
							label: dateI18n(
								getSettings().formats.datetime,
								occurrence.datetime_start
							),
							value: occurrence.recurrence_id,
						} ) ) }
						onChange={ setSplitFrom }
					/>
					{ splitFrom === occurrences[ 0 ]?.recurrence_id && (
						<p>
							{ __(
								'If this is the series’ first date, splitting here applies the change to the whole series.',
								'gatherpress'
							) }
						</p>
					) }
					{ isDirty && (
						<p>
							{ __(
								'Save or discard your current changes before splitting.',
								'gatherpress'
							) }
						</p>
					) }
					<Button
						variant="secondary"
						isBusy={ isSplitting }
						disabled={ isSplitting || ! splitFrom || isDirty }
						onClick={ handleSplit }
					>
						{ __( 'Split series', 'gatherpress' ) }
					</Button>
				</>
			) }
			{ '' !== notice && <output>{ notice }</output> }
			{ 0 < forwardPostId && (
				<a
					href={ addQueryArgs( 'post.php', {
						post: forwardPostId,
						action: 'edit',
					} ) }
				>
					{ __( 'Edit the new event', 'gatherpress' ) }
				</a>
			) }
		</div>
	);
};

export default ApplyScope;
