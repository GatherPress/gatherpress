import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';

import { PanelBody, PanelRow } from '@wordpress/components';

import { CheckCurrentPostType } from '../helpers/post-type';

wp.data.dispatch( 'core/edit-post' ) .toggleEditorPanelOpened(
	'gp-sample-document-setting-panel/sample-panel'
);

const GP_SampleDocumentSettingPanel = () => (
    <PluginDocumentSettingPanel
        name="sample-panel"
        title="Post Type Panel"
        className="sample-panel"
		icon="palmtree"
    >
        <>
        <PanelRow>
            <div
                style={{textAlign: 'center', width:'88%'}}
            >
				<b>Show Current Post Type</b>
			</div>
        </PanelRow>
        <PanelRow>
            <div
                style={{textAlign: 'center', width:'88%'}}
            >
				<CheckCurrentPostType />
			</div>
        </PanelRow>
        </>
    </PluginDocumentSettingPanel>
);

registerPlugin( 'gp-sample-document-setting-panel', {
    render: GP_SampleDocumentSettingPanel,
} );

export default GP_SampleDocumentSettingPanel