/**
 * External dependencies.
 */
import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies.
 */
import UserSelect from '../components/UserSelect';

const containers = document.querySelectorAll(
	`[data-gp_component_name="user-select"]`
);

for (let i = 0; i < containers.length; i++) {
	const attrs = JSON.parse(containers[i].dataset.gp_component_attrs);

	ReactDOM.render(<UserSelect attrs={attrs} />, containers[i]);
}
