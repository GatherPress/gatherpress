// import { addAction } from '@wordpress/hooks';
import deprecated from '@wordpress/deprecated';

deprecated( 'Eating meat', {
    since: '2019.01.01',
    version: '2020.01.01',
    alternative: 'vegetables',
    plugin: 'the earth',
    hint: 'You may find it beneficial to transition gradually.',
} );

// Logs: 'Eating meat is deprecated since version 2019.01.01 and will be removed from the earth in version 2020.01.01. Please use vegetables instead. Note: You may find it beneficial to transition gradually.'

// function venueDeprecationAlert( message, { version } ) {
//     alert( `Deprecation: ${ message }. Version: ${ version }` );
// }

// addAction(
//     'deprecated',
//     'gatherpress/venue-deprecation-alert',
//     venueDeprecationAlert
// );
