/**
 * External dependencies.
 */
import React from 'react';
import { render } from 'react-dom';

/**
 * WordPress dependencies.
 */
// import { render } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import UserSelect from '../components/UserSelect';

const containers = document.querySelectorAll(
	`[data-gp_component_name="user-select"]`,
);

for ( let i = 0; i < containers.length; i++ ) {
	const attrs = JSON.parse( containers[ i ].dataset.gp_component_attrs );

	render(
		<UserSelect attrs={ attrs } />,
		containers[ i ],
	);
}
