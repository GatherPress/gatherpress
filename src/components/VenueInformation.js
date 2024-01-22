/**
 * WordPress dependencies.
 */
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';

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
	const updateVenueMeta = (key, value) => {
		const payload = JSON.stringify({
			...venueInformationMetaData,
			[key]: value,
		});
		const meta = { _venue_information: payload };

		editPost({ meta });
	};

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

	return (
		<>
			<TextControl
				label={__('Full Address', 'gatherpress')}
				value={fullAddress}
				onChange={(value) => {
					Broadcaster({ setFullAddress: value });
					updateVenueMeta('fullAddress', value);
				}}
			/>
			<TextControl
				label={__('Phone Number', 'gatherpress')}
				value={phoneNumber}
				onChange={(value) => {
					Broadcaster({ setPhoneNumber: value });
					updateVenueMeta('phoneNumber', value);
				}}
			/>
			<TextControl
				label={__('Website', 'gatherpress')}
				value={website}
				type="url"
				onChange={(value) => {
					Broadcaster({ setWebsite: value });
					updateVenueMeta('website', value);
				}}
			/>
		</>
	);
};

export default VenueInformation;
