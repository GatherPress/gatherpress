/**
 * WordPress dependencies.
 */
import { select } from '@wordpress/data';

/**
 * Check if the current post type is a venue.
 *
 * This function determines whether the current post type in the WordPress editor
 * is associated with venue content.
 *
 * @since 1.0.0
 *
 * @return {boolean} True if the current post type is a venue; false otherwise.
 */
export function isVenuePostType() {
	return 'gatherpress_venue' === select( 'core/editor' )?.getCurrentPostType();
}
