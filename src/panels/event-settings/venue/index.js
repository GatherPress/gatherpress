/**
 * WordPress dependencies.
 */
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Flex, FlexItem, PanelRow, SelectControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { Broadcaster } from '../../../helpers/broadcasting';

const VenuePanel = (props) => {
	const { venue, setVenue } = props;
	const editPost = useDispatch('core/editor').editPost;
	const { unlockPostSaving } = useDispatch('core/editor');
	const venueTermId = useSelect((select) =>
		select('core/editor').getEditedPostAttribute('_gp_venue')
	);
	const venueTerm = useSelect((select) =>
		select('core').getEntityRecord('taxonomy', '_gp_venue', venueTermId)
	);
	const venueId = venueTerm?.slug.replace('_venue_', '');
	const venueValue = venueTermId + ':' + venueId;

	useEffect(() => {
		setVenue(String(venueValue) ?? '');
		Broadcaster({
			setVenueId: venueId,
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
			value: item.id + ':' + item.slug.replace('_venue_', ''),
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
			setVenueId: value[1],
		});
		unlockPostSaving();
	};

	return (
		<PanelRow>
			<Flex>
				<FlexItem>{__('Venue', 'gatherpress')}</FlexItem>
				<FlexItem>
					<SelectControl
						label={__('Venue', 'gatherpress')}
						hideLabelFromVision="true"
						value={venue}
						onChange={(value) => {
							updateTerm(value);
						}}
						options={venues}
						style={{ width: '11rem' }}
					/>
				</FlexItem>
			</Flex>
		</PanelRow>
	);
};

export default VenuePanel;
