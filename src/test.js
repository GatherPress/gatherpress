import { store } from '@wordpress/interactivity';

const { state } = store( 'my-plugin/button-interactivity', {
	actions: {
		logSomething() {
			alert('hello');
		}
	},
});
