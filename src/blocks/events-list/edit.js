/**
 * External dependencies.
 */
import { includes } from 'lodash';
import classnames from 'classnames';

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	FormTokenField,
	SelectControl,
	RangeControl,
	ButtonGroup,
	Button,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalText as Text,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import EventsList from '../../components/EventsList';
import EditCover from '../../components/EditCover';

/**
 * Edit component for the GatherPress Event List block.
 *
 * This component renders the edit view of the GatherPress Event List block.
 * It provides an interface for users to customize the display options of the event list,
 * including the type of events (upcoming or past), the maximum number of events to display,
 * topics, venues, and various display options such as showing/hiding RSVP responses,
 * featured images, descriptions, and event venues.
 *
 * @since 1.0.0
 *
 * @param {Object}   props               - The properties passed to the component.
 * @param {Object}   props.attributes    - The block attributes.
 * @param {Function} props.setAttributes - Function to update block attributes.
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = (props) => {
	const { attributes, setAttributes } = props;
	const blockProps = useBlockProps();
	const { topics, venues } = attributes;
	const { topicsList } = useSelect((select) => {
		const { getEntityRecords } = select(coreStore);
		return {
			topicsList: getEntityRecords('taxonomy', 'gp_topic', {
				per_page: -1,
				context: 'view',
			}),
		};
	}, []);
	const { venueList } = useSelect((select) => {
		const { getEntityRecords } = select(coreStore);
		return {
			venueList: getEntityRecords('taxonomy', '_gp_venue', {
				per_page: -1,
				context: 'view',
			}),
		};
	}, []);
	const excerptMax = 55;
	const topicSuggestions =
		topicsList?.reduce(
			(accumulator, topic) => ({
				...accumulator,
				[topic.name]: topic,
			}),
			{}
		) ?? {};
	const venueSuggestions =
		venueList?.reduce(
			(accumulator, venue) => ({
				...accumulator,
				[venue.name]: venue,
			}),
			{}
		) ?? {};

	const selectTopics = (tokens) => {
		const hasNoSuggestion = tokens.some(
			(token) => typeof token === 'string' && !topicSuggestions[token]
		);

		if (hasNoSuggestion) {
			return;
		}

		const allTopics = tokens.map((token) => {
			return typeof token === 'string' ? topicSuggestions[token] : token;
		});

		if (includes(allTopics, null)) {
			return false;
		}

		setAttributes({ topics: allTopics });
	};

	const selectVenues = (tokens) => {
		const hasNoSuggestion = tokens.some(
			(token) => typeof token === 'string' && !venueSuggestions[token]
		);

		if (hasNoSuggestion) {
			return;
		}

		const allVenues = tokens.map((token) => {
			return typeof token === 'string' ? venueSuggestions[token] : token;
		});

		if (includes(allVenues, null)) {
			return false;
		}

		setAttributes({ venues: allVenues });
	};

	const imageOptions = [
		{ label: 'Default', value: 'default' },
		{ label: 'Thumbnail', value: 'thumbnail' },
		{ label: 'Large', value: 'large' },
	];

	return (
		<>
			<InspectorControls>
				<PanelBody>
					<p>{__('Event List type', 'gatherpress')}</p>
					<ButtonGroup className="block-editor-block-styles__variants">
						<Button
							className={classnames(
								'block-editor-block-styles__item',
								{
									'is-active': 'upcoming' === attributes.type,
								}
							)}
							variant="secondary"
							label={__('Upcoming', 'gatherpress')}
							onClick={() => {
								setAttributes({ type: 'upcoming' });
							}}
						>
							<Text
								as="span"
								limit={12}
								ellipsizeMode="tail"
								className="block-editor-block-styles__item-text"
								truncate
							>
								{__('Upcoming', 'gatherpress')}
							</Text>
						</Button>
						<Button
							className={classnames(
								'block-editor-block-styles__item',
								{
									'is-active': 'past' === attributes.type,
								}
							)}
							variant="secondary"
							label={__('Past', 'gatherpress')}
							onClick={() => {
								setAttributes({ type: 'past' });
							}}
						>
							<Text
								as="span"
								limit={12}
								ellipsizeMode="tail"
								className="block-editor-block-styles__item-text"
								truncate
							>
								{__('Past', 'gatherpress')}
							</Text>
						</Button>
					</ButtonGroup>
				</PanelBody>
				<PanelBody>
					<RangeControl
						label={__(
							'Maximum number of events to display',
							'gatherpress'
						)}
						min={1}
						max={10}
						value={parseInt(attributes.maxNumberOfEvents, 10)}
						onChange={(newVal) =>
							setAttributes({ maxNumberOfEvents: newVal })
						}
					/>
					<FormTokenField
						key="query-controls-topics-select"
						label={__('Topics', 'gatherpress')}
						value={
							topics &&
							topics.map((item) => ({
								id: item.id,
								slug: item.slug,
								value: item.name || item.value,
							}))
						}
						suggestions={Object.keys(topicSuggestions)}
						onChange={selectTopics}
						maxSuggestions={20}
					/>
					<FormTokenField
						key="query-controls-venues-select"
						label={__('Venues', 'gatherpress')}
						value={
							venues &&
							venues.map((item) => ({
								id: item.id,
								slug: item.slug,
								value: item.name || item.value,
							}))
						}
						suggestions={Object.keys(venueSuggestions)}
						onChange={selectVenues}
						maxSuggestions={20}
					/>
				</PanelBody>
				<PanelBody>
					<ToggleControl
						label={__(
							'Show/Hide All RSVP Responses',
							'gatherpress'
						)}
						help={
							attributes.eventOptions.showRsvpResponse
								? __('Show All RSVP Responses', 'gatherpress')
								: __('Hide All RSVP Responses', 'gatherpress')
						}
						checked={
							attributes.eventOptions.showRsvpResponse ?? true
						}
						onChange={(value) => {
							setAttributes({
								eventOptions: {
									...attributes.eventOptions,
									showRsvpResponse: value,
								},
							});
						}}
					/>
					<ToggleControl
						label={__('Show/Hide My RSVP Response')}
						help={
							attributes.eventOptions.showRsvp
								? __('Show My RSVP Response')
								: __('Hide My RSVP Response')
						}
						checked={attributes.eventOptions.showRsvp}
						onChange={(value) => {
							setAttributes({
								eventOptions: {
									...attributes.eventOptions,
									showRsvp: value,
								},
							});
						}}
					/>
					<SelectControl
						label={__('Image Size Options', 'gatherpress')}
						value={attributes.eventOptions.imageSize}
						options={imageOptions}
						onChange={(value) =>
							setAttributes({
								eventOptions: {
									...attributes.eventOptions,
									imageSize: value,
								},
							})
						}
					/>
					<ToggleControl
						label={__('Show/Hide Featured Image', 'gatherpress')}
						help={
							attributes.eventOptions.showFeaturedImage
								? __('Show Featured Image', 'gatherpress')
								: __('Hide Featured Image', 'gatherpress')
						}
						checked={attributes.eventOptions.showFeaturedImage}
						onChange={(value) => {
							setAttributes({
								eventOptions: {
									...attributes.eventOptions,
									showFeaturedImage: value,
								},
							});
						}}
					/>
					<ToggleControl
						label={__('Show/Hide Description', 'gatherpress')}
						help={
							attributes.eventOptions.showDescription
								? __('Show Description', 'gatherpress')
								: __('Hide Description', 'gatherpress')
						}
						checked={attributes.eventOptions.showDescription}
						onChange={(value) => {
							setAttributes({
								eventOptions: {
									...attributes.eventOptions,
									showDescription: value,
								},
							});
						}}
					/>
					<TextControl
						label={__('Description Limit')}
						help={__(
							'Limit the amount of words that display underneath the title of the event'
						)}
						value={parseInt(
							attributes.eventOptions.descriptionLimit
						)}
						onChange={(value) =>
							setAttributes({
								eventOptions: {
									...attributes.eventOptions,
									descriptionLimit: value,
								},
							})
						}
						min={0}
						max={excerptMax}
						type="number"
					/>
					<ToggleControl
						label={__('Show/Event Venue')}
						help={
							attributes.eventOptions.showVenue
								? __('Show Event Venue')
								: __('Hide Event Venue')
						}
						checked={attributes.eventOptions.showVenue}
						onChange={(value) => {
							setAttributes({
								eventOptions: {
									...attributes.eventOptions,
									showVenue: value,
								},
							});
						}}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<EditCover>
					<EventsList
						eventOptions={attributes.eventOptions}
						maxNumberOfEvents={attributes.maxNumberOfEvents}
						type={attributes.type}
						topics={attributes.topics}
						venues={attributes.venues}
					/>
				</EditCover>
			</div>
		</>
	);
};

export default Edit;
