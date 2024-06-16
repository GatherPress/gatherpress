/**
 * WordPress dependencies.
 */
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import axios from 'axios';

/**
 * Internal dependencies.
 */
import { Broadcaster, Listener } from '../helpers/broadcasting';

/**
 * VenueInformation component for GatherPress.
 *
 * This component allows users to input and update venue information, including full address,
 * phone number, and website. It uses the `TextControl` component from the Gutenberg editor
 * package to provide input fields for each type of information. The entered data is stored
 * in post meta as JSON and updated using the `editPost` method from the editor package.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const VenueInformation = () => {
	const editPost = useDispatch('core/editor').editPost;
	const updateVenueMeta = (metaData) => {
		const payload = JSON.stringify({
			...venueInformationMetaData,
			...metaData,
		});
		const meta = { gatherpress_venue_information: payload };

		editPost({ meta });
	};

	let venueInformationMetaData = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('meta')
				.gatherpress_venue_information
	);

	if (venueInformationMetaData) {
		venueInformationMetaData = JSON.parse(venueInformationMetaData);
	} else {
		venueInformationMetaData = {};
	}

	const [fullAddress, setFullAddress] = useState(
		venueInformationMetaData.fullAddress ?? ''
	);
	const [phoneNumber, setPhoneNumber] = useState(
		venueInformationMetaData.phoneNumber ?? ''
	);
	const [website, setWebsite] = useState(
		venueInformationMetaData.website ?? ''
	);

	Listener({ setFullAddress, setPhoneNumber, setWebsite });

	useEffect(() => {
		const getData = setTimeout(() => {
			let lat = 0;
			let lng = 0;
			axios
				.get(
					`https://nominatim.openstreetmap.org/search?q=${fullAddress}&format=geojson`
				)
				.then((res) => {
					if ( typeof res.data.features.length !== 0 && typeof res.data.features[0]?.geometry !== 'undefined') {
						lat = res.data.features[0].geometry.coordinates[1];
						lng = res.data.features[0].geometry.coordinates[0];
					}
					updateVenueMeta({
						latitude: lat,
						longitude: lng,
					});
				})
				.catch((err) => {
					console.log(err)
				});
		}, 2000)

		return () => clearTimeout(getData)
	}, [fullAddress, updateVenueMeta]);

	return (
		<>
			<TextControl
				label={__('Full Address', 'gatherpress')}
				value={fullAddress}
				onChange={(value) => {
					Broadcaster({ setFullAddress: value });
					updateVenueMeta({'fullAddress': value});
				}}
			/>
			<TextControl
				label={__('Phone Number', 'gatherpress')}
				value={phoneNumber}
				onChange={(value) => {
					Broadcaster({ setPhoneNumber: value });
					updateVenueMeta({ setPhoneNumber: value });
				}}
			/>
			<TextControl
				label={__('Website', 'gatherpress')}
				value={website}
				type="url"
				onChange={(value) => {
					Broadcaster({ setWebsite: value });
					updateVenueMeta({ setWebsite: value });
				}}
			/>
		</>
	);
};

export default VenueInformation;
