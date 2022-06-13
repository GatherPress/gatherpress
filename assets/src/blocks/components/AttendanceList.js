import { useState } from '@wordpress/element';
import {__} from '@wordpress/i18n';
import AttendanceListNavigation from './AttendanceListNavigation';
import AttendanceListContent from './AttendanceListContent';
import { Listener } from '../helpers/broadcasting';
import Modal from 'react-modal';

const AttendanceList = () => {
	let defaultStatus = 'attending';
	const items = [
		{
			title: __('Attending', 'gatherpress'),
			value: 'attending',
		},
		{
			title: __('Waiting List', 'gatherpress'),
			value: 'waiting_list',
		},
		{
			title: __('Not Attending', 'gatherpress'),
			value: 'not_attending',
		}
	];

	const customStyles = {
		content: {
			top: '50%',
			left: '50%',
			right: 'auto',
			bottom: 'auto',
			maxHeight: '75%',
			marginRight: '-50%',
			transform: 'translate(-50%, -50%)',
		},
	};
	const [modalIsOpen, setIsOpen] = useState(false);
	const openModal = (e) => {
		e.preventDefault();

		setIsOpen(true);
	};

	// Might be better way to do this, but should only run on frontend, not admin.
	if ('undefined' === typeof adminpage) {
		Modal.setAppElement('body');
	}

	const closeModal = (e) => {
		e.preventDefault();

		setIsOpen(false);
	};

	if ('object' === typeof GatherPress) {
		// @todo redo this logic and have it come from API and not GatherPress object.
		defaultStatus =
			'undefined' !== typeof GatherPress.current_user.status &&
			'attend' !== GatherPress.current_user.status
				? GatherPress.current_user.status
				: defaultStatus;
	}

	const [attendanceStatus, setAttendanceStatus] = useState(defaultStatus);

	Listener({ setAttendanceStatus });

	const onTitleClick = (e, value) => {
		e.preventDefault();

		setAttendanceStatus(value);
	};

	return (
		<>
			<div className="gp-attendance-list">
				<AttendanceListNavigation
					items={items}
					activeValue={attendanceStatus}
					onTitleClick={onTitleClick}
				/>
				<AttendanceListContent
					items={items}
					activeValue={attendanceStatus}
					limit={8}
				/>
			</div>
			<div className="has-text-align-right">
				{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
				<a href="#" onClick={(e) => openModal(e)}>
					{__('See all', 'gatherpress')}
				</a>
			</div>
			<Modal
				isOpen={modalIsOpen}
				onRequestClose={closeModal}
				style={customStyles}
				contentLabel={__('Attendance', 'gatherpress')}
			>
				<div className="gp-modal gp-modal__attendance-list">
					<div className="gp-modal__header has-large-font-size">
						{__('Attendance List', 'gatherpress')}
					</div>
					<div className="gp-modal__navigation">
						<AttendanceListNavigation
							items={items}
							activeValue={attendanceStatus}
							onTitleClick={onTitleClick}
						/>
					</div>
					<div className="gp-modal__content">
						<AttendanceListContent
							items={items}
							activeValue={attendanceStatus}
						/>
					</div>
				</div>
			</Modal>
		</>
	);
};

export default AttendanceList;
