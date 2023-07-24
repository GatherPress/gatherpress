/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { createRoot, useState } from '@wordpress/element';
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
import './event-settings';
import { Listener } from '../helpers/broadcasting';
import { getFromGlobal, setToGlobal } from '../helpers/globals';

const sendMessage = () => {
	if (
		global.confirm(
			__('Ready to announce this event to all members?', 'gatherpress')
		)
	) {
		apiFetch({
			path: '/gatherpress/v1/event/email/',
			method: 'POST',
			data: {
				post_id: getFromGlobal('post_id'),
				_wpnonce: getFromGlobal('nonce'),
			},
		}).then((res) => {
			const success = res.success ? '1' : '0';

			setToGlobal('event_announced', success);
			this.setState({
				announceEventSent: res.success,
			});
		});
	}
};

const EventCommuncationModal = () => {
	const [isOpen, setOpen] = useState(false);
	const [isAllChecked, setAllChecked] = useState(true);
	const [isAttendingChecked, setAttendingChecked] = useState(false);
	const [isWaitingListChecked, setWaitingListChecked] = useState(false);
	const [isNotAttendingChecked, setNotAttendingChecked] = useState(false);
	const [message, setMessage] = useState('');
	const openModal = () => setOpen(true);
	const closeModal = () => setOpen(false);

	Listener({ setOpen });

	return (
		<>
			<Button variant="secondary" onClick={openModal}>
				Open Modal
			</Button>
			{isOpen && (
				<Modal
					title={__('Email members', 'gatherpress')}
					onRequestClose={closeModal}
					shouldCloseOnClickOutside={false}
				>
					<TextareaControl
						label={__('Optional Message', 'gatherpress')}
						value={message}
						onChange={(value) => setMessage(value)}
					/>
					<Flex>
						<FlexItem>
							<CheckboxControl
								label={__('All', 'gatherpress')}
								checked={isAllChecked}
								onChange={setAllChecked}
							/>
						</FlexItem>
						<FlexItem>
							<CheckboxControl
								label={__('Attending', 'gatherpress')}
								checked={isAttendingChecked}
								onChange={setAttendingChecked}
							/>
						</FlexItem>
						<FlexItem>
							<CheckboxControl
								label={__('Waiting List', 'gatherpress')}
								checked={isWaitingListChecked}
								onChange={setWaitingListChecked}
							/>
						</FlexItem>
						<FlexItem>
							<CheckboxControl
								label={__('Not Attending', 'gatherpress')}
								checked={isNotAttendingChecked}
								onChange={setNotAttendingChecked}
							/>
						</FlexItem>
					</Flex>
					<br />
					<Button variant="primary" onClick={sendMessage}>
						{__('Send Email', 'gatherpress')}
					</Button>
				</Modal>
			)}
		</>
	);
};

domReady(() => {
	createRoot(document.getElementById('gp-event-communication-modal')).render(
		<EventCommuncationModal />
	);
});
