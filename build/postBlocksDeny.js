/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!*****************************************!*\
  !*** ./src/block-inserter/deny-post.js ***!
  \*****************************************/
/**
 * Remove unwanted blocks from given post type.
 */

const disableBlocks = ['gatherpress/add-to-calendar', 'gatherpress/attendance-list', 'gatherpress/attendance-selector', 'gatherpress/event-date', 'gatherpress/venue', 'gatherpress/venue-information'];
wp.domReady(function () {
  Object.keys(disableBlocks).forEach(function (key) {
    const blockName = disableBlocks[key];
    if (blockName && wp.blocks.getBlockType(blockName) !== undefined) {
      wp.blocks.unregisterBlockType(blockName);
    }
  });
});
/******/ })()
;
//# sourceMappingURL=postBlocksDeny.js.map