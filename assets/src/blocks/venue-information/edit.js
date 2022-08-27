/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import {  Flex, FlexBlock, FlexItem, Icon, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */

const Edit = ( props ) => {
	const { attributes, setAttributes, isSelected } = props;
	const {
		address,
		city,
		stateOrProvince,
		postalCode,
		phoneNumber,
		website
	} = attributes;
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<Flex align="left" gap="4">
				<FlexItem>
					<Icon icon="location" />
				</FlexItem>
				<FlexItem>
					{ ! isSelected && (
						<span>{address} &bull; {city}, {stateOrProvince}</span>
					) }
					{ isSelected && (
						<>
							<Flex>
								<FlexBlock>
									<TextControl
										label={ __( 'Address', 'gatherpress') }
										value={ address }
										onChange={ ( value ) => setAttributes( { address: value } ) }
									/>
								</FlexBlock>
							</Flex>
							<Flex>
								<FlexBlock>
									<TextControl
										label={ __( 'City', 'gatherpress') }
										value={ city }
										onChange={ ( value ) => setAttributes( { city: value } ) }
									/>
								</FlexBlock>
								<FlexBlock>
									<TextControl
										label={ __( 'State or Province', 'gatherpress') }
										value={ stateOrProvince }
										onChange={ ( value ) => setAttributes( { stateOrProvince: value } ) }
									/>
								</FlexBlock>
								<FlexBlock>
									<TextControl
										label={ __( 'Postal Code', 'gatherpress') }
										value={ postalCode }
										onChange={ ( value ) => setAttributes( { postalCode: value } ) }
									/>
								</FlexBlock>
							</Flex>
							<Flex>
								<FlexBlock>
									<TextControl
										label={ __( 'Phone Number', 'gatherpress') }
										value={ phoneNumber }
										onChange={ ( value ) => setAttributes( { phoneNumber: value } ) }
									/>
								</FlexBlock>
								<FlexBlock>
									<TextControl
										label={ __( 'Website', 'gatherpress') }
										value={ website }
										onChange={ ( value ) => setAttributes( { website: value } ) }
									/>
								</FlexBlock>
							</Flex>
						</>
					) }
				</FlexItem>
			</Flex>
		</div>
	);
};

export default Edit;
