/**
 * External dependencies
 */
import Modal from 'react-modal';
import HtmlReactParser from 'html-react-parser';

/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { ButtonGroup, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal Dependencies.
 */
import { Broadcaster } from '../helpers/broadcasting';
import RsvpStatusResponse from './RsvpStatusResponse';
import { getFromGlobal } from '../helpers/globals';

/**
 * Rsvp component for GatherPress.
 *
 * This component provides functionality for users to RSVP to events. It includes a button
 * to open a modal for RSVP actions, handles different RSVP statuses, and updates the RSVP
 * status and guest count accordingly. The component communicates with the server through
 * REST API calls and broadcasts changes to other components.
 *
 * @since 1.0.0
 *
 * @param {Object} props                  - Component props.
 * @param {number} props.eventId          - The ID of the event.
 * @param {Object} [props.currentUser=''] - Current user's RSVP information.
 * @param {string} props.type             - Type of event ('upcoming' or 'past').
 *
 * @return {JSX.Element} The rendered React component.
 */
const Rsvp = ({ eventId, currentUser = '', type }) => {
	const [rsvpStatus, setRsvpStatus] = useState(currentUser.status);
	const [rsvpGuests, setRsvpGuests] = useState(currentUser.guests);
	const [selectorHidden, setSelectorHidden] = useState('hidden');
	const [selectorExpanded, setSelectorExpanded] = useState('false');
	const [modalIsOpen, setIsOpen] = useState(false);

	const customStyles = {
		overlay: {
			zIndex: 999999999,
		},
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
			path: '/gatherpress/v1/event/rsvp',
			method: 'POST',
			data: {
				post_id: eventId,
				status,
				guests,
				_wpnonce: getFromGlobal('nonce'),
			},
		}).then((res) => {
			if (res.success) {
				setRsvpStatus(res.status);
				setRsvpGuests(res.guests);

				const count = {
					all: 0,
					attending: 0,
					not_attending: 0, // eslint-disable-line camelcase
					waiting_list: 0, // eslint-disable-line camelcase
				};

				for (const [key, value] of Object.entries(res.responses)) {
					count[key] = value.count;
				}

				const payload = {
					setRsvpStatus: res.status,
					setRsvpResponse: res.responses,
					setRsvpCount: count,
					setRsvpSeeAllLink: count[res.status] > 8, // @todo make defaultLimit a setting, not hardcoded.
					setOnlineEventLink: res.online_link,
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
			case 'not_attending':
				return __('Edit RSVP', 'gatherpress');
		}

		return __('RSVP', 'gatherpress');
	};

	const getModalLabel = (status) => {
		switch (status) {
			case 'attending':
				return __("You're attending", 'gatherpress');
			case 'waiting_list':
				return __("You're wait listed", 'gatherpress');
			case 'not_attending':
				return __("You're not attending", 'gatherpress');
		}

		return __('RSVP to this event', 'gatherpress');
	};

	const onSpanKeyDown = (e) => {
		if (13 === e.keyCode) {
			setSelectorHidden('hidden' === selectorHidden ? 'show' : 'hidden');
			setSelectorExpanded(
				'false' === selectorExpanded ? 'true' : 'false'
			);
		}
	};

	const LoggedOutModal = () => {
		return (
			<div className="gp-modal gp-modal__rsvp">
				<div className="gp-modal__header">
					{__('Login Required', 'gatherpress')}
				</div>
				<div className="gp-modal__content">
					<div className="gp-modal__text">
						{__('You must ', 'gatherpress')}
						<a href={getFromGlobal('login_url')}>
							{__('Login', 'gatherpress')}
						</a>
						{__(' to RSVP to events.', 'gatherpress')}
					</div>
					{'' !== getFromGlobal('registration_url') && (
						<div className="gp-modal__text">
							<a href={getFromGlobal('registration_url')}>
								{__('Register', 'gatherpress')}
							</a>
							{__(
								' if you do not have an account.',
								'gatherpress'
							)}
						</div>
					)}
				</div>
				<ButtonGroup className="gp-buttons wp-block-buttons">
					<div className="gp-buttons__container wp-block-button">
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
		);
	};

	const LoggedInModal = ({ status }) => {
		let buttonStatus = '';
		let buttonLabel = '';

		if ('not_attending' === status || 'attend' === status) {
			buttonStatus = 'attending';
			buttonLabel = __('Attend', 'gatherpress');
		} else {
			buttonStatus = 'not_attending';
			buttonLabel = __('Not Attending', 'gatherpress');
		}

		return (
			<div className="gp-modal gp-modal__rsvp">
				<div className="gp-modal__header">
					{getModalLabel(rsvpStatus) ? (
						getModalLabel(rsvpStatus)
					) : (
						<Spinner />
					)}
				</div>
				<div className="gp-modal__content">
					<div className="gp-modal__text">
						{HtmlReactParser(
							sprintf(
								/* translators: %s: button label. */
								__(
									'To set or change your attending status, simply click the %s button below.',
									'gatherpress'
								),
								'<strong>' + buttonLabel + '</strong>'
							)
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
					{/*		defaultValue={rsvpGuests}*/}
					{/*	/>*/}
				</div>
				<ButtonGroup className="gp-buttons wp-block-buttons">
					<div className="gp-buttons__container wp-block-button is-style-outline">
						{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
						<a
							href="#"
							onClick={(e) => onAnchorClick(e, buttonStatus)}
							className="gp-buttons__button wp-block-button__link"
						>
							{buttonLabel}
						</a>
					</div>
					<div className="gp-buttons__container wp-block-button">
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
		);
	};

	return (
		<div className="gp-rsvp">
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
						{getButtonText(rsvpStatus)}
					</a>
				</div>
				<Modal
					isOpen={modalIsOpen}
					onRequestClose={closeModal}
					style={customStyles}
					contentLabel={__('Edit RSVP', 'gatherpress')}
				>
					{'' === currentUser && <LoggedOutModal />}
					{'' !== currentUser && (
						<LoggedInModal status={rsvpStatus} />
					)}
				</Modal>
			</ButtonGroup>
			{'attend' !== rsvpStatus && (
				<div className="gp-status">
					<RsvpStatusResponse type={type} status={rsvpStatus} />

					{0 < rsvpGuests && (
						<div className="gp-status__guests">
							<span>
								+{rsvpGuests} {__('guest(s)', 'gatherpress')}
							</span>
						</div>
					)}
				</div>
			)}
		</div>
	);
};

export default Rsvp;
