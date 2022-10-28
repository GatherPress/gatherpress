/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';

// import './attendance-list';

/**
 * Internal dependencies.
 */
// import AttendanceList from '../../components/AttendanceList';

const Save = () => {
	const blockProps = useBlockProps.save();

	return (
		<div { ...blockProps }>
			<h2>attendance-list</h2>
		</div>
	);
};

export default Save;
