import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ButtonGroup } from '@wordpress/components';
import Modal from 'react-modal';
import apiFetch from '@wordpress/api-fetch';

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
		if ('not_attending' === attendanceStatus) {
			onAnchorClick(e, 'attending', 0, false);
		}
		setIsOpen(true);
	};

	// Might be better way to do this, but should only run on frontend, not admin.
	if ('undefined' === typeof adminpage) {
		Modal.setAppElement('#gp-attendance-selector-container');
	}

	const closeModal = () => {
		setIsOpen(false);
	};

	const onAnchorClick = async (e, status, guests = 0, close = true) => {
		e.preventDefault();

		if ('attending' !== status) {
			guests = 0;
		}

		apiFetch({
			path: '/gatherpress/v1/event/attendance',
			method: 'POST',
			data: {
				post_id: GatherPress.post_id,
				status: status,
				guests: guests,
				_wpnonce: GatherPress.nonce
			}
		}).then((res) => {
			if (res.success) {
				setAttendanceStatus(res.status);
				setAttendanceGuests(res.guests);

				const dispatchAttendanceStatus = new CustomEvent(
					'setAttendanceStatus',
					{
						detail: res.status,
					}
				);

				dispatchEvent(dispatchAttendanceStatus);

				const dispatchAttendanceList = new CustomEvent(
					'setAttendanceList',
					{
						detail: res.attendees,
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
					res.attendees
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
		});
	};

	const getButtonText = (status) => {
		switch (status) {
			case 'attending':
			case 'waiting_list':
				return __('Edit RSVP', 'gatherpress');
		}

		return __('Attend', 'gatherpress');
	};

	const getStatusText = (status) => {
		switch (status) {
			case 'attending':
				return __('Attending', 'gatherpress');
			case 'waiting_list':
				return __('Waiting List', 'gatherpress');
			case 'not_attending':
				return __('Not Attending', 'gatherpress');
		}

		return '';
	}

	const onSpanKeyDown = (e) => {
		if (13 === e.keyCode) {
			setSelectorHidden('hidden' === selectorHidden ? 'show' : 'hidden');
			setSelectorExpanded(
				'false' === selectorExpanded ? 'true' : 'false'
			);
		}
	};

	// @todo need to revisit this and handle button for users that aren't logged in.
	// Clean up so this does something... See issue #68 in GitHub.
	if ('' === GatherPress.current_user) {
		return (
			<div className="gp-attendance-selector">
				<div className="wp-block-button">
					<a
						className="wp-block-button__link"
						href="#"
						onClick={(e) => onAnchorClick(e, 'attending')}
					>
						{__('Attend', 'gatherpress')}
					</a>
				</div>
			</div>
		);
	}

	return (
		<div className="gp-attendance-selector">
			<ButtonGroup className="gp-buttons wp-block-buttons">
				<div className="gp-buttons__container  wp-block-button">
					<a
						className="gp-buttons__button wp-block-button__link"
						aria-expanded={selectorExpanded}
						tabIndex="0"
						onKeyDown={onSpanKeyDown}
						onClick={(e) => openModal(e)}
					>
						{getButtonText(attendanceStatus)}
					</a>
				</div>
				<Modal
					isOpen={modalIsOpen}
					onRequestClose={closeModal}
					style={customStyles}
					contentLabel={__('Edit RSVP', 'gatherpress')}
				>
					<div className="gp-modal">
						<div className="gp-modal__header has-large-font-size">
							{__('Edit RSVP', 'gatherpress')}
						</div>
						<div className="gp-modal__content">
							<label htmlFor="gp-guests">
								{__('Number of guests?', 'gatherpress')}
							</label>
							<input
								id="gp-guests"
								type="number"
								min="0"
								max="5"
								onChange={(e) => onAnchorClick(e, 'attending', e.target.value, false)}
								defaultValue={attendanceGuests}
							/>
						</div>
						<ButtonGroup className="gp-buttons wp-block-buttons">
							<div className="gp-buttons__container wp-block-button is-style-outline has-small-font-size">
								<a
									onClick={(e) =>
										onAnchorClick(e, 'not_attending')
									}
									className="gp-buttons__button wp-block-button__link"
								>
									{__('Not Attending', 'gatherpress')}
								</a>
							</div>
							<div className="gp-buttons__container wp-block-button has-small-font-size">
								<a
									onClick={closeModal}
									className="gp-buttons__button wp-block-button__link"
								>
									{__('Close', 'gatherpress')}
								</a>
							</div>
						</ButtonGroup>
					</div>
				</Modal>
			</ButtonGroup>
			{'attend' !== attendanceStatus &&
				<div className="gp-status">
					<div className="gp-status__response">
						<span>{__('Response:', 'gatherpress')}</span>
						<strong>{getStatusText(attendanceStatus)}</strong>
					</div>
					{0 < attendanceGuests &&
						<div className="gp-status__guests">
							<span>+{attendanceGuests} {__('guest(s)', 'gatherpress')}</span>
						</div>
					}
				</div>
			}
		</div>
	);
};

export default AttendanceSelector;
