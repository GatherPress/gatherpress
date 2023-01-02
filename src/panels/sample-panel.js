import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';

import { CheckCurrentPostType } from '../helpers/post-type';

wp.data.dispatch( 'core/edit-post' ) .toggleEditorPanelOpened(
	'gp-sample-document-setting-panel/sample-panel'
);

const GP_SampleDocumentSettingPanel = () => (
    <PluginDocumentSettingPanel
        name="sample-panel"
        title="Sample Panel"
        className="sample-panel"
    >
        <>
        <div>
        Custom Panel Contents
        </div>
        <div>
            <CheckCurrentPostType />
        </div>
        </>
    </PluginDocumentSettingPanel>
);

registerPlugin( 'gp-sample-document-setting-panel', {
    render: GP_SampleDocumentSettingPanel,
    icon: 'palmtree',
} );

export default GP_SampleDocumentSettingPanel