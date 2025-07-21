/**
 * WordPress dependencies
 */
import { RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * EventCountControls component
 *
 * @param {*} param0
 * @return {Element} EventCountControls
 */
export const EventCountControls = ({ attributes, setAttributes }) => {
	const { query: { perPage, offset = 0 } = {} } = attributes;

	return (
		<RangeControl
			label={__('Events Per Page', 'gatherpress')}
			min={1}
			max={50}
			onChange={(newCount) => {
				setAttributes({
					query: {
						...attributes.query,
						perPage: newCount,
						offset,
					},
				});
			}}
			value={perPage}
		/>
	);
};
