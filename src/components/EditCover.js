/**
 * EditCover component provides an overlay for content to indicate its editability.
 *
 * @param {Object}      props            - The properties passed to the component.
 * @param {boolean}     props.isSelected - Indicates whether the content is selected for editing.
 * @param {JSX.Element} props.children   - The child elements to be rendered within the component.
 *
 * @return {JSX.Element} The rendered EditCover component.
 */
const EditCover = (props) => {
	const { isSelected } = props;
	const display = isSelected ? 'none' : 'block';

	return (
		<div style={{ position: 'relative' }}>
			{props.children}
			<div
				style={{
					position: 'absolute',
					top: '0',
					right: '0',
					bottom: '0',
					left: '0',
					display,
				}}
			></div>
		</div>
	);
};

export default EditCover;
