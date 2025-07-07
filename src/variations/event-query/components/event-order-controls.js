/**
 * WordPress dependencies
 */
import { SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * EventOrderControls component
 *
 * @param {*} param0
 * @return {Element} EventCountControls
 */
export const EventOrderControls = ({ attributes, setAttributes }) => {
	const { query: { order, orderBy } = {} } = attributes;
	const label =
		order === 'asc'
			? __('Ascending Order', 'gatherpress')
			: __('Descending Order', 'gatherpress');
	return (
		<>
			<SelectControl
				label={__('Order Events By', 'gatherpress')}
				value={orderBy}
				help={
					orderBy === 'meta_value' || orderBy === 'meta_value_num'
						? __(
								'Meta Value and Meta Value Num require that Meta Key is set in the Meta Query section.',
								'gatherpress'
							)
						: ''
				}
				options={[
					// The 'gatherpress_event' post_type does not support 'author'.
					// {
					// 	label: __( 'Author', 'gatherpress' ),
					// 	value: 'author',
					// },
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
					// {
					// 	label: __( 'Meta Value', 'gatherpress' ),
					// 	value: 'meta_value',
					// },
					// {
					// 	label: __( 'Meta Value Num', 'gatherpress' ),
					// 	value: 'meta_value_num',
					// },
					{
						label: __('Random', 'gatherpress'),
						value: 'rand',
					},
					// The 'gatherpress_event' post_type does not support 'page_attributes'.
					// {
					// 	label: __( 'Menu Order', 'gatherpress' ),
					// 	value: 'menu_order',
					// },
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
