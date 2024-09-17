import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';

/**
 * Predefined duration options for event scheduling.
 *
 * This array contains a list of duration options in hours that can be selected
 * for an event. Each option includes a label for display and a corresponding
 * value representing the duration in hours. The last option allows the user
 * to set a custom end time by selecting `false`.
 *
 * @since 1.0.0
 *
 * @type {Array<Object>} durationOptions
 * @property {string}         label - The human-readable label for the duration option.
 * @property {number|boolean} value - The value representing the duration in hours, or `false` if a custom end time is to be set.
 */

const DurationOptionsDefaults = [
	{
		label: __('1 hour', 'gatherpress'),
		value: 1,
	},
	{
		label: __('1.5 hours', 'gatherpress'),
		value: 1.5,
	},
	{
		label: __('2 hours', 'gatherpress'),
		value: 2,
	},
	{
		label: __('3 hours', 'gatherpress'),
		value: 3,
	},
	{
		label: __('Set an end time…', 'gatherpress'),
		value: false,
	},
];

export default applyFilters('gatherpress.durationOptions', DurationOptionsDefaults );
