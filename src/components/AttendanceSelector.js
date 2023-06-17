/**
 * External dependencies
 */
import Modal from 'react-modal';

/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ButtonGroup, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal Dependencies.
 */
import { Broadcaster } from '../helpers/broadcasting';
import AttendeeResponse from './AttendeeResponse';
import { getFromGlobal } from '../helpers/globals';

const AttendanceSelector = ({ eventId, currentUser = '', type }) => {
	const [attendanceStatus, setAttendanceStatus] = useState(
		currentUser.status
	);
	const [attendanceGuests, setAttendanceGuests] = useState(
		currentUser.guests
	);
	const [selectorHidden, setSelectorHidden] = useState('hidden');
	const [selectorExpanded, setSelectorExpanded] = useState('false');
	const [modalIsOpen, setIsOpen] = useState(false);

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

	const openModal = (e) => {
		e.preventDefault();

		if (
			'not_attending' === attendanceStatus ||
			'attend' === attendanceStatus
		) {
			onAnchorClick(e, 'attending', 0, false);
		}
		setIsOpen(true);
	};

	// No need to show block if event is in the past.
	if ('past' === type) {
		return '';
	}

	// Might be better way to do this, but should only run on frontend, not admin.
	if ('undefined' === typeof adminpage) {
		Modal.setAppElement('.gp-enabled');
	}

	const closeModal = (e) => {
		e.preventDefault();

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
				post_id: eventId,
				status,
				guests,
				_wpnonce: getFromGlobal('nonce'),
			},
		}).then((res) => {
			if (res.success) {
				setAttendanceStatus(res.status);
				setAttendanceGuests(res.guests);

				const count = {
					all: 0,
					attending: 0,
					not_attending: 0, // eslint-disable-line camelcase
					waiting_list: 0, // eslint-disable-line camelcase
				};

				for (const [key, value] of Object.entries(res.attendees)) {
					count[key] = value.count;
				}

				const payload = {
					setAttendanceStatus: res.status,
					setAttendanceList: res.attendees,
					setAttendanceCount: count,
				};

				Broadcaster(payload, res.event_id);

				if (close) {
					closeModal(e);
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

	const getModalLabel = (status) => {
		switch (status) {
			case 'attending':
				return __("You're Attending", 'gatherpress');
			case 'waiting_list':
				return __("You're Wait Listed", 'gatherpress');
		}

		return '';
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
	// Clean up so this does something... See issue #68 in GitHub.
	if ('' === currentUser) {
		return (
			<div className="gp-attendance-selector">
				<div className="wp-block-button">
					{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
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
					{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
					<a
						href="#"
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
					<div className="gp-modal gp-modal__attendance-selector">
						<div className="gp-modal__header has-large-font-size">
							{getModalLabel(attendanceStatus) ? (
								getModalLabel(attendanceStatus)
							) : (
								<Spinner />
							)}
						</div>
						<div className="gp-modal__content">
							<div className="gp-modal__text">
								{__(
									'To change your attending status, simply click the "Not Attending" button below.',
									'gatherpress'
								)}
							</div>
							{/*@todo Guests feature coming in later version of GatherPress*/}
							{/*	<label htmlFor="gp-guests">*/}
							{/*		{__('Number of guests?', 'gatherpress')}*/}
							{/*	</label>*/}
							{/*	<input*/}
							{/*		id="gp-guests"*/}
							{/*		type="number"*/}
							{/*		min="0"*/}
							{/*		max="5"*/}
							{/*		onChange={(e) =>*/}
							{/*			onAnchorClick(*/}
							{/*				e,*/}
							{/*				'attending',*/}
							{/*				e.target.value,*/}
							{/*				false*/}
							{/*			)*/}
							{/*		}*/}
							{/*		defaultValue={attendanceGuests}*/}
							{/*	/>*/}
						</div>
						<ButtonGroup className="gp-buttons wp-block-buttons">
							<div className="gp-buttons__container wp-block-button is-style-outline has-small-font-size">
								{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
								<a
									href="#"
									onClick={(e) =>
										onAnchorClick(e, 'not_attending')
									}
									className="gp-buttons__button wp-block-button__link"
								>
									{__('Not Attending', 'gatherpress')}
								</a>
							</div>
							<div className="gp-buttons__container wp-block-button has-small-font-size">
								{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
								<a
									href="#"
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
			{'attend' !== attendanceStatus && (
				<div className="gp-status">
					<AttendeeResponse type={type} status={attendanceStatus} />

					{0 < attendanceGuests && (
						<div className="gp-status__guests">
							<span>
								+{attendanceGuests}{' '}
								{__('guest(s)', 'gatherpress')}
							</span>
						</div>
					)}
				</div>
			)}
		</div>
	);
};

export default AttendanceSelector;
