/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-block-editor/#useBlockProps
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Fragment, createElement } from '@wordpress/element';

/**
 * The save function defines the way in which the different attributes should
 * be combined into the final markup, which is then serialized by the block
 * editor into `post_content`.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#save
 *
 * @param {Object} props            Properties passed to the function.
 * @param {Object} props.attributes Available block attributes.
 * @return {WPElement} Element to render.
 */
export default function save({ attributes }) {
	const blockPropsSave = useBlockProps.save();
    const { datetime } = attributes;
	return (
        <div {...blockPropsSave}>
            <p>{attributes.message}</p>
            <div>
            { datetime ?
                <>
                    <p>The Date-Time:</p>
                    <p>{datetime}</p>
                </> :
                    <p>No date defined</p>
                }
            </div>
        </div>
    );
}
