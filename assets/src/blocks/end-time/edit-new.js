import { __ } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
import { useBlockProps } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';
import {
	Button,
	DateTimePicker,
	Popover
} from '@wordpress/components';

// https://bobbyhadz.com/blog/javascript-date-add-weeks
// import { createEventStart } from '../helper - functions';

function CreateEventStart() {
	const dateCopy = new Date();

	dateCopy.setDate(dateCopy.getDate() + 2 * 7);

	return (dateI18n('F j, Y g:i a', dateCopy));
}

function CreateEventEnd() {
	const dateCopy = new Date();

	dateCopy.setDate(dateCopy.getDate() + 2 * 7);

	dateCopy.setMinutes(0);

	return (dateI18n('F j, Y g:i a', dateCopy));
}


import './editor.scss';
/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {

	const { initialTime } = attributes;

	const [openDatePopup, setOpenDatePopup] = useState( false );

	const [myDateTime, setMyDateTime] = useState(initialTime);

	const updateOnChange = (newTime) => {
		setMyDateTime( newTime );
		setAttributes( {initialTime: newTime } );
	}

	return (
		<div { ...useBlockProps() }>
		<Button
			isLink={true}
			onClick={() => setOpenDatePopup( ! openDatePopup )}
			isSecondary
		>
				{myDateTime ? "Change Date/Time" : "Set Date/Time" }
		</Button>
			<>
			{ openDatePopup && (
				<Popover
					position="bottom"
					onClose={ setOpenDatePopup.bind( null, false )}
				>
					<DateTimePicker
						label="My Date/Time Picker"
						currentDate={myDateTime}
						onChange={updateOnChange}
						is12Hour ={true}
					/>
				</Popover>
			) }
				<p>
					{myDateTime ? (dateI18n('F j, Y g:i a', initialTime)) : <CreateEventStart /> }
				</p>
			</>
		</div>
	);
}
