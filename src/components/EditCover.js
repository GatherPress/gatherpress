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
