// import { select, subscribe } from '@wordpress/data';
// import { useEffect, useState } from '@wordpress/element';
// https://github.com/WordPress/gutenberg/issues/25330
const { select, subscribe } = wp.data;
const { useEffect, useState } = wp.element;


export function getPostType(effect) {
	const [isPostTypeEvent, setPostTypeAsEvent] = useState(false);

	subscribe(() => {
		const postType = wp.data.select('core/editor').getCurrentPostType();

		if ('gp_event' === postType) {
			setPostTypeAsEvent(isPostTypeEvent);
		}
	});

	useEffect(() => {
		if (!isPostSavingLocked) { effect(didPostSaveRequestSucceed); }
	}, [isPostSavingLocked]);
}


// set the initial postType
// let postType = getPostType();

const ThePT = wp.data.subscribe(() => {
	const GetThePostType = () => wp.data.select('core/editor').getCurrentPostType();

	// get the current postType
	const currentPostType = GetThePostType();

	// // only do something if postType has changed.
	// if (postType !== newPostType) {

	// 	// Do whatever you want after postType has changed
	// 	if (newPostType == 'gallery') {
	// 		$('#blockAudio, #blockVideo, #blockGallery').hide();
	// 		$('#blockGallery').fadeIn();
	// 	}

	// }
	return currentPostType
	// // update the postType variable.
	// postType = newPostType;
});
console.log('current pt ' + ThePT);

// const posts = window.wp.data.useSelect((select) => {
// 	return select('core').getEntityRecords('postType', 'product');
// }, []);

// wp.domReady(function () {
// 	wp.data.subscribe(() => {
// 		// console.log('A change occurred');
// 	});

// // 	wp.blocks.unregisterBlockType('core/heading');
// });

// const disable_blocks = [ 'core/cover', 'gatherpress/event-date' ];
// wp.domReady(function () {
// 	if ('post' === ThePT) {
// 		Object.keys(disable_blocks).forEach(function (key) {
// 			const blockName = disable_blocks[key];
// 			if (blockName && wp.blocks.getBlockType(blockName) !== undefined) {
// 				wp.blocks.unregisterBlockType(blockName);
// 			}
// 		});
// 	}
// });

wp.domReady(function () {
	const thePostTt = wp.data.select('core/block-editor').getCurrentPost().type;
	if ('post' === wp.data.select('core/editor').getCurrentPostType()) {
		wp.blocks.unregisterBlockType('core/cover');
	}
	console.log('FFS %s', thePostTt );
});
