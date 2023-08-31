import { select } from '@wordpress/data';

export function isVenuePostType() {
	return 'gp_venue' === select('core/editor').getCurrentPostType();
}
