/**
 * EditCover component for GatherPress.
 *
 * This component is used to create an overlay cover for the block editor.
 * It is typically used to visually distinguish the selected and unselected states
 * of a block in the editor.
 *
 * @deprecated Component will be removed soon.
 *
 * @since 1.0.0
 *
 * @param {Object}  props            - Component properties.
 * @param {boolean} props.isSelected - Indicates whether the block is selected.
 *
 * @return {JSX.Element} The rendered React component.
 */
const EditCover = ( props ) => {
	const { isSelected } = props;
	const display = isSelected ? 'none' : 'block';

	return (
		<div style={ { position: 'relative', zIndex: '0' } }>
			{ props.children }
			<div
				style={ {
					position: 'absolute',
					top: '0',
					right: '0',
					bottom: '0',
					left: '0',
					display,
				} }
			></div>
		</div>
	);
};

export default EditCover;
