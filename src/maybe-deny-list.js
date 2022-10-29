// import { unregisterBlockType } from '@wordpress/blocks';
// import domReady from '@wordpress/dom-ready';

// domReady(function () {
// 	unregisterBlockType('core/verse');
// });

if ('gp_event' === wp.data.select('core/editor').getCurrentPostType()) {
	wp.blocks.unregisterBlockType('core/cover');
}
wp.domReady(function () {
	wp.blocks.unregisterBlockType('core/heading');
});
