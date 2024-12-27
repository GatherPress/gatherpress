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
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!*********************!*\
  !*** ./src/main.js ***!
  \*********************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/dom-ready */ "@wordpress/dom-ready");
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0__);
/**
 * WordPress dependencies.
 */

const setupModalCloseHandlers = () => {
  // Function to close a modal
  const closeModal = modal => {
    modal.classList.remove('gatherpress--is-visible');
  };

  // Handle Escape key to close modals
  const handleEscapeKey = event => {
    if (event.key === 'Escape') {
      const openModals = document.querySelectorAll('.gatherpress--is-visible');
      openModals.forEach(modal => closeModal(modal));
    }
  };

  // Handle clicks outside modal content
  const handleOutsideClick = event => {
    const openModals = document.querySelectorAll('.wp-block-gatherpress-modal.gatherpress--is-visible');
    openModals.forEach(modal => {
      const modalContent = modal.querySelector('.wp-block-gatherpress-modal-content');

      // Close modal if the click is outside the modal content
      if (modal.contains(event.target) &&
      // Click is inside the modal
      !modalContent.contains(event.target) // Click is NOT inside the modal content
      ) {
        closeModal(modal);
      }
    });
  };

  // Attach event listeners
  document.addEventListener('keydown', handleEscapeKey);
  document.addEventListener('click', handleOutsideClick);
};
const setupDropdownCloseHandlers = () => {
  // Function to close a dropdown
  const closeDropdown = dropdown => {
    dropdown.classList.remove('gatherpress--is-visible');
  };

  // Handle Escape key to close dropdowns
  const handleEscapeKey = event => {
    if (event.key === 'Escape') {
      const openDropdowns = document.querySelectorAll('.wp-block-gatherpress-dropdown__menu.gatherpress--is-visible');
      openDropdowns.forEach(dropdown => closeDropdown(dropdown));
    }
  };

  // Handle clicks outside dropdown content
  const handleOutsideClick = event => {
    const openDropdowns = document.querySelectorAll('.wp-block-gatherpress-dropdown__menu.gatherpress--is-visible');
    openDropdowns.forEach(dropdown => {
      const dropdownParent = dropdown.closest('.wp-block-gatherpress-dropdown');

      // Close dropdown if the click is outside the dropdown
      if (dropdownParent && !dropdownParent.contains(event.target) // Click is NOT inside the dropdown parent
      ) {
        closeDropdown(dropdown);
      }
    });
  };

  // Attach event listeners
  document.addEventListener('keydown', handleEscapeKey);
  document.addEventListener('click', handleOutsideClick);
};
_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_0___default()(() => {
  setupModalCloseHandlers();
  setupDropdownCloseHandlers();
});
})();

/******/ })()
;
//# sourceMappingURL=main.js.map