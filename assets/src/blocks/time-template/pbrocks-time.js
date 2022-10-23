/**
 * Internal dependencies
 */

// import './panels';

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';

export const PBrocksTimeSettingsPanel = () => (
	<PluginDocumentSettingPanel
		name="pbrocks-time-panel"
		title="PBrocks Time Panel"
		className="pbrocks-time-panel"
	>
		<div>
			PBrocks Panel Contents
		</div>
		<div>
			PBrocks Panel Contents
		</div>
	</PluginDocumentSettingPanel>
);

registerPlugin('pbrocks-time-panel-plugin', {
	render: PBrocksTimeSettingsPanel,
	icon: 'palmtree',
});

