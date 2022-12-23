/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-block-editor/#useBlockProps
 */
import { useBlockProps } from '@wordpress/block-editor';
import { useSelect, useDispatch } from '@wordpress/data';

import VenueInformation from '../../components/VenueInformation';

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

	// let venueInformationMetaData = useSelect(
	// 	( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' )._venue_information,
	// );

	// if ( venueInformationMetaData ) {
	// 	venueInformationMetaData = JSON.parse( venueInformationMetaData );
	// }

	const blockPropsSave = useBlockProps.save();
	return (
        <div {...blockPropsSave}>
            <VenueInformation />
            <p>get_post({ attributes.venueId })</p>
            <p>attributes {JSON.stringify(attributes)}</p>
            {/* <p>venueInformationMetaData {JSON.stringify(venueInformationMetaData)}</p> */}
        </div>
    );
}
