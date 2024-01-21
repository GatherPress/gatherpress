import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import {getFromGlobal} from '../helpers/globals';

const AnonymousRsvp = () => {
	const [anonymousRsvp, setAnonymousRsvp] = useState(getFromGlobal('settings.anonymous_rsvp'));

	return(
		<CheckboxControl
			label={__('Allow anonymous RSVPs', 'gatherpress')}
			checked={anonymousRsvp}
			onChange={setAnonymousRsvp}
		/>
	);
};

export default AnonymousRsvp;
