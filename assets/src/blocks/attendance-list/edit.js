/**
 * External dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
/**
 * Internal dependencies.
 */
import AttendanceList from '../../components/AttendanceList';

const Edit = ( props ) => {
	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
			<AttendanceList />
		</div>
	);
};

export default Edit;
