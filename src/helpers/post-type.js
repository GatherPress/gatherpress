
export function CheckCurrentPostType() {
	return (wp.data.select('core/editor').getCurrentPostType() ?? 'dont know');
}

wp.domReady(function () {
	const postType = wp.data.select('core/editor').getCurrentPostType();
	if ( 'gp_event' === postType ) {
		wp.blocks.unregisterBlockType('gatherpress/events-list');
	}
});

function FilterPostTypeBlocks() {
	const postType = wp.data.select('core/editor').getCurrentPostType();
	if ( 'gp_event' === postType ) {
		wp.blocks.unregisterBlockType('gatherpress/events-list');
	}
}

