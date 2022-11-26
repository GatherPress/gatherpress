/**
 * External dependencies.
 */
import React from 'react';

/**
 * WordPress dependencies.
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import Autocomplete from '../components/Autocomplete';

const autocompleteContainers = document.querySelectorAll(
	`[data-gp_component_name="autocomplete"]`
);

for ( let i = 0; i < autocompleteContainers.length; i++ ) {
	const attrs = JSON.parse( autocompleteContainers[ i ].dataset.gp_component_attrs );

	render( <Autocomplete attrs={ attrs } />, autocompleteContainers[ i ] );
}
