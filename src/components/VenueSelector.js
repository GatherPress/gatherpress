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
	// eslint-disable-next-line no-unused-vars
	const [name, setName] = useState('');
	// eslint-disable-next-line no-unused-vars
	const [fullAddress, setFullAddress] = useState('');
	// eslint-disable-next-line no-unused-vars
	const [phoneNumber, setPhoneNumber] = useState('');
	// eslint-disable-next-line no-unused-vars
	const [website, setWebsite] = useState('');
	// eslint-disable-next-line no-unused-vars
	const [isOnlineEventTerm, setIsOnlineEventTerm] = useState(false);

	const [venue, setVenue] = useState('');
	const editPost = useDispatch('core/editor').editPost;
	const { unlockPostSaving } = useDispatch('core/editor');
	const venueTermId = useSelect((select) =>
		select('core/editor').getEditedPostAttribute('_gp_venue')
	);
	const venueTerm = useSelect((select) =>
		select('core').getEntityRecord('taxonomy', '_gp_venue', venueTermId)
	);
	const slug = venueTerm?.slug.replace(/^_/, '');
	const [venueSlug, setVenueSlug] = useState('');
	const venueValue = venueTermId + ':' + venueSlug;
	const venuePost = useSelect((select) =>
		select('core').getEntityRecords('postType', 'gp_venue', {
			per_page: 1,
			slug: venueSlug,
		})
	);

	useEffect(() => {
		let venueInformation = {};

		if (venueSlug && Array.isArray(venuePost)) {
			const jsonString = venuePost[0]?.meta?._venue_information ?? '{}';

			if (jsonString) {
				venueInformation = JSON.parse(jsonString);
				venueInformation.name = venuePost[0]?.title.rendered ?? '';
			}
		}

		const nameUpdated =
			venueInformation?.name ?? __('No venue selected.', 'gatherpress');
		const fullAddressUpdated = venueInformation?.fullAddress ?? '';
		const phoneNumberUpdated = venueInformation?.phoneNumber ?? '';
		const websiteUpdated = venueInformation?.website ?? '';

		// Will unset the venue if slug is `undefined` here.
		if (slug) {
			setVenueSlug(slug);
		}

		setVenue(venueValue ? String(venueValue) : '');

		setName(nameUpdated);
		setFullAddress(fullAddressUpdated);
		setPhoneNumber(phoneNumberUpdated);
		setWebsite(websiteUpdated);

		Broadcaster({
			setName: nameUpdated,
			setFullAddress: fullAddressUpdated,
			setPhoneNumber: phoneNumberUpdated,
			setWebsite: websiteUpdated,
			setIsOnlineEventTerm: venueSlug === 'online-event',
		});
	}, [venueSlug, venuePost, slug, venueValue]);

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
		unlockPostSaving();
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
