/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';
import { useCallback, useRef } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	getTimeOfDay,
	toDayEnd,
	toDayStart,
	withTimeOfDay,
} from '../helpers/datetime';

/**
 * AllDay component.
 *
 * Toggles whether an event runs for whole days rather than at a time, stored
 * as the `gatherpress_is_all_day` post meta.
 *
 * Turning it on snaps the stored start and end to the day's boundaries rather
 * than leaving a time hidden underneath, so exports, duration and date
 * queries stay correct. PHP enforces the same boundaries on save, so a write
 * that never passes through the editor lands on them too.
 *
 * Turning it back off restores the times it replaced, onto whatever dates are
 * selected by then, so flipping the toggle to look at it does not cost the
 * author what they had. Those times are remembered for the session only: an
 * event saved as all-day no longer has other times to come back to, which is
 * the point of storing a real full-day span.
 *
 * @since 0.36.0
 *
 * @return {JSX.Element} A toggle for all-day events.
 */
const AllDay = () => {
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );
	const { setDateTimeStart, setDateTimeEnd } = useDispatch(
		'gatherpress/datetime'
	);

	const isAllDay = useSelect(
		( select ) =>
			Boolean(
				select( 'core/editor' ).getEditedPostAttribute( 'meta' )
					?.gatherpress_is_all_day
			),
		[]
	);

	const { dateTimeStart, dateTimeEnd } = useSelect(
		( select ) => ( {
			dateTimeStart: select( 'gatherpress/datetime' ).getDateTimeStart(),
			dateTimeEnd: select( 'gatherpress/datetime' ).getDateTimeEnd(),
		} ),
		[]
	);

	// Seeded with what the post loaded holding, so the first toggle off has
	// something to restore even when nothing was turned on this session.
	const previous = useRef( {
		start: getTimeOfDay( dateTimeStart ),
		end: getTimeOfDay( dateTimeEnd ),
	} );

	const updateAllDay = useCallback(
		( value ) => {
			editPost( { meta: { gatherpress_is_all_day: Boolean( value ) } } );

			if ( value ) {
				previous.current = {
					start: getTimeOfDay( dateTimeStart ),
					end: getTimeOfDay( dateTimeEnd ),
				};

				setDateTimeStart( toDayStart( dateTimeStart ) );
				setDateTimeEnd( toDayEnd( dateTimeEnd ) );
			} else {
				setDateTimeStart(
					withTimeOfDay( dateTimeStart, previous.current.start ),
				);
				setDateTimeEnd( withTimeOfDay( dateTimeEnd, previous.current.end ) );
			}

			unlockPostSaving();
		},
		[
			editPost,
			unlockPostSaving,
			setDateTimeStart,
			setDateTimeEnd,
			dateTimeStart,
			dateTimeEnd,
		]
	);

	return (
		<ToggleControl
			label={ __( 'All day', 'gatherpress' ) }
			checked={ isAllDay }
			onChange={ updateAllDay }
		/>
	);
};

export default AllDay;
