/**
 * Remove unwanted blocks from given post type.
 *
 * @package gatherpress
 */

wp.domReady(function () {
	wp.blocks.unregisterBlockType('gatherpress/events-list');
});
