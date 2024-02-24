/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/components/Autocomplete.js":
/*!****************************************!*\
  !*** ./src/components/Autocomplete.js ***!
  \****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var lodash__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! lodash */ "lodash");
/* harmony import */ var lodash__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(lodash__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_6__);

/**
 * External dependencies.
 */


/**
 * WordPress dependencies.
 */






/**
 * Autocomplete component for GatherPress.
 *
 * This component renders an autocomplete field for selecting posts or other entities.
 * It uses a FormTokenField for the input, allowing users to select multiple items.
 * The selected items are stored in a hidden input field as JSON data.
 *
 * @since 1.0.0
 *
 * @param {Object} props                    - Component props.
 * @param {Object} props.attrs              - Attributes for configuring the Autocomplete field.
 * @param {string} props.attrs.name         - The name attribute for the input field.
 * @param {string} props.attrs.option       - The option attribute for identifying the field.
 * @param {string} props.attrs.value        - The value of the Autocomplete field.
 * @param {Object} props.attrs.fieldOptions - Additional options for configuring the field.
 *
 * @return {JSX.Element} The rendered React component.
 */
const Autocomplete = props => {
  var _JSON$parse, _contentList$reduce;
  const {
    name,
    option,
    value,
    fieldOptions
  } = props.attrs;
  const showHowTo = 1 !== fieldOptions.limit;
  const [content, setContent] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)((_JSON$parse = JSON.parse(value)) !== null && _JSON$parse !== void 0 ? _JSON$parse : '[]');
  const {
    contentList
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_6__.useSelect)(select => {
    const {
      getEntityRecords
    } = select(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_5__.store);
    const entityType = 'user' !== fieldOptions.type ? 'postType' : 'root';
    const kind = fieldOptions.type || 'post';
    return {
      contentList: getEntityRecords(entityType, kind, {
        per_page: -1,
        context: 'view'
      })
    };
  }, [fieldOptions.type]);
  const contentSuggestions = (_contentList$reduce = contentList?.reduce((accumulator, item) => ({
    ...accumulator,
    [item.title?.rendered || item.name]: item
  }), {})) !== null && _contentList$reduce !== void 0 ? _contentList$reduce : {};
  const selectContent = tokens => {
    const hasNoSuggestion = tokens.some(token => typeof token === 'string' && !contentSuggestions[token]);
    if (hasNoSuggestion) {
      return;
    }
    const allContent = tokens.map(token => {
      return typeof token === 'string' ? contentSuggestions[token] : token;
    });
    if ((0,lodash__WEBPACK_IMPORTED_MODULE_1__.includes)(allContent, null)) {
      return false;
    }
    setContent(allContent);
  };
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FormTokenField, {
    key: option,
    label: fieldOptions.label || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Select Posts', 'gatherpress'),
    name: name,
    value: content && content.map(item => ({
      id: item.id,
      slug: item.slug,
      value: item.title?.rendered || item.name || item.value
    })),
    suggestions: Object.keys(contentSuggestions),
    onChange: selectContent,
    maxSuggestions: fieldOptions.max_suggestions || 20,
    maxLength: fieldOptions.limit || 0,
    __experimentalShowHowTo: showHowTo
  }), false === showHowTo && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    className: "description"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Choose only one item.', 'gatherpress')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("input", {
    type: "hidden",
    id: option,
    name: name,
    value: content && JSON.stringify(content.map(item => ({
      id: item.id,
      slug: item.slug,
      value: item.title?.rendered || item.name || item.value
    })))
  }));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Autocomplete);

/***/ }),

/***/ "./src/components/DateTimePreview.js":
/*!*******************************************!*\
  !*** ./src/components/DateTimePreview.js ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/date */ "@wordpress/date");
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_date__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);

/**
 * WordPress dependencies.
 */



