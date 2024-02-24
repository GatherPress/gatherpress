/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "@wordpress/dom-ready":
/*!**********************************!*\
  !*** external ["wp","domReady"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["domReady"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!*******************************************************!*\
  !*** ./src/blocks/add-to-calendar/add-to-calendar.js ***!
  \*******************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/dom-ready */ "@wordpress/dom-ready");
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0__);
/**
 * WordPress dependencies.
 */


/**
 * Toggle to Show/Hide Calendar options.
 *
 * @param {TouchEvent} e Event.
 */
const addToCalendarToggle = e => {
  e.preventDefault();
  const currentListDisplay = e.target.nextElementSibling.style.display;
  const lists = document.querySelectorAll('.gp-add-to-calendar__list');
  for (let i = 0; i < lists.length; i++) {
    lists[i].style.display = 'none';
  }
  e.target.nextElementSibling.style.display = 'none' === currentListDisplay ? 'flex' : 'none';
};

/**
 * Initialize all Add To Calendar blocks.
 *
 * This function initializes the behavior of Add To Calendar blocks on the page.
 * It sets up event listeners for click and keydown events to toggle the display
 * of the calendar options list. The function targets elements with the class
 * 'gp-add-to-calendar' and adds event listeners to handle user interactions.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
const addToCalendarInit = () => {
  const containers = document.querySelectorAll('.gp-add-to-calendar');
  for (let i = 0; i < containers.length; i++) {
    containers[i].querySelector('.gp-add-to-calendar__init').addEventListener('click', addToCalendarToggle, false);
    document.addEventListener('click', ({
      target
    }) => {
      if (!target.closest('.gp-add-to-calendar')) {
        containers[i].querySelector('.gp-add-to-calendar__list').style.display = 'none';
      }
    });
    document.addEventListener('keydown', ({
      key
    }) => {
      if ('Escape' === key) {
        containers[i].querySelector('.gp-add-to-calendar__list').style.display = 'none';
      }
    });
  }
};

/**
 * Callback for when the DOM is ready.
 *
 * This callback function is executed when the DOM is fully loaded and ready for manipulation.
 * It calls the `addToCalendarInit` function to initialize the behavior of Add To Calendar blocks
 * on the page, setting up event listeners for user interactions.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0___default()(() => {
  addToCalendarInit();
});
})();

/******/ })()
;
//# sourceMappingURL=add-to-calendar.js.map