/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import {  Flex, FlexBlock, FlexItem, Icon, TextControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';

const Edit = ( props ) => {
	const { attributes, setAttributes, isSelected } = props;
	const {
		fullAddress,
		phoneNumber,
		website
	} = attributes;
	const blockProps = useBlockProps();
	const editPost = useDispatch( 'core/editor' ).editPost;
	const venueInformationMetaData = JSON.parse( useSelect(
		( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' )._venue_information,
	) );

	const onUpdate = ( key, value ) => {
		const payload = JSON.stringify( { ...venueInformationMetaData, [ key ]: value } );
		const meta = { _venue_information: payload };

		setAttributes( { [ key ]: value } );
		editPost( { meta } );
	};

	return (
		<div { ...blockProps }>
			{ ! isSelected && (
				<>
					<Flex justify="normal">
						<FlexItem display="flex">
							<Icon icon="location" />
						</FlexItem>
						<FlexItem>
							{fullAddress}
						</FlexItem>
					</Flex>
					<Flex justify="normal" gap="4">
						<FlexItem>
							<Flex justify="normal">
								<FlexItem display="flex">
									<Icon icon="phone" />
								</FlexItem>
								<FlexItem>
									{phoneNumber}
								</FlexItem>
							</Flex>
						</FlexItem>
						<FlexItem>
							<Flex justify="normal">
								<FlexItem display="flex">
									<Icon icon="admin-site-alt3" />
								</FlexItem>
								<FlexItem>
									<a href={website} target="_blank">{website}</a>
								</FlexItem>
							</Flex>
						</FlexItem>
					</Flex>
				</>
			) }
			{ isSelected && (
				<>
					<Flex>
						<FlexBlock>
							<TextControl
								label={ __( 'Full Address', 'gatherpress') }
								value={ fullAddress }
								onChange={ ( value ) => {
									onUpdate( 'fullAddress', value );
								} }
							/>
						</FlexBlock>
					</Flex>
					<Flex>
						<FlexBlock>
							<TextControl
								label={ __( 'Phone Number', 'gatherpress') }
								value={ phoneNumber }
								onChange={ ( value ) => {
									onUpdate( 'phoneNumber', value );
								} }
							/>
						</FlexBlock>
						<FlexBlock>
							<TextControl
								label={ __( 'Website', 'gatherpress') }
								value={ website }
								onChange={ ( value ) => {
									onUpdate( 'website', value );
								} }
							/>
						</FlexBlock>
					</Flex>
				</>
			) }
		</div>
	);
};

export default Edit;
