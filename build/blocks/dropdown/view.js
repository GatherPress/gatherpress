import * as __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__ from "@wordpress/interactivity";
/******/ var __webpack_modules__ = ({

/***/ "@wordpress/interactivity":
/*!*******************************************!*\
  !*** external "@wordpress/interactivity" ***!
  \*******************************************/
/***/ ((module) => {

module.exports = __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__;

/***/ })

/******/ });
/************************************************************************/
/******/ // The module cache
/******/ var __webpack_module_cache__ = {};
/******/ 
/******/ // The require function
/******/ function __webpack_require__(moduleId) {
/******/ 	// Check if module is in cache
/******/ 	var cachedModule = __webpack_module_cache__[moduleId];
/******/ 	if (cachedModule !== undefined) {
/******/ 		return cachedModule.exports;
/******/ 	}
/******/ 	// Create a new module (and put it into the cache)
/******/ 	var module = __webpack_module_cache__[moduleId] = {
/******/ 		// no module.id needed
/******/ 		// no module.loaded needed
/******/ 		exports: {}
/******/ 	};
/******/ 
/******/ 	// Execute the module function
/******/ 	__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 
/******/ 	// Return the exports of the module
/******/ 	return module.exports;
/******/ }
/******/ 
/************************************************************************/
/******/ /* webpack/runtime/global */
/******/ (() => {
/******/ 	__webpack_require__.g = (function() {
/******/ 		if (typeof globalThis === 'object') return globalThis;
/******/ 		try {
/******/ 			return this || new Function('return this')();
/******/ 		} catch (e) {
/******/ 			if (typeof window === 'object') return window;
/******/ 		}
/******/ 	})();
/******/ })();
/******/ 
/******/ /* webpack/runtime/make namespace object */
/******/ (() => {
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = (exports) => {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/ })();
/******/ 
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!*************************************!*\
  !*** ./src/blocks/dropdown/view.js ***!
  \*************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/**
 * WordPress dependencies.
 */

(0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('gatherpress', {
  actions: {
    toggleDropdown(event) {
      event.preventDefault();
      const element = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
      const menu = element.ref.parentElement.querySelector('.wp-block-gatherpress-dropdown__menu');
      const trigger = element.ref.parentElement.querySelector('.wp-block-gatherpress-dropdown__trigger');

      // Define focus trap logic
      const focusableSelectors = ['a[href]'];
      const focusableElements = [trigger,
      // Include the trigger for focus trapping
      ...menu.querySelectorAll(focusableSelectors.join(','))];
      const firstFocusableElement = focusableElements[0];
      const lastFocusableElement = focusableElements[focusableElements.length - 1];
      const handleFocusTrap = e => {
        if ('Tab' === e.key) {
          if (e.shiftKey && document.activeElement === firstFocusableElement) {
            // Shift + Tab (backward navigation).
            e.preventDefault();
            lastFocusableElement.focus();
          } else if (!e.shiftKey && document.activeElement === lastFocusableElement) {
            // Tab (forward navigation).
            e.preventDefault();
            firstFocusableElement.focus();
          }
        }
      };

      // Handle Escape key to close the dropdown.
      const handleEscapeKey = e => {
        if ('Escape' === e.key) {
          menu.classList.remove('gatherpress--is-visible');
          trigger.setAttribute('aria-expanded', 'false');
          trigger.focus();
        }
        if ('Escape' === e.key || 'Enter' === e.key) {
          cleanupEventListeners();
        }
      };

      // Cleanup event listeners.
      const cleanupEventListeners = () => {
        menu.removeEventListener('keydown', handleFocusTrap);
        trigger.removeEventListener('keydown', handleFocusTrap);
        __webpack_require__.g.document.removeEventListener('keydown', handleEscapeKey);
      };
      if (menu) {
        const isVisible = menu.classList.toggle('gatherpress--is-visible');

        // Update aria-expanded based on visibility.
        if (trigger) {
          trigger.setAttribute('aria-expanded', isVisible ? 'true' : 'false');
        }
        if (isVisible) {
          // Open dropdown: trap focus and add event listeners.
          firstFocusableElement.focus();
          menu.addEventListener('keydown', handleFocusTrap);
          trigger.addEventListener('keydown', handleFocusTrap);
          __webpack_require__.g.document.addEventListener('keydown', handleEscapeKey);
        } else {
          // Close dropdown: remove event listeners.
          cleanupEventListeners();
        }
      }
    }
  }
});
})();


//# sourceMappingURL=view.js.map