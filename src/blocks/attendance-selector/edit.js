/**
 * External dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Button, Modal } from "@wordpress/components";
import { useState } from "@wordpress/element";

/**
 * Internal dependencies.
 */
import AttendanceSelector from '../../components/AttendanceSelector';
const AttendanceModal = () => {
	const [isOpen, setOpen] = useState(false);
	const openModal = () => setOpen(true);
	const closeModal = () => setOpen(false);

	return (
		<>
			<Button variant="secondary" onClick={openModal}>
				Open Modal
			</Button>
			{isOpen && (
				<Modal title="This is my modal" onRequestClose={closeModal}>
					<Button variant="secondary" onClick={closeModal}>
						My custom close button
					</Button>
				</Modal>
			)}
		</>
	);
};

const Edit = () => {
	const blockProps = useBlockProps();
	// eslint-disable-next-line no-undef
	const postId = GatherPress.post_id;
	// eslint-disable-next-line no-undef
	const currentUser = GatherPress.current_user;

	return (
		<div { ...blockProps }>
			<h4 style={{ color: 'maroon' }}>AttendanceSelector</h4>
			<AttendanceSelector
				eventId={ postId }
				currentUser={ currentUser }
				type={ 'upcoming' }
			/>
			<AttendanceModal />
		</div>
	);
};

export default Edit;