/**
 * DateTimePreview component for GatherPress.
 *
 * This component renders a preview of the formatted date and time based on the specified format.
 * It listens for the 'input' event on the input field with the specified name and updates
 * the state with the new date and time format. The formatted preview is displayed accordingly.
 *
 * @since 1.0.0
 *
 * @param {Object} props             - Component props.
 * @param {Object} props.attrs       - Component attributes.
 * @param {string} props.attrs.name  - The name of the input field.
 * @param {string} props.attrs.value - The initial value of the input field (date and time format).
 *
 * @return {JSX.Element} The rendered React component.
 */
const DateTimePreview = props => {
  const {
    name,
    value
  } = props.attrs;
  const [dateTimeFormat, setDateTimeFormat] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(value);
  const input = document.querySelector(`[name="${name}"]`);
  input.addEventListener('input', e => {
    setDateTimeFormat(e.target.value);
  }, {
    once: true
  });
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, dateTimeFormat && (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_1__.format)(dateTimeFormat));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (DateTimePreview);

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ }),

/***/ "lodash":
/*!*************************!*\
  !*** external "lodash" ***!
  \*************************/
/***/ ((module) => {

module.exports = window["lodash"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/core-data":
/*!**********************************!*\
  !*** external ["wp","coreData"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["coreData"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["data"];

/***/ }),

/***/ "@wordpress/date":
/*!******************************!*\
  !*** external ["wp","date"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["date"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

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
/*!*******************************!*\
  !*** ./src/settings/index.js ***!
  \*******************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _components_Autocomplete__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../components/Autocomplete */ "./src/components/Autocomplete.js");
/* harmony import */ var _components_DateTimePreview__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../components/DateTimePreview */ "./src/components/DateTimePreview.js");

/**
 * WordPress dependencies.
 */


/**
 * Internal dependencies.
 */



/**
 * Autocomplete Initialization
 *
 * This script initializes the autocomplete functionality for all elements
 * with the attribute 'data-gp_component_name' set to 'autocomplete'.
 * It iterates through all matching elements and initializes an Autocomplete component
 * with the attributes provided in the 'data-gp_component_attrs' attribute.
 *
 * @since 1.0.0
 */

// Select all elements with the attribute 'data-gp_component_name' set to 'autocomplete'.
const autocompleteContainers = document.querySelectorAll(`[data-gp_component_name="autocomplete"]`);

// Iterate through each matched element and initialize Autocomplete component.
for (let i = 0; i < autocompleteContainers.length; i++) {
  // Parse attributes from the 'data-gp_component_attrs' attribute.
  const attrs = JSON.parse(autocompleteContainers[i].dataset.gp_component_attrs);

  // Create a root element and render the Autocomplete component with the parsed attributes.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createRoot)(autocompleteContainers[i]).render((0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_Autocomplete__WEBPACK_IMPORTED_MODULE_2__["default"], {
    attrs: attrs
  }));
}

/**
 * DateTime Preview Initialization
 *
 * This script initializes the DateTime Preview functionality for all elements
 * with the attribute 'data-gp_component_name' set to 'datetime-preview'.
 * It iterates through all matching elements and initializes a DateTimePreview component
 * with the attributes provided in the 'data-gp_component_attrs' attribute.
 *
 * @since 1.0.0
 */

// Select all elements with the attribute 'data-gp_component_name' set to 'datetime-preview'.
const dateTimePreviewContainers = document.querySelectorAll(`[data-gp_component_name="datetime-preview"]`);

// Iterate through each matched element and initialize DateTimePreview component.
for (let i = 0; i < dateTimePreviewContainers.length; i++) {
  // Parse attributes from the 'data-gp_component_attrs' attribute.
  const attrs = JSON.parse(dateTimePreviewContainers[i].dataset.gp_component_attrs);

  // Create a root element and render the DateTimePreview component with the parsed attributes.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createRoot)(dateTimePreviewContainers[i]).render((0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_DateTimePreview__WEBPACK_IMPORTED_MODULE_3__["default"], {
    attrs: attrs
  }));
}
})();

/******/ })()
;
//# sourceMappingURL=settings.js.map