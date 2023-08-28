/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import RsvpResponse from '../../components/RsvpResponse';

const Edit = () => {
	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
			<RsvpResponse />
		</div>
	);
};

export default Edit;
