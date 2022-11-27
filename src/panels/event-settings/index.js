/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';

/**
 * Internal dependencies.
 */
import { isEventPostType } from '../helpers';
import DateTimePanel from './datetime';
import VenuePanel from './venue';
// import { OptionsPanel } from './options';
import { useState } from '@wordpress/element';

const EventSettings = () => {
	const [ venue, setVenue ] = useState( '' );

	return (
		isEventPostType() && (
			<PluginDocumentSettingPanel
				name="gp-event-settings"
				title={ __( 'Event settings', 'gatherpress' ) }
				initialOpen={ true }
				className="gp-event-settings"
			>
				<DateTimePanel />
				<hr />
				<VenuePanel venue={ venue } setVenue={ setVenue } />
				{ /*<hr />*/ }
				{ /*<OptionsPanel />*/ }
			</PluginDocumentSettingPanel>
		)
	);
};

registerPlugin( 'gp-event-settings', {
	render: EventSettings,
	icon: '',
} );
