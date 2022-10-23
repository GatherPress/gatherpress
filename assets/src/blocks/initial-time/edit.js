import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';
import {
	Button,
	DateTimePicker,
	Popover
} from '@wordpress/components';

import { CreateEventStart, FormatTheDate } from '../helper-functions';

import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
	const { beginTime } = attributes;

	const [openDatePopup, setOpenDatePopup] = useState(false);

	const [initialDateTime, setInitialDateTime] = useState(beginTime);

	const updateOnChange = (newTime) => {
		setInitialDateTime(newTime);
		setAttributes({ beginTime: newTime });
	}

	return (
		<div {...useBlockProps()}>
			<>
				<p>
					{initialDateTime ? (FormatTheDate(beginTime)) : <CreateEventStart />}
				</p>
				<Button
					isLink={true}
					onClick={() => setOpenDatePopup(!openDatePopup)}
					isSecondary
				>
					{initialDateTime ? (FormatTheDate(initialDateTime)) : __('Set Start Date & Time', 'gb-blocks')}
				</Button>
				{openDatePopup && (
					<Popover
						position="bottom"
						onClose={setOpenDatePopup.bind(null, false)}
					>
						<DateTimePicker
							label={__('Date/Time Picker', 'gatherpress')}
							currentDate={initialDateTime}
							onChange={updateOnChange}
							is12Hour={true}
						/>
					</Popover>
				)}
			</>
		</div>
	);
}
