/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import RsvpResponse from '../../components/RsvpResponse';
import EditCover from '../../components/EditCover';

const Edit = () => {
	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
			<EditCover>
				<RsvpResponse />
			</EditCover>
		</div>
	);
};

export default Edit;
