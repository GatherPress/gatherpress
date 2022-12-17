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
export default function save(props) {
	return (
		<div { ...useBlockProps.save() }>
			<p>{'Events List – hello from the saved content!'}</p>
			<h4>{props.attributes.maxNumberOfEvents}</h4>
			<p>{JSON.stringify(props.attributes.eventOptions)}</p>
			<p>{'Loop thru a list of events from attributes'}</p>
		</div>
	);
}
