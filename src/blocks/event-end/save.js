import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * The save function defines the way in which the different attributes should
 * be combined into the final markup, which is then serialized by the block
 * editor into `post_content`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#save
 *
 * @return {WPElement} Element to render.
 */
export default function save() {
	return (
		<div { ...useBlockProps.save() }>
			<p>{ __(
				'Event End – ',
                'gatherpress-event'
			)}</p>
			<p>{GatherPress.event_datetime.datetime_end}</p>
		</div>
	);
}
