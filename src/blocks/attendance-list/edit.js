/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import AttendanceList from '../../components/AttendanceList';

const Edit = () => {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<h4 style={{color:'maroon'}}>AttendanceList</h4>
			<AttendanceList />
		</div>
	);
};

export default Edit;
