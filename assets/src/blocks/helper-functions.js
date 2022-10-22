import { dateI18n } from '@wordpress/date';

// import './variations'

export const AddWeeks = ( numOfWeeks, date = new Date() ) => {
	const dateCopy = new Date( date.getTime() );

	dateCopy.setDate( dateCopy.getDate() + numOfWeeks * 7 );

	return dateCopy;
}

export const CreateEventStart = () => {
	const dateCopy = new Date();

	dateCopy.setDate(dateCopy.getDate() + 2 * 7);
	dateCopy.setHours(18, 0, 0);

	return (FormatTheDate(dateCopy));
}

export function CreateEventEnd() {
	const dateCopy = new Date();
	dateCopy.setDate(dateCopy.getDate() + 2 * 7);
	dateCopy.setHours(20, 0, 0);

	return (FormatTheDate(dateCopy));
}

export const FormatTheDate = (inputDate, format = 'F j, Y g:ia ') => {
	const dateCopy = new Date(inputDate);
	dateCopy.setDate(dateCopy.getDate());

	return (dateI18n(format, dateCopy) + 'UTC-' + dateCopy.getTimezoneOffset() / 60 + ':00' );
}

