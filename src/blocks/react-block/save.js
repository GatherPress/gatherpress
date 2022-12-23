/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-block-editor/#useBlockProps
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * In block.json we have a viewScript which allows for frontend stuff.
 * The `<div id="react-app">react-app</div>` is what that script is looking for.
 */
export default function save({ attributes }) {
	const blockPropsSave = useBlockProps.save();
	return (
        <div {...blockPropsSave}>
			<div id="react-app">react-app</div>
        </div>
    );
}
