/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { PanelRow, SelectControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Broadcaster } from '../helpers/broadcasting';

const VenueSelector = () => {
	const [name, setName] = useState('');
	const [fullAddress, setFullAddress] = useState('');
	const [phoneNumber, setPhoneNumber] = useState('');
	const [website, setWebsite] = useState('');

	const [venue, setVenue] = useState('');
	const editPost = useDispatch('core/editor').editPost;
	const { unlockPostSaving } = useDispatch('core/editor');
	const venueTermId = useSelect((select) =>
		select('core/editor').getEditedPostAttribute('_gp_venue')
	);
	const venueTerm = useSelect((select) =>
		select('core').getEntityRecord('taxonomy', '_gp_venue', venueTermId)
	);
	const [venueSlug, setVenueSlug] = useState(
		venueTerm?.slug.replace(/^_/, '')
	);
	const venueValue = venueTermId + ':' + venueSlug;
	const venuePost = useSelect((select) =>
		select('core').getEntityRecords('postType', 'gp_venue', {
			per_page: 1,
			slug: venueSlug,
		})
	);

	useEffect(() => {
		if (venueSlug && Array.isArray(venuePost)) {
			const jsonString = venuePost[0]?.meta?._venue_information ?? '{}';
			const nameUpdated = venuePost[0]?.title.rendered ?? '';

			if (jsonString) {
				const venueInformation = JSON.parse(jsonString);
				const fullAddressUpdated = venueInformation?.fullAddress ?? '';
				const phoneNumberUpdated = venueInformation?.phoneNumber ?? '';
				const websiteUpdated = venueInformation?.website ?? '';

				setName(nameUpdated);
				setFullAddress(fullAddressUpdated);
				setPhoneNumber(phoneNumberUpdated);
				setWebsite(websiteUpdated);

				Broadcaster({
					setName: nameUpdated,
					setFullAddress: fullAddressUpdated,
					setPhoneNumber: phoneNumberUpdated,
					setWebsite: websiteUpdated,
				});
			}
		}
		// console.log(venuePost?.meta);
	}, [venueSlug, venuePost]);

	// useEffect(() => {
	// 	setVenue(String(venueValue) ?? '');
	// 	Broadcaster({
	// 		setVenueSlug: venueSlug,
	// 	});
	// }, [venueValue, venueSlug]);

	let venues = useSelect((select) => {
		return select('core').getEntityRecords('taxonomy', '_gp_venue', {
			per_page: -1,
			context: 'view',
		});
	}, []);

	if (venues) {
		venues = venues.map((item) => ({
			label: item.name,
			value: item.id + ':' + item.slug.replace(/^_/, ''),
		}));

		venues.unshift({
			value: ':',
			label: __('Choose a venue', 'gatherpress'),
		});
	} else {
		venues = [];
	}

	const updateTerm = (value) => {
		setVenue(value);
		value = value.split(':');

		const term = '' !== value[0] ? [value[0]] : [];

		editPost({ _gp_venue: term });
		setVenueSlug(value[1]);
		// Broadcaster({
		// 	setVenueSlug: value[1],
		// });
		unlockPostSaving();

		// let jsonString = '';
		// let venueTest = null;
		//
		// if (Array.isArray(venuePost)) {
		// 	venueTest = venuePost[0];
		// 	jsonString = venueTest?.meta?._venue_information ?? '{}';
		// }
		//
		// let name =
		// 	venue?.title.rendered ?? __('No venue selected.', 'gatherpress');
		//
		// jsonString = '' !== jsonString ? jsonString : '{}';
		//
		// const venueInformation = JSON.parse(jsonString);
		//
		// setName(
		// 	venueTest?.title.rendered ?? __('No venue selected.', 'gatherpress')
		// );
		// setFullAddress(venueInformation?.fullAddress ?? '');
		// setPhoneNumber(venueInformation?.phoneNumber ?? '');
		// setWebsite(venueInformation?.website ?? '');
		//
		// Broadcaster({
		// 	setName: name,
		// 	setFullAddress: fullAddress,
		// 	setPhoneNumber: phoneNumber,
		// 	setWebsite: website,
		// });
	};

	return (
		<PanelRow>
			<SelectControl
				label={__('Venue Selector', 'gatherpress')}
				value={venue}
				onChange={(value) => {
					updateTerm(value);
				}}
				options={venues}
			/>
		</PanelRow>
	);
};

export default VenueSelector;
