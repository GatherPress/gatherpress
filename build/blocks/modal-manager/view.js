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
/*!******************************************!*\
  !*** ./src/blocks/modal-manager/view.js ***!
  \******************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/**
 * WordPress dependencies.
 */

const {
  actions
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('gatherpress', {
  actions: {
    openModal(event = null, element = null) {
      var _element;
      if (event) {
        event.preventDefault();
      }
      element = (_element = element) !== null && _element !== void 0 ? _element : event.target;
      const modalManager = element.closest('.wp-block-gatherpress-modal-manager');
      if (modalManager) {
        const modal = modalManager.querySelector('.wp-block-gatherpress-modal');
        if (modal) {
          modal.classList.add('gatherpress--is-visible');
          modal.setAttribute('aria-hidden', 'false');
          const modalContent = modal.querySelector('.wp-block-gatherpress-modal-content');

          // Trap focus when the modal opens.
          const focusableSelectors = ['a[href]', 'button:not([disabled])', 'textarea:not([disabled])', 'input[type="text"]:not([disabled])', 'input[type="radio"]:not([disabled])', 'input[type="checkbox"]:not([disabled])', 'select:not([disabled])', '[tabindex]:not([tabindex="-1"])'];
          const focusableElements = modalContent.querySelectorAll(focusableSelectors.join(','));
          const firstFocusableElement = focusableElements[0];
          const lastFocusableElement = focusableElements[focusableElements.length - 1];

          // Automatically focus the first focusable element.
          if (firstFocusableElement) {
            firstFocusableElement.focus();
          }

          // Trap focus within the modal content.
          const handleFocusTrap = e => {
            if ('Tab' === e.key) {
              if (e.shiftKey && __webpack_require__.g.document.activeElement === firstFocusableElement) {
                // Shift + Tab (backward navigation).
                e.preventDefault();
                lastFocusableElement.focus();
              } else if (!e.shiftKey && __webpack_require__.g.document.activeElement === lastFocusableElement) {
                // Tab (forward navigation).
                e.preventDefault();
                firstFocusableElement.focus();
              }
            }
          };

          // Add keydown listener for trapping focus.
          modalContent.addEventListener('keydown', handleFocusTrap);

          // Cleanup focus trapping when the modal is closed.
          const closeButton = modal.querySelector('.gatherpress--close-modal');
          if (closeButton) {
            closeButton.addEventListener('click', () => {
              modal.classList.remove('gatherpress--is-visible');
              modalContent.removeEventListener('keydown', handleFocusTrap);
            });
          }
        }
      }
    },
    openModalKeyHandler(event) {
      if ('Enter' === event.key || ' ' === event.key) {
        actions.openModal(event);
      }
    },
    closeModal(event = null, element = null) {
      var _element2;
      if (event) {
        event.preventDefault();
      }
      element = (_element2 = element) !== null && _element2 !== void 0 ? _element2 : event.target;
      const modalManager = element.closest('.wp-block-gatherpress-modal-manager');
      if (modalManager) {
        const modal = modalManager.querySelector('.wp-block-gatherpress-modal');
        if (modal) {
          modal.classList.remove('gatherpress--is-visible');
          modal.setAttribute('aria-hidden', 'true');
        }
      }
    },
    closeModalOnEnter(event) {
      if ('Enter' === event.key) {
        actions.closeModal(event);
      }
    }
  }
});
})();


//# sourceMappingURL=view.js.map