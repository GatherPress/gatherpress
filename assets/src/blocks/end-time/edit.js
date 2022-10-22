import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';
import {
	Button,
	DateTimePicker,
	Popover
} from '@wordpress/components';

import { CreateEventEnd, FormatTheDate } from '../helper-functions';

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

	const { endTime } = attributes;

	const [openDatePopup, setOpenDatePopup] = useState( false );

	const [myDateTime, setMyDateTime] = useState(endTime);

	const updateOnChange = (newTime) => {
		setMyDateTime( newTime );
		setAttributes( {endTime: newTime } );
	}

	return (
		<div {...useBlockProps()}>
			<>
				<p>
					{myDateTime ? (FormatTheDate(endTime)) : <CreateEventEnd />}
				</p>
				<Button
					isLink={true}
					onClick={() => setOpenDatePopup( ! openDatePopup )}
					isSecondary
				>
						{myDateTime ? __('Change End Date/Time', 'gatherpress') : __('Set End Date/Time', 'gatherpress') }
				</Button>
				{ openDatePopup && (
					<Popover
						position="bottom"
						onClose={ setOpenDatePopup.bind( null, false )}
					>
						<DateTimePicker
							label={__( 'Date/Time Picker' , 'gatherpress' )}
							currentDate={myDateTime}
							onChange={updateOnChange}
							is12Hour ={true}
						/>
					</Popover>
				) }
			</>
		</div>
	);
}
