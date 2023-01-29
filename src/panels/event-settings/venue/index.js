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
import { Broadcaster } from '../../../helpers/broadcasting';

const VenuePanel = () => {
	const [venue, setVenue] = useState('');
	const editPost = useDispatch('core/editor').editPost;
	const { unlockPostSaving } = useDispatch('core/editor');
	const venueTermId = useSelect((select) =>
		select('core/editor').getEditedPostAttribute('_gp_venue')
	);
	const venueTerm = useSelect((select) =>
		select('core').getEntityRecord('taxonomy', '_gp_venue', venueTermId)
	);
	const venueSlug = venueTerm?.slug.slice(1, venueTerm?.slug.length);
	const venueValue = venueTermId + ':' + venueSlug;
	useEffect(() => {
		setVenue(String(venueValue) ?? '');
		Broadcaster({
			setVenueSlug: venueSlug,
		});
	});

	let venues = useSelect(
		(select) => {
			return select('core').getEntityRecords('taxonomy', '_gp_venue', {
				per_page: -1,
				context: 'view',
			});
		},
		[venue]
	);

	if (venues) {
		venues = venues.map((item) => ({
			label: item.name,
			value: item.id + ':' + item.slug.slice(1, item.slug.length),
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
		Broadcaster({
			setVenueSlug: value[1],
		});
		unlockPostSaving();
	};

	return (
		<PanelRow>
			<SelectControl
				label={__('Venue', 'gatherpress')}
				value={venue}
				onChange={(value) => {
					updateTerm(value);
				}}
				options={venues}
			/>
		</PanelRow>
	);
};

export default VenuePanel;
