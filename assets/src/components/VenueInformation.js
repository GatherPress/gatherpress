import { Flex, FlexItem, Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const VenueInformation = ( { fullAddress, phoneNumber, website } ) => {
	return (
		<>
			{ fullAddress && (
				<Flex justify="normal">
					<FlexItem display="flex">
						<Icon icon="location" />
					</FlexItem>
					<FlexItem>
						{ fullAddress }
					</FlexItem>
				</Flex>
			) }
			{ ( phoneNumber || website ) && (
				<Flex justify="normal" gap="4">
					{ phoneNumber && (
						<FlexItem>
							<Flex justify="normal">
								<FlexItem display="flex">
									<Icon icon="phone" />
								</FlexItem>
								<FlexItem>
									{ phoneNumber }
								</FlexItem>
							</Flex>
						</FlexItem>
					) }
					{ website && (
						<FlexItem>
							<Flex justify="normal">
								<FlexItem display="flex">
									<Icon icon="admin-site-alt3" />
								</FlexItem>
								<FlexItem>
									<a href={ website } target="_blank" rel="noreferrer noopener">{ website }</a>
								</FlexItem>
							</Flex>
						</FlexItem>
					) }
				</Flex>
			) }
		</>
	);
};

export default VenueInformation;
