const disable_blocks = [
	'gatherpress/add-to-calendar',
	'gatherpress/attendance-list',
	'gatherpress/attendance-selector',
	'gatherpress/event-date',
];
wp.domReady(
	function () {
		Object.keys( disable_blocks ).forEach(
			function (key) {
				const blockName = disable_blocks[key];
				if (blockName && wp.blocks.getBlockType( blockName ) !== undefined) {
					wp.blocks.unregisterBlockType( blockName );
				}
			}
		);
	}
);
