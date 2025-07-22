/**
 * WordPress dependencies
 */
import {
	RangeControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __, _x, sprintf } from '@wordpress/i18n';

/**
 * EventCountControls component
 *
 * @param {*} param0
 * @return {Element} EventCountControls
 */
export const EventCountControls = ({ attributes, setAttributes }) => {
	const { query: { perPage, offset = 0 } = {} } = attributes;

	return (
		<RangeControl
			label={__('Events Per Page', 'gatherpress')}
			min={1}
			max={50}
			onChange={(newCount) => {
				setAttributes({
					query: {
						...attributes.query,
						perPage: newCount,
						offset,
					},
				});
			}}
			value={perPage}
		/>
	);
};

/**
 * A component that lets you exclude the current event from the query
 *
 * @param {*} props
 * @return {Element} EventExcludeControls
 */
export const EventExcludeControls = ({ attributes, setAttributes }) => {
	const { query: { exclude_current: excludeCurrent } = {} } = attributes;

	const currentPost = useSelect((select) => {
		return select('core/editor').getCurrentPost();
	}, []);

	if (!currentPost) {
		return <div>{__('Loading…', 'gatherpress')}</div>;
	}

	return (
		<>
			<ToggleControl
				label={__('Exclude Current Event', 'gatherpress')}
				checked={!!excludeCurrent}
				onChange={(value) => {
					setAttributes({
						query: {
							...attributes.query,
							exclude_current: value ? currentPost.id : 0,
						},
					});
				}}
			/>
		</>
	);
};

/**
 * A component that lets you include the current event from the query
 *
 * @param {*} props
 * @return {Element} EventIncludeUnfinishedControls
 */
export const EventIncludeUnfinishedControls = ({
	attributes,
	setAttributes,
}) => {
	const { query: { include_unfinished: includeUnfinished } = {} } =
		attributes;

	return (
		<>
			<ToggleControl
				label={__('Include unfinished Events', 'gatherpress')}
				help={sprintf(
					/* translators: %s: 'upcoming' or 'past' */
					_x(
						'%s events that have started but are not yet finished.',
						"'Shows' or 'Hides'",
						'gatherpress'
					),
					includeUnfinished
						? __('Shows', 'gatherpress')
						: __('Hides', 'gatherpress')
				)}
				checked={includeUnfinished}
				onChange={(value) => {
					setAttributes({
						query: {
							...attributes.query,
							include_unfinished: value ? 1 : 0,
						},
					});
				}}
			/>
		</>
	);
};

/**
 * A component that lets you select whether to query for upcoming or past events.
 *
 * @param {*} props
 * @return {Element} EventListTypeControls
 */
export const EventListTypeControls = ({ attributes, setAttributes }) => {
	const {
		query: { gatherpress_events_query: eventListType = 'upcoming' } = {},
	} = attributes;

	const currentPost = useSelect((select) => {
		return select('core/editor').getCurrentPost();
	}, []);

	if (!currentPost) {
		return <div>{__('Loading…', 'gatherpress')}</div>;
	}

	return (
		<ToggleControl
			label={__('Upcoming or past events.', 'gatherpress')}
			help={sprintf(
				/* translators: %s: 'upcoming' or 'past' */
				_x(
					'Currently shows %s events.',
					"'upcoming' or 'past'",
					'gatherpress'
				),
				eventListType
			)}
			checked={'upcoming' === eventListType}
			onChange={(value) => {
				setAttributes({
					query: {
						...attributes.query,
						gatherpress_events_query: value ? 'upcoming' : 'past',
					},
				});
			}}
		/>
	);
};

export const EventOffsetControls = ({ attributes, setAttributes }) => {
	const { query: { offset = 0 } = {} } = attributes;
	return (
		<RangeControl
			label={__('Event Offset', 'gatherpress')}
			min={0}
			max={50}
			value={offset}
			onChange={(newOffset) => {
				setAttributes({
					query: {
						...attributes.query,
						offset: newOffset,
					},
				});
			}}
		/>
	);
};

/**
 * EventOrderControls component
 *
 * @param {*} param0
 * @return {Element} EventCountControls
 */
export const EventOrderControls = ({ attributes, setAttributes }) => {
	const { query: { order, orderBy } = {} } = attributes;
	let label;
	if (orderBy === 'rand') {
		label = __('Random Order', 'gatherpress');
	} else if (order === 'asc') {
		label = __('Ascending Order', 'gatherpress');
	} else {
		label = __('Descending Order', 'gatherpress');
	}
	return (
		<>
			<SelectControl
				label={__('Order Events by', 'gatherpress')}
				value={orderBy}
				options={[
					{
						label: __('Event Date', 'gatherpress'),
						value: 'datetime', // This is GatherPress specific, a normal post would use 'date'.
					},
					{
						label: __('Last Modified Date', 'gatherpress'),
						value: 'modified',
					},
					{
						label: __('Title', 'gatherpress'),
						value: 'title',
					},
					{
						label: __('Random', 'gatherpress'),
						value: 'rand',
					},
					{
						label: __('Post ID', 'gatherpress'),
						value: 'id',
					},
				]}
				onChange={(newOrderBy) => {
					setAttributes({
						query: {
							...attributes.query,
							orderBy: newOrderBy,
						},
					});
				}}
			/>
			<ToggleControl
				label={label}
				checked={order === 'asc'}
				disabled={orderBy === 'rand'}
				onChange={() => {
					setAttributes({
						query: {
							...attributes.query,
							order: order === 'asc' ? 'desc' : 'asc',
						},
					});
				}}
			/>
		</>
	);
};
