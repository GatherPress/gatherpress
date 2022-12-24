/**
 * Remove unwanted blocks from given post type.
 *
 * @package gatherpress
 */

const disableBlocks = [
	'gatherpress/add-to-calendar',
	'gatherpress/attendance-list',
	'gatherpress/attendance-selector',
	'gatherpress/event-date',
	'gatherpress/venue',
];

wp.domReady(function () {
	Object.keys(disableBlocks).forEach(function (key) {
		const blockName = disableBlocks[key];
		if (blockName && wp.blocks.getBlockType(blockName) !== undefined) {
			wp.blocks.unregisterBlockType(blockName);
		}
	});
});
