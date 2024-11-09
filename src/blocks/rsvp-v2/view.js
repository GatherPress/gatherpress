import { store } from '@wordpress/interactivity';

store("my-plugin/button-interactivity", {
	actions: {
		logSomething() {
			alert('hi');
		}
	},
});
