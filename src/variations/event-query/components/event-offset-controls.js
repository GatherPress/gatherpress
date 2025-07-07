/**
 * WordPress dependencies
 */

import { RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export const EventOffsetControls = ({ attributes, setAttributes }) => {
	const { query: { offset = 0 } = {} } = attributes;
	return (
		<RangeControl
			label={__('Event Offset', 'gatherpress')}
			min={0}
			max={50}
			value={offset}
			onChange={(newOffset) => {
				setAttributes({
					query: {
						...attributes.query,
						offset: newOffset,
					},
				});
			}}
		/>
	);
};
