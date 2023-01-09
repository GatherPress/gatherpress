/**
 * Remove unwanted blocks from given post type.
 */

wp.domReady(function () {
	wp.blocks.unregisterBlockType('gatherpress/venue-information');
});
