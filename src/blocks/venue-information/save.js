import { useBlockProps } from '@wordpress/block-editor';

import { Icon } from '@wordpress/components';

import MapEmbed from '../../helpers/map-embed';

export default function save({ attributes }) {
	const { mapId, fullAddress, phoneNumber, website, zoom, type, deskHeight } =
		attributes;

	const blockProps = useBlockProps.save();

	return (
		<div {...blockProps}>
			{fullAddress && (
				<>
					<address className="gp-venue__full-address-row">
						<span className="gp-venue__icon">
							<Icon icon="location" />
						</span>
						<span>
							{fullAddress && (
								<div className="gp-venue__full-address">
									{fullAddress}
								</div>
							)}
						</span>
					</address>
				</>
			)}
			{fullAddress && (
				<>
					<div className="gp-venue__map">
						<MapEmbed
							location={fullAddress}
							zoom={zoom}
							type={type}
							height={deskHeight}
							className={`embed-height_${mapId}`}
						/>
					</div>
				</>
			)}
			<div className="gp-venue__row">
				{phoneNumber && (
					<>
						<phone className="gp-venue__phone-row">
							<span className="gp-venue__icon">
								<Icon icon="phone" />
							</span>
							<span>
								<div className="gp-venue__phone">
									{phoneNumber}
								</div>
							</span>
						</phone>
					</>
				)}
				{website && (
					<>
						<website className="gp-venue__website-row">
							<span className="gp-venue__icon">
								<Icon icon="admin-site-alt3" />
							</span>
							<span>
								<div className="gp-venue__website">
									{website}
								</div>
							</span>
						</website>
					</>
				)}
			</div>
		</div>
	);
}
