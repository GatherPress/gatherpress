// import React, { useState } from 'react';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, ButtonGroup } from '@wordpress/components';
import Modal from 'react-modal';
import AttendanceSelectorItem from './AttendanceSelectorItem';
import attendance from '../apis/attendance';

const AttendanceSelector = () => {
	if ('object' !== typeof GatherPress) {
		return '';
	}

	const [attendanceStatus, setAttendanceStatus] = useState(GatherPress.current_user.status);
	const [attendanceGuests, setAttendanceGuests] = useState(GatherPress.current_user.guests);
	const [selectorHidden, setSelectorHidden] = useState('hidden');
	const [selectorExpanded, setSelectorExpanded] = useState('false');
	const customStyles = {
		content: {
			top: '50%',
			left: '50%',
			right: 'auto',
			bottom: 'auto',
			marginRight: '-50%',
			transform: 'translate(-50%, -50%)',
		},
	};
	const [modalIsOpen, setIsOpen] = useState(false);
	const openModal = (e) => {
		onAnchorClick(e, 'attending', 0, false);
		setIsOpen(true);
	};

	// Might be better way to do this, but should only run on frontend, not admin.
	if ('undefined' === typeof adminpage) {
		Modal.setAppElement('#gp-attendance-selector-container');
	}

	const afterOpenModal = () => {
		// references are now sync'd and can be accessed.
		subtitle.style.color = '#f00';
	};

	const closeModal = () => {
		setIsOpen(false);
	};

	const items = [
		{
			text: GatherPress.settings.language.attendance.attending_text,
			status: 'attending',

		},
		{
			text: GatherPress.settings.language.attendance.not_attending_text,
			status: 'not_attending',
		},
	];

	const onAnchorClick = async (e, status, close = true) => {
		e.preventDefault();

		let guests = attendanceGuests;

		if ('attending' !== status) {
			guests = 0;
		}

		const response = await attendance.post('/attendance', {
			status,
			guests
		});

		if (response.data.success) {
			setAttendanceStatus(response.data.status);
			setAttendanceGuests(response.data.guests);

			const dispatchAttendanceStatus = new CustomEvent(
				'setAttendanceStatus',
				{
					detail: response.data.status,
				}
			);

			dispatchEvent(dispatchAttendanceStatus);

			const dispatchAttendanceList = new CustomEvent(
				'setAttendanceList',
				{
					detail: response.data.attendees,
				}
			);

			dispatchEvent(dispatchAttendanceList);

			const count = {
				all: 0,
				attending: 0,
				not_attending: 0, // eslint-disable-line camelcase
				waiting_list: 0, // eslint-disable-line camelcase
			};

			for (const [key, value] of Object.entries(
				response.data.attendees
			)) {
				count[key] = value.count;
			}

			const dispatchAttendanceCount = new CustomEvent(
				'setAttendanceCount',
				{
					detail: count,
				}
			);

			dispatchEvent(dispatchAttendanceCount);

			if (close) {
				closeModal();
			}
		}
	};

	const getStatusText = (status) => {
		switch (status) {
			case 'attending':
				return 'Edit RSVP';
			case 'not_attending':
				return GatherPress.settings.language.attendance.attend;
			case 'waiting_list':
				return GatherPress.settings.language.attendance.waiting_list;
		}

		return GatherPress.settings.language.attendance.attend;
	};

	const renderedItems = items.map((item, index) => {
		const { text, status } = item;

		return (
			<AttendanceSelectorItem
				key={index}
				text={text}
				status={status}
				onAnchorClick={onAnchorClick}
			/>
		);
	});

	const onSpanKeyDown = (e) => {
		if (13 === e.keyCode) {
			setSelectorHidden('hidden' === selectorHidden ? 'show' : 'hidden');
			setSelectorExpanded(
				'false' === selectorExpanded ? 'true' : 'false'
			);
		}
	};

	if ('' === GatherPress.current_user) {
		return (
			<div className="gp-attendance-selector">
				<div className="wp-block-button">
					<a
						className="wp-block-button__link"
						href="#"
						onClick={(e) => onAnchorClick(e, 'attending')}
					>
						{getStatusText(attendanceStatus)}
					</a>
				</div>
			</div>
		);
	}

	return (
		<ButtonGroup className="gp-block gp-attendance-selector wp-block-buttons">
			<div className="wp-block-button">
				<Button
					className="gp-attendance-selector__container wp-block-button__link"
					aria-expanded={selectorExpanded}
					tabIndex="0"
					onKeyDown={onSpanKeyDown}
					onClick={(e) => openModal(e)}
				>
					{getStatusText(attendanceStatus)}
				</Button>
			</div>
			<Modal
				isOpen={modalIsOpen}
				onAfterOpen={afterOpenModal}
				onRequestClose={closeModal}
				style={customStyles}
				contentLabel="Example Modal"
			>
				<div className="gp-block">
					<h3>{__('Update RSVP', 'gatherpress')}</h3>
					<p>
						<label htmlFor="gp-number-of-guests">
							{__('Number of guests?', 'gatherpress')}
						</label>
						<input
							id="gp-number-of-guests"
							type="number"
							min="0"
							max="5"
							onChange={(e) => setAttendanceGuests(e.target.value)}
							defaultValue={attendanceGuests}
						/>
					</p>
					<ButtonGroup className="wp-block-buttons">
						<div className="wp-block-button has-custom-font-size has-small-font-size">
							<Button
								onClick={(e) =>
									onAnchorClick(e, 'not_attending')
								}
								className="wp-block-button__link"
							>
								Not Attending
							</Button>
						</div>
						<div className="wp-block-button has-custom-font-size has-small-font-size">
							<Button
								onClick={(e) => onAnchorClick(e, 'attending')}
								className="wp-block-button__link"
							>
								Submit
							</Button>
						</div>
					</ButtonGroup>
				</div>
			</Modal>
		</ButtonGroup>
	);
};

export default AttendanceSelector;
