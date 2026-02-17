/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */
import { usePopularVenues, getVenueTitle } from '../helpers/venue';

/**
 * PopularVenues component.
 *
 * Displays a list of the most frequently used venues as quick-select buttons.
 * Only shown when there are popular venues available and the user is in an event context.
 *
 * @since 1.0.0
 *
 * @param {Object}   props           Component props.
 * @param {Function} props.onSelect  Callback function when a venue is selected.
 * @param {number}   props.currentId Currently selected venue ID (to highlight/disable it).
 *
 * @return {JSX.Element|null} Popular venues list or null if no venues.
 */
export default function PopularVenues( { onSelect, currentId } ) {
	const popularVenues = usePopularVenues( 3 );

	// Don't render if there are no popular venues.
	if ( ! popularVenues || 0 === popularVenues.length ) {
		return null;
	}

	return (
		<div className="gatherpress-popular-venues">
			<p className="gatherpress-popular-venues__label">
				{ __( 'Popular venues:', 'gatherpress' ) }{ ' ' }
				{ popularVenues.map( ( venue ) => {
					const isSelected = currentId === venue.id;
					const venueName = decodeEntities(
						getVenueTitle( venue, 'taxonomy' )
					);
					return (
						<Button
							key={ venue.id }
							variant="link"
							className={
								isSelected
									? 'gatherpress-popular-venue gatherpress-popular-venue--selected'
									: 'gatherpress-popular-venue'
							}
							onClick={ () => {
								if ( ! isSelected ) {
									onSelect( venue.id );
								}
							} }
							disabled={ isSelected }
							aria-label={
								isSelected
									? sprintf(
										/* translators: %s: venue name */
										__(
											'%s (currently selected)',
											'gatherpress'
										),
										venueName
									)
									: sprintf(
										/* translators: %s: venue name */
										__( 'Select %s', 'gatherpress' ),
										venueName
									)
							}
							aria-current={ isSelected ? 'true' : undefined }
						>
							{ venueName }
						</Button>
					);
				} ) }
			</p>
		</div>
	);
}
