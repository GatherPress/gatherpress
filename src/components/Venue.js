/**
 * External dependencies.
 */
import HtmlReactParser from 'html-react-parser';

/**
 * WordPress dependencies.
 */
import { Flex, FlexItem, Icon } from '@wordpress/components';

/**
 * Venue component for GatherPress.
 *
 * This component displays information about a venue, including its name, full address,
 * phone number, and website. It utilizes the Flex component to arrange the information
 * in a visually appealing manner. Icons are used to represent location, phone, and website.
 *
 * @since 1.0.0
 *
 * @param {Object} props             - Component props.
 * @param {string} props.name        - The name of the venue.
 * @param {string} props.fullAddress - The full address of the venue.
 * @param {string} props.phoneNumber - The phone number of the venue.
 * @param {string} props.website     - The website URL of the venue.
 *
 * @return {JSX.Element} The rendered React component.
 */
const Venue = ( { name, fullAddress, phoneNumber, website } ) => {
	return (
		<>
			{ ( name || fullAddress ) && (
				<Flex justify="normal" align="flex-start" gap="4">
					<FlexItem
						display="flex"
						className="gatherpress-venue__icon"
					>
						<Icon icon="location" />
					</FlexItem>
					<FlexItem>
						{ name && (
							<div className="gatherpress-venue__name">
								<strong>{ HtmlReactParser( name ) }</strong>
							</div>
						) }
						{ fullAddress && (
							<div className="gatherpress-venue__full-address">
								{ HtmlReactParser( fullAddress ) }
							</div>
						) }
					</FlexItem>
				</Flex>
			) }
			{ ( phoneNumber || website ) && (
				<Flex justify="normal" gap="8">
					{ phoneNumber && (
						<FlexItem>
							<Flex justify="normal" gap="4">
								<FlexItem
									display="flex"
									className="gatherpress-venue__icon"
								>
									<Icon icon="phone" />
								</FlexItem>
								<FlexItem>
									<div className="gatherpress-venue__phone-number">
										{ phoneNumber }
									</div>
								</FlexItem>
							</Flex>
						</FlexItem>
					) }
					{ website && (
						<FlexItem>
							<Flex justify="normal" gap="4">
								<FlexItem
									display="flex"
									className="gatherpress-venue__icon"
								>
									<Icon icon="admin-site-alt3" />
								</FlexItem>
								<FlexItem>
									<div className="gatherpress-venue__website">
										<a
											href={ website }
											target="_blank"
											rel="noreferrer noopener"
										>
											{ website }
										</a>
									</div>
								</FlexItem>
							</Flex>
						</FlexItem>
					) }
				</Flex>
			) }
		</>
	);
};

export default Venue;
