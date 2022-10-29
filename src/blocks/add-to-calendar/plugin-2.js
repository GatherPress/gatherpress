
const { registerPlugin } = wp.plugins;
const { PluginDocumentSettingPanel } = wp.editPost
const { useSelect } = wp.data;

const RyanCustomSideBarPanel = () => {
	const postType = useSelect(select => select('core/editor').getCurrentPostType());

	if ('gp_event' !== postType) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="my-custom-panel"
			title="Ryan Custom Panel"
			icon="cloud"
		>
			Hello, World!
		</PluginDocumentSettingPanel>
	);
}
registerPlugin('ryan-custom-panel', { render: RyanCustomSideBarPanel });
