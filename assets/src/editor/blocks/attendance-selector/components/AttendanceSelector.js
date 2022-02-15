import React, { useState } from 'react';
import {__} from '@wordpress/i18n';
import AttendanceSelectorItem from './AttendanceSelectorItem';
import attendance from '../apis/attendance';
import Modal from 'react-modal';


const AttendanceSelector = () => {
	if ( 'object' !== typeof GatherPress ) {
		return '';
	}

	let defaultStatus = GatherPress.current_user_status.status;

	const [ attendanceStatus, setAttendanceStatus ] = useState( defaultStatus );
	const [ selectorHidden, setSelectorHidden ] = useState( 'hidden' );
	const [ selectorExpanded, setSelectorExpanded ] = useState( 'false' );

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

Modal.setAppElement('#gp-attendance-selector-container');
const [modalIsOpen, setIsOpen] = React.useState(false);
const openModal = () => {
    setIsOpen(true);
  }

  const afterOpenModal = () => {
    // references are now sync'd and can be accessed.
    subtitle.style.color = '#f00';
  }

  const closeModal = () => {
    setIsOpen(false);
  }

	const items = [
		{
			text: GatherPress.settings.language.attendance.attending_text,
			status: 'attending'
		},
		{
			text: GatherPress.settings.language.attendance.not_attending_text,
			status: 'not_attending'
		}
	];

	const onAnchorClick = async( e, status ) => {
		e.preventDefault();

		const response = await attendance.post( '/attendance', {
			status: status
		});

		if ( response.data.success ) {
			setAttendanceStatus( response.data.status );

			const dispatchAttendanceStatus = new CustomEvent( 'setAttendanceStatus', {
				detail: response.data.status
			});

			dispatchEvent( dispatchAttendanceStatus );

			const dispatchAttendanceList = new CustomEvent( 'setAttendanceList', {
				detail: response.data.attendees
			});

			dispatchEvent( dispatchAttendanceList );

			let count = {
				all: 0,
				attending: 0,
				not_attending: 0, // eslint-disable-line camelcase
				waiting_list: 0 // eslint-disable-line camelcase
			};

			for ( const [ key, value ] of Object.entries( response.data.attendees ) ) {
				count[key] = value.count;
			}

			const dispatchAttendanceCount = new CustomEvent( 'setAttendanceCount', {
				detail: count
			});

			dispatchEvent( dispatchAttendanceCount );
		}
	};

	const getStatusText = ( status ) => {
		switch ( status ) {
			case 'attending':
				return GatherPress.settings.language.attendance.attending;
			case 'not_attending':
				return GatherPress.settings.language.attendance.not_attending;
			case 'waiting_list':
				return GatherPress.settings.language.attendance.waiting_list;
		}

		return GatherPress.settings.language.attendance.attend;
	};

	const renderedItems = items.map( ( item, index ) => {
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

	const onSpanKeyDown = ( e ) => {
		if ( 13 === e.keyCode ) {
			setSelectorHidden( ( 'hidden' === selectorHidden ) ? 'show' : 'hidden' );
			setSelectorExpanded( ( 'false' === selectorExpanded ) ? 'true' : 'false' );
		}
	};

	if ( '' === GatherPress.current_user_status ) {
		return (
			<div className="gp-attendance-selector">
				<div className="wp-block-button">
					<a
						className="wp-block-button__link"
						href="#"
						onClick={ e => onAnchorClick( e, 'attending' )}
					>
						{ getStatusText( attendanceStatus ) }
					</a>
				</div>
			</div>
		);
	}

	const Example = () => {
		const [show, setShow] = useState(false);
		const handleClose = () => setShow(false);
		const handleShow = () => setShow(true);
		return (
			<>
				<Button variant="primary" onClick={handleShow}>
					Launch demo modal
				</Button>

				<Modal show={show} onHide={handleClose}>
					<Modal.Header closeButton>
						<Modal.Title>Modal heading</Modal.Title>
					</Modal.Header>
					<Modal.Body>Woohoo, you're reading this text in a modal!</Modal.Body>
					<Modal.Footer>
						<Button variant="secondary" onClick={handleClose}>
							Close
						</Button>
						<Button variant="primary" onClick={handleClose}>
							Save Changes
						</Button>
					</Modal.Footer>
				</Modal>
			</>
		);
	};

{/*   return ( */}
{/*     <div> */}
{/*       <p>Modal is Open? {isOpen ? 'Yes' : 'No'}</p> */}
{/*       <button onClick={open}>OPEN</button> */}
{/*     </div> */}
{/*   ); */}

	return (
		<div className="gp-attendance-selector wp-block-button">
			<button
				className="gp-attendance-selector__container wp-block-button__link"
				aria-expanded={selectorExpanded}
				tabIndex="0"
				onKeyDown={onSpanKeyDown}
				onClick={openModal}
			>
				{ getStatusText( attendanceStatus ) }
			</button>
      <Modal
        isOpen={modalIsOpen}
        onAfterOpen={afterOpenModal}
        onRequestClose={closeModal}
        style={customStyles}
        contentLabel="Example Modal"
      >
				<div>
					<h1>Title</h1>
					<p>This is a customizable modal.</p>
					<button onClick={closeModal}>CLOSE</button>
				</div>
			</Modal>
		</div>
	);
};

export default AttendanceSelector;
