import { dateI18n } from '@wordpress/date';

import moment from 'moment';

export const AddWeeks = ( numOfWeeks, date = new Date() ) => {
	const dateCopy = new Date( date.getTime() );

	dateCopy.setDate( dateCopy.getDate() + numOfWeeks * 7 );

	return dateCopy;
}

export const CreateEventStart = () => {
	const dateCopy = new Date();

	dateCopy.setDate(dateCopy.getDate() + 2 * 7);
	dateCopy.setHours(14, 0, 0);

	return (FormatTheDate(dateCopy));
}

export function CreateEventEnd() {
	const dateCopy = new Date();
	dateCopy.setDate(dateCopy.getDate() + 2 * 7);
	dateCopy.setHours(16, 0, 0);

	return (FormatTheDate(dateCopy));
}

export const FormatTheDate = (inputDate, format = 'F j, Y g:ia ') => {
	const dateCopy = new Date(inputDate);
	dateCopy.setDate(dateCopy.getDate());

	return (dateI18n(format, dateCopy) + 'UTC-' + dateCopy.getTimezoneOffset() / 60 + ':00' );
}


// @todo maybe put this is a save_post hook.
// https://www.ibenic.com/use-wordpress-hooks-package-javascript-apps/
// Then move button enabler
export function SaveGatherPressDateTime() {
	const isSavingPost = wp.data.select('core/editor').isSavingPost(),
		isAutosavingPost = wp.data.select('core/editor').isAutosavingPost();

	if (isEventPostType() && isSavingPost && !isAutosavingPost) {
		apiFetch({
			path: '/gatherpress/v1/event/datetime/',
			method: 'POST',
			data: {
				// eslint-disable-next-line no-undef
				post_id: GatherPress.post_id,
				datetime_start: moment(
					// eslint-disable-next-line no-undef
					GatherPress.event_datetime.datetime_start,
				).format('YYYY-MM-DD HH:mm:ss'),
				datetime_end: moment(
					// eslint-disable-next-line no-undef
					GatherPress.event_datetime.datetime_end,
				).format('YYYY-MM-DD HH:mm:ss'),
				// eslint-disable-next-line no-undef
				_wpnonce: GatherPress.nonce,
			},
		}).then(() => {
			// Saved.
		});
	}
}
