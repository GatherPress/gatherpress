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
	RangeControl,
	ButtonGroup,
	Button,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalText as Text,
} from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import EventsList from '../../components/EventsList';

const Edit = (props) => {
	const { attributes, setAttributes } = props;
	const blockProps = useBlockProps();
	const { topics } = attributes;
	const { topicsList } = useSelect(
		(select) => {
			const { getEntityRecords } = select(coreStore);
			return {
				topicsList: getEntityRecords('taxonomy', 'gp_topic', {
					per_page: -1,
					context: 'view',
				}),
			};
		},
		[topics]
	);

	const topicSuggestions =
		topicsList?.reduce(
			(accumulator, topic) => ({
				...accumulator,
				[topic.name]: topic,
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

	return (
		<div {...blockProps}>
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
				</PanelBody>
			</InspectorControls>
			<EventsList
				maxNumberOfEvents={attributes.maxNumberOfEvents}
				type={attributes.type}
				topics={attributes.topics}
			/>
		</div>
	);
};

export default Edit;
