import axios from 'axios';

export default axios.create({
	baseURL: GatherPress.event_rest_api,
	headers: {
		'Content-Type': 'application/json',
		'X-WP-Nonce': GatherPress.nonce
	},
	params: {
		_wpnonce: GatherPress.nonce,
	}
});
