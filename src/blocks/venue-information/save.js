/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-i18n/
 */
import { __ } from "@wordpress/i18n";
import { useBlockProps } from "@wordpress/block-editor";
import GoogleMap from './googlemap';

/**
 * The save function defines the way in which the different attributes should
 * be combined into the final markup, which is then serialized by the block
 * editor into `post_content`.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#save
 *
 * @return {WPElement} Element to render.
 */
export default function save({attributes}) {

// const Edit = ( { attributes, setAttributes, isSelected, clientId } ) => {
	const {
		blockId,
		fullAddress,
		phoneNumber,
		website,
		zoom,
		type,
		deskHeight,
		tabHeight,
		mobileHeight,
		device,
	} = attributes;

	const blockProps = useBlockProps.save();
	return (
		<div {...blockProps}>
			{fullAddress && (
				<p>{fullAddress}</p>
			)}
			{phoneNumber && (
				<p>{phoneNumber}</p>
			)}
			{website && (
				<p>{website}</p>
			)}
			{fullAddress && (
				<GoogleMap
					location={fullAddress}
					zoom={zoom}
					type={type}
					height={deskHeight}
				/>
			)}
		</div>
	);
}
