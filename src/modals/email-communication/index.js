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
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies.
 */
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
	const { isOpen, isSaving } = useSelect( ( select ) => ( {
		isOpen: select( 'gatherpress/email-modal' ).isModalOpen(),
		isSaving: select( 'gatherpress/email-modal' ).isSaving(),
	} ), [] );
	const { closeModal } = useDispatch( 'gatherpress/email-modal' );
	const [ isAllChecked, setAllChecked ] = useState( false );
	const [ isAttendingChecked, setAttendingChecked ] = useState( false );
	const [ isWaitingListChecked, setWaitingListChecked ] = useState( false );
	const [ isNotAttendingChecked, setNotAttendingChecked ] = useState( false );
	const [ buttonDisabled, setButtonDisabled ] = useState( false );
	const [ message, setMessage ] = useState( '' );
	const textareaRef = useRef( null );
	const sendMessage = () => {
		if (
			// eslint-disable-next-line no-alert -- Confirmation required before sending mass emails.
			window.confirm( __( 'Confirm you are ready to send?', 'gatherpress' ) )
		) {
			apiFetch( {
				path: getFromGlobal( 'urls.eventApiPath' ) + '/email',
				method: 'POST',
				data: {
					post_id: getFromGlobal( 'eventDetails.postId' ),
					message,
					send: {
						all: isAllChecked,
						attending: isAttendingChecked,
						waiting_list: isWaitingListChecked,
						not_attending: isNotAttendingChecked,
					},
				},
			} ).then( ( res ) => {
				if ( res.success ) {
					closeModal();
					setMessage( '' );
					setAllChecked( false );
					setAttendingChecked( false );
					setWaitingListChecked( false );
					setNotAttendingChecked( false );
				}
			} );
		}
	};

	useEffect( () => {
		if (
			! isAllChecked &&
			! isAttendingChecked &&
			! isWaitingListChecked &&
			! isNotAttendingChecked
		) {
			setButtonDisabled( true );
		} else {
			setButtonDisabled( false );
		}
	}, [
		isAllChecked,
		isAttendingChecked,
		isWaitingListChecked,
		isNotAttendingChecked,
	] );

	useEffect( () => {
		// Focus the TextareaControl when the modal opens
		if ( isOpen && textareaRef.current ) {
			textareaRef.current.focus();
		}
	}, [ isOpen ] );

	return (
		<>
			{ isOpen && (
				<Modal
					title={ __( 'Send event update via email', 'gatherpress' ) }
					onRequestClose={ closeModal }
					shouldCloseOnClickOutside={ false }
					style={ { maxWidth: '550px' } }
				>
					<TextareaControl
						label={ __( 'Optional message', 'gatherpress' ) }
						value={ message }
						focus
						onChange={ ( value ) => setMessage( value ) }
						ref={ textareaRef }
					/>
					<p className="description">
						{ __(
							'Select the recipients for your message by checking the relevant boxes. "All Members" includes site users only. RSVP status options include both site users and non-user RSVPs.',
							'gatherpress',
						) }
					</p>
					<Flex gap="8">
						<FlexItem>
							<CheckboxControl
								label={ _x(
									'All Members',
									'Email recipient group option',
									'gatherpress',
								) }
								checked={ isAllChecked }
								onChange={ setAllChecked }
							/>
						</FlexItem>
						<FlexItem>
							<CheckboxControl
								label={ _x(
									'Attending',
									'Email recipient group option',
									'gatherpress',
								) }
								checked={ isAttendingChecked }
								onChange={ setAttendingChecked }
							/>
						</FlexItem>
						<FlexItem>
							<CheckboxControl
								label={ _x(
									'Waiting List',
									'Email recipient group option',
									'gatherpress',
								) }
								checked={ isWaitingListChecked }
								onChange={ setWaitingListChecked }
							/>
						</FlexItem>
						<FlexItem>
							<CheckboxControl
								label={ _x(
									'Not Attending',
									'Email recipient group option',
									'gatherpress',
								) }
								checked={ isNotAttendingChecked }
								onChange={ setNotAttendingChecked }
							/>
						</FlexItem>
					</Flex>
					<br />
					<Button
						variant="primary"
						onClick={ sendMessage }
						disabled={ buttonDisabled || isSaving }
					>
						{ _x(
							'Send Email',
							'Email submission button text',
							'gatherpress',
						) }
					</Button>
				</Modal>
			) }
		</>
	);
};

domReady( () => {
	const modalWrapper = document.getElementById(
		'gatherpress-event-communication-modal',
	);
	if ( modalWrapper ) {
		createRoot( modalWrapper ).render( <EventCommunicationModal /> );
	}
} );
