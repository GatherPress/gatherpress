/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { Listener } from '../../helpers/broadcasting';
import VenueInformation from './venue-info';


const Edit = (props) => {
	const { setAttributes } = props;
	const blockProps = useBlockProps();
	const [venueId, setVenueId] = useState('');

	Listener({ setVenueId });

	useEffect(() => {
		setAttributes({
			venueId: venueId ?? '',
		});
	});

	const VenueSelector = ({ id }) => {
		const venuePost = useSelect((select) =>
			select('core').getEntityRecord('postType', 'gp_venue', id)
		);

		let jsonString = venuePost?.meta._venue_information ?? '{}';
		jsonString = '' !== jsonString ? jsonString : '{}';

		const venueInformation = JSON.parse(jsonString);
		const fullAddress = venueInformation?.fullAddress ?? '';
		const phoneNumber = venueInformation?.phoneNumber ?? '';
		const website = venueInformation?.website ?? '';
		const name =
			venuePost?.title.rendered ??
			__('No venue selected.', 'gatherpress');

		return (
			<VenueInformation
				name={name}
				fullAddress={fullAddress}
				phoneNumber={phoneNumber}
				website={website}
			/>
		);
	};

	return (
		<div {...blockProps}>
			<VenueSelector id={venueId} />
		</div>
	);
};

export default Edit;
