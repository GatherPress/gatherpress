import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, ButtonGroup } from '@wordpress/components';
import Modal from 'react-modal';
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

	const closeModal = () => {
		setIsOpen(false);
	};

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
			case 'waiting_list':
				return __('Edit RSVP', 'gatherpress');
		}

		return __('Attend', 'gatherpress');
	};

	const onSpanKeyDown = (e) => {
		if (13 === e.keyCode) {
			setSelectorHidden('hidden' === selectorHidden ? 'show' : 'hidden');
			setSelectorExpanded(
				'false' === selectorExpanded ? 'true' : 'false'
			);
		}
	};

	// @todo need to revisit this and handle button for users that aren't logged in.
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
				onRequestClose={closeModal}
				style={customStyles}
				contentLabel={__('Edit RSVP', 'gatherpress')}
			>
				<div className="gp-block">
					<div class="has-large-font-size">{__('Edit RSVP', 'gatherpress')}</div>
					<div class="gp-guests-container">
						<label htmlFor="gp-guests">
							{__('Number of guests?', 'gatherpress')}
						</label>
						<input
							id="gp-guests"
							type="number"
							min="0"
							max="5"
							onChange={(e) => setAttendanceGuests(e.target.value)}
							defaultValue={attendanceGuests}
						/>
					</div>
					<ButtonGroup className="gp-buttons-container wp-block-buttons">
						<div className="gp-button-container wp-block-button is-style-outline has-small-font-size ">
							<Button
								onClick={(e) =>
									onAnchorClick(e, 'not_attending')
								}
								className="gp-button wp-block-button__link"
							>
								{__('Not Attending', 'gatherpress')}
							</Button>
						</div>
						<div className="gp-button-container wp-block-button has-small-font-size">
							<Button
								onClick={(e) => onAnchorClick(e, 'attending')}
								className="gp-button wp-block-button__link"
							>
								{__('Update', 'gatherpress')}
							</Button>
						</div>
					</ButtonGroup>
				</div>
			</Modal>
		</ButtonGroup>
	);
};

export default AttendanceSelector;
