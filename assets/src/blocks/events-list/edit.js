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

const Edit = ( props ) => {
	const { attributes, setAttributes } = props;
	const blockProps = useBlockProps();
	const { topics } = attributes;
	const {
		topicsList,
	} = useSelect(
		( select ) => {
			const { getEntityRecords } = select( coreStore );
			return {
				topicsList: getEntityRecords(
					'taxonomy',
					'gp_topic',
					{
						per_page: -1,
						context: 'view',
					},
				),
			};
		},
		[
			topics,
		],
	);
	const excerptMax = 55;
	const topicSuggestions =
		topicsList?.reduce(
			( accumulator, topic ) => ( {
				...accumulator,
				[ topic.name ]: topic,
			} ),
			{},
		) ?? {};

	const selectTopics = ( tokens ) => {
		const hasNoSuggestion = tokens.some(
			( token ) =>
				typeof token === 'string' && ! topicSuggestions[ token ],
		);

		if ( hasNoSuggestion ) {
			return;
		}

		const allTopics = tokens.map( ( token ) => {
			return typeof token === 'string'
				? topicSuggestions[ token ]
				: token;
		} );

		if ( includes( allTopics, null ) ) {
			return false;
		}

		setAttributes( { topics: allTopics } );
	};
	const imageOptions = [ { label: 'Default', value: 'default' }, { label: 'Thumbnail', value: 'thumbnail' }, { label: 'Large', value: 'large'} ];

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody>
					<p>{ __( 'Event List type', 'gatherpress' ) }</p>
					<ButtonGroup className="block-editor-block-styles__variants">
						<Button
							className={ classnames(
								'block-editor-block-styles__item',
								{
									'is-active': 'upcoming' === attributes.type,
								},
							) }
							variant="secondary"
							label={ __( 'Upcoming', 'gatherpress' ) }
							onClick={ () => {
								setAttributes( { type: 'upcoming' } );
							} }
						>
							<Text
								as="span"
								limit={ 12 }
								ellipsizeMode="tail"
								className="block-editor-block-styles__item-text"
								truncate
							>
								{ __( 'Upcoming', 'gatherpress' ) }
							</Text>
						</Button>
						<Button
							className={ classnames(
								'block-editor-block-styles__item',
								{
									'is-active': 'past' === attributes.type,
								},
							) }
							variant="secondary"
							label={ __( 'Past', 'gatherpress' ) }
							onClick={ () => {
								setAttributes( { type: 'past' } );
							} }
						>
							<Text
								as="span"
								limit={ 12 }
								ellipsizeMode="tail"
								className="block-editor-block-styles__item-text"
								truncate
							>
								{ __( 'Past', 'gatherpress' ) }
							</Text>
						</Button>
					</ButtonGroup>
				</PanelBody>
				<PanelBody>
					<RangeControl
						label={ __(
							'Maximum number of events to display',
							'gatherpress',
						) }
						min={ 1 }
						max={ 10 }
						value={ parseInt( attributes.maxNumberOfEvents, 10 ) }
						onChange={ ( newVal ) =>
							setAttributes( { maxNumberOfEvents: newVal } )
						}
					/>
					<FormTokenField
						key="query-controls-topics-select"
						label={ __( 'Topics', 'gatherpress' ) }
						value={
							topics &&
							topics.map( ( item ) => ( {
								id: item.id,
								slug: item.slug,
								value: item.name || item.value,
							} ) )
						}
						suggestions={ Object.keys( topicSuggestions ) }
						onChange={ selectTopics }
						maxSuggestions={ 20 }
					/>
				</PanelBody>
				<PanelBody>
					<ToggleControl
						label="Show/Hide Attendee list"
						help={
							attributes.showAttendeeList
								? 'Show Attendee List'
								: 'Do not show Attendee List'
						}
						checked={ attributes.showAttendeeList }
						onChange={ () => {
							setAttributes( { showAttendeeList: ! attributes.showAttendeeList } );
						} }
					/>
					<SelectControl
						label="Image Size Options"
						value={ attributes.imageSize }
						options={ imageOptions }
						onChange={ ( value ) => {
							setAttributes( { imageSize: value } );
						} }
					/>
					<ToggleControl
						label="Show/Hide Featured Image"
						help={
							attributes.showFeaturedImage
								? 'Show Featured Image'
								: 'Do not show Featured Image'
						}
						checked={ attributes.showFeaturedImage }
						onChange={ () => {
							setAttributes( { showFeaturedImage: ! attributes.showFeaturedImage } );
						} }
					/>
					<ToggleControl
						label="Show/Description"
						help={
							attributes.showDescription
								? 'Show Description'
								: 'Hide Description'
						}
						checked={ attributes.showDescription }
						onChange={ () => {
							setAttributes( { showDescription: ! attributes.showDescription } );
						} }
					/>
					<TextControl
						label="Description Limit"
						help="Limit the amount of words that display underneath the title of the event"
						value={ parseInt( attributes.descriptionLimit ) }
						onChange={ ( value ) => setAttributes( { descriptionLimit: parseInt( value ) } ) }
						min={ 0 }
						max={ excerptMax }
						type="number"
					/>
					<ToggleControl
						label="Show/RSVP Button"
						help={
							attributes.showRsvpButton
								? 'Show RSVP Button'
								: 'Hide RSVP Button'
						}
						checked={ attributes.showRsvpButton }
						onChange={ () => {
							setAttributes( { showRsvpButton: ! attributes.showRsvpButton } );
						} }
					/>
				</PanelBody>
			</InspectorControls>
			<EventsList
				imageSize={ attributes.imageSize }
				descriptionLimit={ attributes.descriptionLimit }
				maxNumberOfEvents={ attributes.maxNumberOfEvents }
				type={ attributes.type }
				topics={ attributes.topics }
				showAttendeeList={ attributes.showAttendeeList }
				showFeaturedImage={ attributes.showFeaturedImage }
				showDescription={ attributes.showDescription }
				showRsvpButton={ attributes.showRsvpButton }
			/>
		</div>
	);
};

export default Edit;
