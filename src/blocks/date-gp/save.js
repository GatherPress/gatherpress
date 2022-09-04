import { useBlockProps } from '@wordpress/block-editor';

import {
	__experimentalGrid as Grid,
	__experimentalText as Text,
} from '@wordpress/components';


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
	return (
        <div {...blockPropsSave}>
			<Grid columns={2}>
				{GatherPress.event_datetime.datetime_start ?
					<Text>
						<p>The Start Date-Time:</p>
						<p>{GatherPress.event_datetime.datetime_start}</p>
					</Text> :
					<Text>No date defined</Text>
				}
				{ GatherPress.event_datetime.datetime_end ?
						<Text>
						<p>The EndDate-Time:</p>
						<p>{GatherPress.event_datetime.datetime_end}</p>
						</Text> :
						<Text>No date defined</Text>
				}
			</Grid>
        </div>
    );
}
