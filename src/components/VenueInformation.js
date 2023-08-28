/**
 * External dependencies.
 */
import HtmlReactParser from 'html-react-parser';

/**
 * WordPress dependencies.
 */
import { Flex, FlexItem, Icon } from '@wordpress/components';
import OnlineEvent from './OnlineEvent';
import { useSelect } from '@wordpress/data';

const VenueInformation = ({
	name,
	fullAddress,
	phoneNumber,
	website,
	onlineEvent = null,
}) => {
	const onlineEventLink = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('meta')
				._online_event_link
	);

	return (
		<>
			{!onlineEvent && (name || fullAddress) && (
				<Flex justify="normal" align="flex-start" gap="4">
					<FlexItem display="flex" className="gp-venue__icon">
						<Icon icon="location" />
					</FlexItem>
					<FlexItem>
						{name && (
							<div className="gp-venue_information__name has-medium-font-size">
								<strong>{HtmlReactParser(name)}</strong>
							</div>
						)}
						{fullAddress && (
							<div className="gp-venue__full-address">
								{HtmlReactParser(fullAddress)}
							</div>
						)}
					</FlexItem>
				</Flex>
			)}
			{!onlineEvent && (phoneNumber || website) && (
				<Flex justify="normal" gap="8">
					{phoneNumber && (
						<FlexItem>
							<Flex justify="normal" gap="4">
								<FlexItem
									display="flex"
									className="gp-venue__icon"
								>
									<Icon icon="phone" />
								</FlexItem>
								<FlexItem>
									<div className="gp-venue__phone-number">
										{phoneNumber}
									</div>
								</FlexItem>
							</Flex>
						</FlexItem>
					)}
					{website && (
						<FlexItem>
							<Flex justify="normal" gap="4">
								<FlexItem
									display="flex"
									className="gp-venue__icon"
								>
									<Icon icon="admin-site-alt3" />
								</FlexItem>
								<FlexItem>
									<div className="gp-venue__website">
										<a
											href={website}
											target="_blank"
											rel="noreferrer noopener"
										>
											{website}
										</a>
									</div>
								</FlexItem>
							</Flex>
						</FlexItem>
					)}
				</Flex>
			)}
			{onlineEvent && <OnlineEvent onlineEventLink={onlineEventLink} />}
		</>
	);
};

export default VenueInformation;
