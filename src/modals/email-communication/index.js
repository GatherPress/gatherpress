/**
 * WordPress dependencies.
 */
import { __, _x } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { createRoot, useState, useEffect, useRef } from '@wordpress/element';
import {
	Button,
	CheckboxControl,
	Flex,
	FlexItem,
	Modal,
	TextareaControl,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import { Listener } from '../../helpers/broadcasting';
import { getFromGlobal } from '../../helpers/globals';

/**
 * A modal component for notifying event members via email.
 *
 * This component provides a modal for event organizers to send email notifications
 * to specific groups of event members, such as attendees, waiting list members, or those
 * who have not indicated attendance.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the Event Communication Modal.
 */
const EventCommunicationModal = () => {
	const [isOpen, setOpen] = useState(false);
	const [isAllChecked, setAllChecked] = useState(false);
	const [isAttendingChecked, setAttendingChecked] = useState(false);
	const [isWaitingListChecked, setWaitingListChecked] = useState(false);
	const [isNotAttendingChecked, setNotAttendingChecked] = useState(false);
	const [isCheckBoxDisabled, setCheckBoxDisabled] = useState(false);
	const [buttonDisabled, setButtonDisabled] = useState(false);
	const [message, setMessage] = useState('');
	const textareaRef = useRef(null);
	const closeModal = () => setOpen(false);
	const sendMessage = () => {
		if (
			global.confirm(__('Confirm you are ready to send?', 'gatherpress'))
		) {
			apiFetch({
				path: getFromGlobal('urls.eventRestApi') + '/email',
				method: 'POST',
				data: {
					post_id: getFromGlobal('eventDetails.postId'),
					message,
					send: {
						all: isAllChecked,
						attending: isAttendingChecked,
						waiting_list: isWaitingListChecked,
						not_attending: isNotAttendingChecked,
					},
					_wpnonce: getFromGlobal('misc.nonce'),
				},
			}).then((res) => {
				if (res.success) {
					closeModal();
					setMessage('');
					setAllChecked(false);
					setAttendingChecked(false);
					setWaitingListChecked(false);
					setNotAttendingChecked(false);
				}
			});
		}
	};

	useEffect(() => {
		if (isAllChecked) {
			setCheckBoxDisabled(true);
			setAttendingChecked(false);
			setWaitingListChecked(false);
			setNotAttendingChecked(false);
		} else {
			setCheckBoxDisabled(false);
		}

		if (
			!isAllChecked &&
			!isAttendingChecked &&
			!isWaitingListChecked &&
			!isNotAttendingChecked
		) {
			setButtonDisabled(true);
		} else {
			setButtonDisabled(false);
		}
	}, [
		isAllChecked,
		isAttendingChecked,
		isWaitingListChecked,
		isNotAttendingChecked,
	]);

	Listener({ setOpen });

	useEffect(() => {
		// Focus the TextareaControl when the modal opens
		if (isOpen && textareaRef.current) {
			textareaRef.current.focus();
		}
	}, [isOpen]);

	return (
		<>
			{isOpen && (
				<Modal
					title={__('Notify members via email', 'gatherpress')}
					onRequestClose={closeModal}
					shouldCloseOnClickOutside={false}
				>
					<TextareaControl
						label={__('Optional message', 'gatherpress')}
						value={message}
						focus
						onChange={(value) => setMessage(value)}
						ref={textareaRef}
					/>
					<p className="description">
						{__(
							'Select the recipients for your message by checking the relevant boxes.',
							'gatherpress'
						)}
					</p>
					<Flex gap="8">
						<FlexItem>
							<CheckboxControl
								label={_x(
									'All Members',
									'List Status',
									'gatherpress'
								)}
								checked={isAllChecked}
								onChange={setAllChecked}
							/>
						</FlexItem>
						<FlexItem>
							<CheckboxControl
								label={_x(
									'Attending',
									'List Status',
									'gatherpress'
								)}
								checked={isAttendingChecked}
								onChange={setAttendingChecked}
								disabled={isCheckBoxDisabled}
							/>
						</FlexItem>
						<FlexItem>
							<CheckboxControl
								label={_x(
									'Waiting List',
									'List Status',
									'gatherpress'
								)}
								checked={isWaitingListChecked}
								onChange={setWaitingListChecked}
								disabled={isCheckBoxDisabled}
							/>
						</FlexItem>
						<FlexItem>
							<CheckboxControl
								label={_x(
									'Not Attending',
									'List Status',
									'gatherpress'
								)}
								checked={isNotAttendingChecked}
								onChange={setNotAttendingChecked}
								disabled={isCheckBoxDisabled}
							/>
						</FlexItem>
					</Flex>
					<br />
					<Button
						variant="primary"
						onClick={sendMessage}
						disabled={buttonDisabled}
					>
						{__('Send Email', 'gatherpress')}
					</Button>
				</Modal>
			)}
		</>
	);
};

domReady(() => {
	const modalWrapper = document.getElementById(
		'gatherpress-event-communication-modal'
	);
	if (modalWrapper) {
		createRoot(modalWrapper).render(<EventCommunicationModal />);
	}
});
