/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/helpers/globals.js":
/*!********************************!*\
  !*** ./src/helpers/globals.js ***!
  \********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "enableSave": () => (/* binding */ enableSave),
/* harmony export */   "getFromGlobal": () => (/* binding */ getFromGlobal),
/* harmony export */   "setToGlobal": () => (/* binding */ setToGlobal)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);


// @todo hack approach to enabling Save buttons after update
// https://github.com/WordPress/gutenberg/issues/13774
function enableSave() {
  (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.dispatch)('core/editor').editPost({
    meta: {
      _non_existing_meta: true
    }
  });
}

/**
 * Helper to safely retrieve from the GatherPress global variable.
 *
 * @param {string} args
 * @return {undefined|*} Returns value of arguments provided.
 */
function getFromGlobal(args) {
  // eslint-disable-next-line no-undef
  if ('object' !== typeof GatherPress) {
    return undefined;
  }
  return args.split('.').reduce(
  // eslint-disable-next-line no-undef
  (GatherPress, level) => GatherPress && GatherPress[level],
  // eslint-disable-next-line no-undef
  GatherPress);
}

/**
 * Helper to safely set to the GatherPress global variable.
 *
 * @param {string} args
 * @param {any}    value
 */
function setToGlobal(args, value) {
  // eslint-disable-next-line no-undef
  if ('object' !== typeof GatherPress) {
    return;
  }
  const properties = args.split('.');
  const last = properties.pop();

  // eslint-disable-next-line no-undef
  properties.reduce((all, item) => {
    var _all$item;
    return (_all$item = all[item]) !== null && _all$item !== void 0 ? _all$item : all[item] = {};
  }, GatherPress)[last] = value;
}

/***/ }),

/***/ "@wordpress/blocks":
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
/***/ ((module) => {

module.exports = window["wp"]["blocks"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["data"];

/***/ }),

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
/*!**********************!*\
  !*** ./src/admin.js ***!
  \**********************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/dom-ready */ "@wordpress/dom-ready");
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./helpers/globals */ "./src/helpers/globals.js");
/**
 * WordPress dependencies.
 */



/**
 * Internal dependencies.
 */


/**
 * Remove unwanted blocks from localized array.
 */
_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0___default()(() => {
  Object.keys((0,_helpers_globals__WEBPACK_IMPORTED_MODULE_2__.getFromGlobal)('unregister_blocks')).forEach(key => {
    const blockName = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_2__.getFromGlobal)('unregister_blocks')[key];
    if (blockName && 'undefined' !== typeof (0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_1__.getBlockType)(blockName)) {
      (0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_1__.unregisterBlockType)(blockName);
    }
  });
});
})();

/******/ })()
;
//# sourceMappingURL=admin.js.map