/**
 * Internal dependencies
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';

export const PBrocksTimeSettingsPanel = () => (
	<PluginDocumentSettingPanel
		name="pbrocks-time-panel"
		title="PBrocks Time Panel"
		className="pbrocks-time-panel"
	>
		<div>
			PBrocks Panel Start Time
		</div>
		<div>
			PBrocks Panel End Time
		</div>
	</PluginDocumentSettingPanel>
);

registerPlugin( 'pbrocks-time-panel-plugin', {
	render: PBrocksTimeSettingsPanel,
	icon: 'palmtree',
} );

