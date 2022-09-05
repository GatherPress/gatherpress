/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import {
	Flex,
	FlexBlock,
	FlexItem,
	Icon,
	TextControl,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

const Edit = (props) => {
	const { attributes, setAttributes, isSelected } = props;
	const { fullAddress, phoneNumber, website } = attributes;
	const blockProps = useBlockProps();
	const editPost = useDispatch('core/editor').editPost;
	let venueInformationMetaData = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('meta')
				._venue_information
	);

	if (venueInformationMetaData) {
		venueInformationMetaData = JSON.parse(venueInformationMetaData);
	} else {
		venueInformationMetaData = {};
	}

	const onUpdate = (key, value) => {
		const payload = JSON.stringify({
			...venueInformationMetaData,
			[key]: value,
		});
		const meta = { _venue_information: payload };

		setAttributes({ [key]: value });
		editPost({ meta });
	};

	useEffect(() => {
		setAttributes({
			fullAddress: venueInformationMetaData.fullAddress ?? '',
			phoneNumber: venueInformationMetaData.phoneNumber ?? '',
			website: venueInformationMetaData.website ?? '',
		});
	}, []);

	return (
		<div {...blockProps}>
			{!isSelected && (
				<>
					{!fullAddress && !phoneNumber && !website && (
						<Flex justify="normal">
							<FlexItem display="flex">
								<Icon icon="location" />
							</FlexItem>
							<FlexItem>
								<em>
									{__(
										'Add venue information.',
										'gatherpress'
									)}
								</em>
							</FlexItem>
						</Flex>
					)}
					{fullAddress && (
						<Flex justify="normal">
							<FlexItem display="flex">
								<Icon icon="location" />
							</FlexItem>
							<FlexItem>{fullAddress}</FlexItem>
						</Flex>
					)}
					{(phoneNumber || website) && (
						<Flex justify="normal" gap="4">
							{phoneNumber && (
								<FlexItem>
									<Flex justify="normal">
										<FlexItem display="flex">
											<Icon icon="phone" />
										</FlexItem>
										<FlexItem>{phoneNumber}</FlexItem>
									</Flex>
								</FlexItem>
							)}
							{website && (
								<FlexItem>
									<Flex justify="normal">
										<FlexItem display="flex">
											<Icon icon="admin-site-alt3" />
										</FlexItem>
										<FlexItem>
											<a
												href={website}
												target="_blank"
												rel="noreferrer noopener"
											>
												{website}
											</a>
										</FlexItem>
									</Flex>
								</FlexItem>
							)}
						</Flex>
					)}
				</>
			)}
			{isSelected && (
				<>
					<Flex>
						<FlexBlock>
							<TextControl
								label={__('Full Address', 'gatherpress')}
								value={fullAddress}
								onChange={(value) => {
									onUpdate('fullAddress', value);
								}}
							/>
						</FlexBlock>
					</Flex>
					<Flex>
						<FlexBlock>
							<TextControl
								label={__('Phone Number', 'gatherpress')}
								value={phoneNumber}
								onChange={(value) => {
									onUpdate('phoneNumber', value);
								}}
							/>
						</FlexBlock>
						<FlexBlock>
							<TextControl
								label={__('Website', 'gatherpress')}
								value={website}
								type="url"
								onChange={(value) => {
									onUpdate('website', value);
								}}
							/>
						</FlexBlock>
					</Flex>
				</>
			)}
		</div>
	);
};

export default Edit;
