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
import { DateTimeStartSettingPanel } from './datetime';
import VenuePanel from './venue';
// import { OptionsPanel } from './options';
import { useState } from '@wordpress/element';

const EventSettings = () => {
	const [ venue, setVenue ] = useState( '' );

	return (
		isEventPostType() && (
			<PluginDocumentSettingPanel
				name="gatherpress-event-settings"
				title={ __( 'Event settings', 'gatherpress' ) }
				initialOpen={ true }
				className="gatherpress-event-settings"
			>
				<DateTimeStartSettingPanel />
				<hr />
				<VenuePanel venue={ venue } setVenue={ setVenue } />
				{ /*<hr />*/ }
				{ /*<OptionsPanel />*/ }
			</PluginDocumentSettingPanel>
		)
	);
};

registerPlugin( 'gatherpress-event-settings', {
	render: EventSettings,
	icon: '',
} );
