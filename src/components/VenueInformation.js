/**
 * WordPress dependencies.
 */
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { Broadcaster, Listener } from '../helpers/broadcasting';

const updateVenueMeta = (key, value, editPost, venueInformationMetaData) => {
	const payload = JSON.stringify({
		...venueInformationMetaData,
		[key]: value,
	});
	const meta = { _venue_information: payload };

	Broadcaster({ setFullAddress: value });
	editPost({ meta });
};

export const FullAddress = () => {
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

	const [fullAddress, setFullAddress] = useState(
		venueInformationMetaData.fullAddress ?? ''
	);

	Listener({ setFullAddress });

	return (
		<TextControl
			label={__('Full Address', 'gatherpress')}
			value={fullAddress}
			onChange={(value) => {
				setFullAddress(value);
				updateVenueMeta(
					'fullAddress',
					value,
					editPost,
					venueInformationMetaData
				);
			}}
		/>
	);
};

export const PhoneNumber = () => {
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

	const [phoneNumber, setPhoneNumber] = useState(
		venueInformationMetaData.phoneNumber ?? ''
	);

	Listener({ setPhoneNumber });

	return (
		<TextControl
			label={__('Phone Number', 'gatherpress')}
			value={phoneNumber}
			onChange={(value) => {
				setPhoneNumber(value);
				updateVenueMeta(
					'phoneNumber',
					value,
					editPost,
					venueInformationMetaData
				);
			}}
		/>
	);
};

export const Website = () => {
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

	const [website, setWebsite] = useState(
		venueInformationMetaData.website ?? ''
	);

	Listener({ setWebsite });

	return (
		<TextControl
			label={__('Website', 'gatherpress')}
			value={website}
			type="url"
			onChange={(value) => {
				setWebsite(value);
				updateVenueMeta(
					'website',
					value,
					editPost,
					venueInformationMetaData
				);
			}}
		/>
	);
};
