/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

/**
 * Block variations for different venue details.
 *
 * @since 1.0.0
 */
const variations = [
	{
		name: 'venue-address',
		title: __( 'Venue Address', 'gatherpress' ),
		description: __( 'Display the venue address.', 'gatherpress' ),
		icon: 'location',
		isDefault: true,
		attributes: {
			placeholder: __( 'Venue address…', 'gatherpress' ),
			fieldType: 'address',
		},
		scope: [ 'inserter', 'transform' ],
	},
	{
		name: 'venue-phone',
		title: __( 'Venue Phone', 'gatherpress' ),
		description: __( 'Display the venue phone number.', 'gatherpress' ),
		icon: 'phone',
		attributes: {
			placeholder: __( 'Venue phone…', 'gatherpress' ),
			fieldType: 'phone',
		},
		scope: [ 'inserter', 'transform' ],
	},
	{
		name: 'venue-website',
		title: __( 'Venue Website', 'gatherpress' ),
		description: __( 'Display the venue website URL.', 'gatherpress' ),
		icon: 'admin-links',
		attributes: {
			placeholder: __( 'Venue website…', 'gatherpress' ),
			fieldType: 'url',
		},
		scope: [ 'inserter', 'transform' ],
	},
];

export default variations;
