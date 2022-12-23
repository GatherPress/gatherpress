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

const Edit = () => {
	const blockProps = useBlockProps();
	// eslint-disable-next-line no-undef
	const postId = GatherPress.post_id;
	// eslint-disable-next-line no-undef
	const currentUser = GatherPress.current_user;

	return (
		<div { ...blockProps }>
			<AttendanceSelector
				eventId={ postId }
				currentUser={ currentUser }
				type={ 'upcoming' }
			/>
		</div>
	);
};

export default Edit;
