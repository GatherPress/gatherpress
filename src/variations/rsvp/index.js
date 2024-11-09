/**
 * WordPress dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';
import {__} from '@wordpress/i18n';
import {calendar} from '@wordpress/icons';

registerBlockVariation('core/group', {
    name: 'single-button-group',
    title: 'Single Button Group',
    icon: 'button', // You can use a WordPress Dashicon or an SVG icon
    attributes: {
        align: 'center', // You can set the desired alignment
    },
    innerBlocks: [
        ['core/button', { text: 'Attend' }] // Predefine one button block
    ],
    isDefault: false, // Set to true if you want this as a default variation
});

import { store } from '@wordpress/interactivity';
