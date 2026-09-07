/**
 * Internal dependencies
 */
import { getFromConfig } from './editor-settings';

/**
 * Slug reported for an event that has no status of its own.
 *
 * @since 0.36.0
 *
 * @type {string}
 */
export const DEFAULT_STATUS = 'scheduled';

/**
 * Every status an event can be in, as PHP published it.
 *
 * The vocabulary is stated once in `Event\Status` and travels to the editor
 * through the block editor settings, so a site that registers its own status
 * gets it here without touching JavaScript. Labels arrive translated.
 *
 * @since 0.36.0
 *
 * @return {Object} Statuses keyed by slug, or an empty object before settings load.
 */
export function getStatuses() {
	const statuses = getFromConfig( 'eventStatuses' );

	return statuses && 'object' === typeof statuses ? statuses : {};
}

/**
 * One status's definition, falling back to the scheduled one.
 *
 * @since 0.36.0
 *
 * @param {string} slug The status slug.
 *
 * @return {Object} The definition, or an empty object when none is known.
 */
function getStatus( slug ) {
	const statuses = getStatuses();

	return statuses[ slug ] || statuses[ DEFAULT_STATUS ] || {};
}

/**
 * The words shown for a status.
 *
 * @since 0.36.0
 *
 * @param {string} slug The status slug.
 *
 * @return {string} The label, or the slug itself before settings load.
 */
export function getStatusLabel( slug ) {
	return getStatus( slug ).label || slug;
}

/**
 * The sentence explaining what a status means.
 *
 * @since 0.36.0
 *
 * @param {string} slug The status slug.
 *
 * @return {string} The description, or an empty string when none is known.
 */
export function getStatusDescription( slug ) {
	return getStatus( slug ).description || '';
}

/**
 * The statuses shaped for a SelectControl.
 *
 * @since 0.36.0
 *
 * @return {Array} Options of label and value, in the order PHP offers them.
 */
export function getStatusOptions() {
	return Object.entries( getStatuses() ).map( ( [ value, status ] ) => ( {
		label: status?.label || value,
		value,
	} ) );
}
