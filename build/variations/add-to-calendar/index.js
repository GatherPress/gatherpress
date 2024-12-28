/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/@wordpress/icons/build-module/library/calendar.js":
/*!************************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/calendar.js ***!
  \************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
/**
 * WordPress dependencies
 */


const calendar = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, {
  viewBox: "0 0 24 24",
  xmlns: "http://www.w3.org/2000/svg",
  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, {
    d: "M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm.5 16c0 .3-.2.5-.5.5H5c-.3 0-.5-.2-.5-.5V7h15v12zM9 10H7v2h2v-2zm0 4H7v2h2v-2zm4-4h-2v2h2v-2zm4 0h-2v2h2v-2zm-4 4h-2v2h2v-2zm4 0h-2v2h2v-2z"
  })
});
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (calendar);
//# sourceMappingURL=calendar.js.map

/***/ }),

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["ReactJSXRuntime"];

/***/ }),

/***/ "@wordpress/blocks":
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
/***/ ((module) => {

module.exports = window["wp"]["blocks"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "@wordpress/primitives":
/*!************************************!*\
  !*** external ["wp","primitives"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["primitives"];

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
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!*************************************************!*\
  !*** ./src/variations/add-to-calendar/index.js ***!
  \*************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/calendar.js");
/**
 * WordPress dependencies
 */



const NAME = 'gatherpress-add-to-calendar-details';
const VARIATION_ATTRIBUTES = {
  category: 'gatherpress',
  isActive: ['metadata.bindings.url.args.service'],
  example: {}
};
const BUTTON_ATTRIBUTES = {
  tagName: 'a' // By setting this to 'button', instead of 'a', we can completely prevent the LinkControl getting rendered into the Toolbar.
};
const SERVICES = [{
  service: 'google',
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Google', 'gatherpress'),
  title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Add event to your Google calendar.', 'gatherpress')
}, {
  service: 'ical',
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('iCal', 'gatherpress'),
  title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Download event as iCal file.', 'gatherpress')
}, {
  service: 'outlook',
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Outlook', 'gatherpress'),
  title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Download event as Outlook file.', 'gatherpress')
}, {
  service: 'yahoo',
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Yahoo', 'gatherpress'),
  title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Add event to your Yahoo calendar.', 'gatherpress')
}];

// Helper to generate button attributes based on service.
function createButtonAttributes(serviceData) {
  const {
    service,
    title,
    text
  } = serviceData;
  return {
    ...BUTTON_ATTRIBUTES,
    title,
    text,
    rel: service === 'google' || service === 'yahoo' ? 'noopener norefferrer' : null,
    linkTarget: service === 'google' || service === 'yahoo' ? '_blank' : null,
    placeholder: text,
    metadata: {
      bindings: {
        url: {
          source: 'gatherpress/add-to-calendar',
          args: {
            service
          }
        }
      },
      name: text
    }
  };
}

/**
 * Registers multiple block variations of the 'core/button' block, one per each calendar service.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
 */
SERVICES.forEach(serviceData => {
  const attributes = createButtonAttributes(serviceData);
  (0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockVariation)('core/button', {
    ...VARIATION_ATTRIBUTES,
    name: `${NAME}-${serviceData.service}`,
    title: serviceData.text,
    description: serviceData.title,
    attributes
  });
});

// Generate innerBlocks array dynamically based on the services.
const INNER_BLOCKS = SERVICES.map(serviceData => ['core/button', createButtonAttributes(serviceData)]);

/**
 * A Trap block, that looks like a single button, hohoho.
 *
 * This block-variation is only useful, because a user can pick the block directly from the inserter or the left sidebar.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
 */
(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockVariation)('core/buttons', {
  title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Add to calendar (BUTTONS)', 'gatherpress'),
  description: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Allows a user to add an event to their preferred calendar.', 'gatherpress'),
  category: 'gatherpress',
  icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_2__["default"],
  name: `pseudo-${NAME}`,
  // isActive: [ 'namespace', 'title' ], // This is not used/disabled by purpose.
  innerBlocks: INNER_BLOCKS,
  example: {
    innerBlocks: SERVICES.map(({
      text
    }) => ({
      name: 'core/button',
      attributes: {
        text
      }
    }))
  }
});
(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockVariation)('core/details', {
  title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Add to calendar (DETAILS)', 'gatherpress'),
  description: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Allows a user to add an event to their preferred calendar.', 'gatherpress'),
  category: 'gatherpress',
  icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_2__["default"],
  name: `pseudo-details-${NAME}`,
  // isActive: [ 'namespace', 'title' ], // This is not used/disabled by purpose.
  attributes: {
    summary: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Add to calendar', 'gatherpress')
  },
  innerBlocks: [['core/buttons', {}, INNER_BLOCKS]],
  example: {
    innerBlocks: [['core/buttons', {}, INNER_BLOCKS]]
  }
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map