/**
 * WordPress dependencies
 */
import { ToggleControl } from '@wordpress/components';
import { __, _x, sprintf } from '@wordpress/i18n';

/**
 * A component that lets you include the current event from the query
 *
 * @param {*} props
 * @return {Element} EventIncludeUnfinishedControls
 */
export const EventIncludeUnfinishedControls = ({
	attributes,
	setAttributes,
}) => {
	const { query: { include_unfinished: includeUnfinished } = {} } =
		attributes;

	return (
		<>
			<ToggleControl
				label={__('Include unfinished Events', 'gatherpress')}
				help={sprintf(
					/* translators: %s: 'upcoming' or 'past' */
					_x(
						'%s events that have started but are not yet finished.',
						"'Shows' or 'Hides'",
						'gatherpress'
					),
					includeUnfinished
						? __('Shows', 'gatherpress')
						: __('Hides', 'gatherpress')
				)}
				checked={includeUnfinished}
				onChange={(value) => {
					setAttributes({
						query: {
							...attributes.query,
							include_unfinished: value ? 1 : 0,
						},
					});
				}}
			/>
		</>
	);
};
