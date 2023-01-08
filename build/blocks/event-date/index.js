/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/blocks/event-date/edit.js":
/*!***************************************!*\
  !*** ./src/blocks/event-date/edit.js ***!
  \***************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../helpers/datetime */ "./src/helpers/datetime.js");
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../helpers/broadcasting */ "./src/helpers/broadcasting.js");

/**
 * External dependencies.
 */


/**
 * WordPress dependencies.
 */





/**
 * Internal dependencies.
 */


/**
 * Similar to get_display_datetime method in class-event.php.
 *
 * @param {string} start
 * @param {string} end
 * @return {string} Displayed date.
 */
const displayDateTime = (start, end) => {
  const dateFormat = 'dddd, MMMM D, YYYY';
  const timeFormat = 'h:mm A';
  const timeZoneFormat = 'z';
  // eslint-disable-next-line no-undef
  const startFormat = dateFormat + ' ' + timeFormat;
  let endFormat = dateFormat + ' ' + timeFormat + ' ' + timeZoneFormat;
  if (moment__WEBPACK_IMPORTED_MODULE_1___default().tz(start, _helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.timeZone).format(dateFormat) === moment__WEBPACK_IMPORTED_MODULE_1___default().tz(end, _helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.timeZone).format(dateFormat)) {
    endFormat = timeFormat + ' ' + timeZoneFormat;
  }
  return moment__WEBPACK_IMPORTED_MODULE_1___default().tz(start, _helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.timeZone).format(startFormat) + ' to ' + moment__WEBPACK_IMPORTED_MODULE_1___default().tz(end, _helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.timeZone).format(endFormat);
};
const Edit = () => {
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.useBlockProps)();
  const [dateTimeStart, setDateTimeStart] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(
  // eslint-disable-next-line no-undef
  GatherPress.event_datetime.datetime_start);
  const [dateTimeEnd, setDateTimeEnd] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(
  // eslint-disable-next-line no-undef
  GatherPress.event_datetime.datetime_end);
  (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_5__.Listener)({
    setDateTimeEnd,
    setDateTimeStart
  });
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", blockProps, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Flex, {
    justify: "normal",
    align: "flex-start",
    gap: "4"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.FlexItem, {
    display: "flex",
    className: "gp-event-date__icon"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Icon, {
    icon: "clock"
  })), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.FlexItem, null, displayDateTime(dateTimeStart, dateTimeEnd))));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Edit);

/***/ }),

/***/ "./src/blocks/event-date/index.js":
/*!****************************************!*\
  !*** ./src/blocks/event-date/index.js ***!
  \****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./edit */ "./src/blocks/event-date/edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./block.json */ "./src/blocks/event-date/block.json");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./style.scss */ "./src/blocks/event-date/style.scss");
/**
 * WordPress dependencies.
 */


/**
 * Internal dependencies.
 */



(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_2__, {
  edit: _edit__WEBPACK_IMPORTED_MODULE_1__["default"],
  save: () => null
});

/***/ }),

/***/ "./src/helpers/broadcasting.js":
/*!*************************************!*\
  !*** ./src/helpers/broadcasting.js ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "Broadcaster": () => (/* binding */ Broadcaster),
/* harmony export */   "Listener": () => (/* binding */ Listener)
/* harmony export */ });
const Broadcaster = function (payload) {
  let identifier = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;
  for (const [key, value] of Object.entries(payload)) {
    let type = key;
    if (identifier) {
      type += identifier;
    }
    const dispatcher = new CustomEvent(type, {
      detail: value
    });
    dispatchEvent(dispatcher);
  }
};
const Listener = function (payload) {
  let identifier = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;
  for (const [key, value] of Object.entries(payload)) {
    let type = key;
    if (identifier) {
      type += identifier;
    }
    addEventListener(type, e => {
      value(e.detail);
    }, false);
  }
};

/***/ }),

/***/ "./src/helpers/datetime.js":
/*!*********************************!*\
  !*** ./src/helpers/datetime.js ***!
  \*********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "dateTimeDatabaseFormat": () => (/* binding */ dateTimeDatabaseFormat),
/* harmony export */   "dateTimeLabelFormat": () => (/* binding */ dateTimeLabelFormat),
/* harmony export */   "dateTimeMomentFormat": () => (/* binding */ dateTimeMomentFormat),
/* harmony export */   "defaultDateTimeStart": () => (/* binding */ defaultDateTimeStart),
/* harmony export */   "getDateTimeEnd": () => (/* binding */ getDateTimeEnd),
/* harmony export */   "getDateTimeStart": () => (/* binding */ getDateTimeStart),
/* harmony export */   "saveDateTime": () => (/* binding */ saveDateTime),
/* harmony export */   "timeZone": () => (/* binding */ timeZone),
/* harmony export */   "updateDateTimeEnd": () => (/* binding */ updateDateTimeEnd),
/* harmony export */   "updateDateTimeStart": () => (/* binding */ updateDateTimeStart),
/* harmony export */   "validateDateTimeEnd": () => (/* binding */ validateDateTimeEnd),
/* harmony export */   "validateDateTimeStart": () => (/* binding */ validateDateTimeStart)
/* harmony export */ });
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _misc__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./misc */ "./src/helpers/misc.js");
/* harmony import */ var _event__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./event */ "./src/helpers/event.js");
/**
 * External dependencies.
 */


/**
 * WordPress dependencies.
 */



/**
 * Internal dependencies.
 */


const dateTimeMomentFormat = 'YYYY-MM-DDTHH:mm:ss';
const dateTimeDatabaseFormat = 'YYYY-MM-DD HH:mm:ss';
const dateTimeLabelFormat = 'MMMM D, YYYY h:mm a z';
// eslint-disable-next-line no-undef
const timeZone = GatherPress.event_datetime.timezone;
const defaultDateTimeStart = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(timeZone).add(1, 'day').set('hour', 18).set('minute', 0).set('second', 0).format(dateTimeMomentFormat);
const getDateTimeStart = () => {
  // eslint-disable-next-line no-undef
  let dateTime = GatherPress.event_datetime.datetime_start;
  dateTime = '' !== dateTime ? moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTime, timeZone).format(dateTimeMomentFormat) : defaultDateTimeStart;

  // eslint-disable-next-line no-undef
  GatherPress.event_datetime.datetime_start = dateTime;
  return dateTime;
};
const getDateTimeEnd = () => {
  // eslint-disable-next-line no-undef
  let dateTime = GatherPress.event_datetime.datetime_end;
  dateTime = '' !== dateTime ? moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTime, timeZone).format(dateTimeMomentFormat) : moment__WEBPACK_IMPORTED_MODULE_0___default().tz(defaultDateTimeStart, timeZone).add(2, 'hours').format(dateTimeMomentFormat);

  // eslint-disable-next-line no-undef
  GatherPress.event_datetime.datetime_end = dateTime;
  return dateTime;
};
const updateDateTimeStart = function (date) {
  let setDateTimeStart = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;
  validateDateTimeStart(date);

  // eslint-disable-next-line no-undef
  GatherPress.event_datetime.datetime_start = date;
  if ('function' === typeof setDateTimeStart) {
    setDateTimeStart(date);
  }
  (0,_misc__WEBPACK_IMPORTED_MODULE_3__.enableSave)();
};
const updateDateTimeEnd = function (date) {
  let setDateTimeEnd = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;
  validateDateTimeEnd(date);

  // eslint-disable-next-line no-undef
  GatherPress.event_datetime.datetime_end = date;
  if (null !== setDateTimeEnd) {
    setDateTimeEnd(date);
  }
  (0,_misc__WEBPACK_IMPORTED_MODULE_3__.enableSave)();
};
function validateDateTimeStart(dateTimeStart) {
  const dateTimeEndNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(
  // eslint-disable-next-line no-undef
  GatherPress.event_datetime.datetime_end, timeZone).valueOf();
  const dateTimeStartNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeStart, timeZone).valueOf();
  if (dateTimeStartNumeric >= dateTimeEndNumeric) {
    const dateTimeEnd = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeStartNumeric, timeZone).add(2, 'hours').format(dateTimeMomentFormat);
    updateDateTimeEnd(dateTimeEnd);
  }
}
function validateDateTimeEnd(dateTimeEnd) {
  const dateTimeStartNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(
  // eslint-disable-next-line no-undef
  GatherPress.event_datetime.datetime_start, timeZone).valueOf();
  const dateTimeEndNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeEnd, timeZone).valueOf();
  if (dateTimeEndNumeric <= dateTimeStartNumeric) {
    const dateTimeStart = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeEndNumeric, timeZone).subtract(2, 'hours').format(dateTimeMomentFormat);
    updateDateTimeStart(dateTimeStart);
  }
}
function saveDateTime() {
  const isSavingPost = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor').isSavingPost(),
    isAutosavingPost = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor').isAutosavingPost();
  if ((0,_event__WEBPACK_IMPORTED_MODULE_4__.isEventPostType)() && isSavingPost && !isAutosavingPost) {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
      path: '/gatherpress/v1/event/datetime/',
      method: 'POST',
      data: {
        // eslint-disable-next-line no-undef
        post_id: GatherPress.post_id,
        datetime_start: moment__WEBPACK_IMPORTED_MODULE_0___default().tz(
        // eslint-disable-next-line no-undef
        GatherPress.event_datetime.datetime_start, timeZone).format(dateTimeDatabaseFormat),
        datetime_end: moment__WEBPACK_IMPORTED_MODULE_0___default().tz(
        // eslint-disable-next-line no-undef
        GatherPress.event_datetime.datetime_end, timeZone).format(dateTimeDatabaseFormat),
        // eslint-disable-next-line no-undef
        _wpnonce: GatherPress.nonce
      }
    }).then(() => {
      // Saved.
    });
  }
}

/***/ }),

/***/ "./src/helpers/event.js":
/*!******************************!*\
  !*** ./src/helpers/event.js ***!
  \******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "CheckCurrentPostType": () => (/* binding */ CheckCurrentPostType),
/* harmony export */   "hasEventPast": () => (/* binding */ hasEventPast),
/* harmony export */   "hasEventPastNotice": () => (/* binding */ hasEventPastNotice),
/* harmony export */   "isEventPostType": () => (/* binding */ isEventPostType)
/* harmony export */ });
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _datetime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./datetime */ "./src/helpers/datetime.js");
/**
 * External dependencies.
 */


/**
 * WordPress dependencies.
 */



/**
 * Internal dependencies.
 */

function isEventPostType() {
  return 'gp_event' === (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor').getCurrentPostType();
}
function CheckCurrentPostType() {
  return wp.data.select('core/editor').getCurrentPostType();
}
function hasEventPast() {
  // eslint-disable-next-line no-undef
  const dateTimeEnd = moment__WEBPACK_IMPORTED_MODULE_0___default()(GatherPress.event_datetime.datetime_end);
  return moment__WEBPACK_IMPORTED_MODULE_0___default().tz(_datetime__WEBPACK_IMPORTED_MODULE_3__.timeZone).valueOf() > dateTimeEnd.tz(_datetime__WEBPACK_IMPORTED_MODULE_3__.timeZone).valueOf();
}
function hasEventPastNotice() {
  const id = 'gp_event_past';
  const notices = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.dispatch)('core/notices');
  notices.removeNotice(id);
  if (hasEventPast()) {
    notices.createNotice('warning', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('This event has already past.', 'gatherpress'), {
      id,
      isDismissible: false
    });
  }
}

/***/ }),

/***/ "./src/helpers/misc.js":
/*!*****************************!*\
  !*** ./src/helpers/misc.js ***!
  \*****************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "enableSave": () => (/* binding */ enableSave)
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

/***/ }),

/***/ "./src/blocks/event-date/style.scss":
/*!******************************************!*\
  !*** ./src/blocks/event-date/style.scss ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "moment":
/*!*************************!*\
  !*** external "moment" ***!
  \*************************/
/***/ ((module) => {

module.exports = window["moment"];

/***/ }),

/***/ "@wordpress/api-fetch":
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["apiFetch"];

/***/ }),

/***/ "@wordpress/block-editor":
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
/***/ ((module) => {

module.exports = window["wp"]["blockEditor"];

/***/ }),

/***/ "@wordpress/blocks":
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
/***/ ((module) => {

module.exports = window["wp"]["blocks"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["data"];

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

/***/ }),

/***/ "./src/blocks/event-date/block.json":
/*!******************************************!*\
  !*** ./src/blocks/event-date/block.json ***!
  \******************************************/
/***/ ((module) => {

module.exports = JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":2,"name":"gatherpress/event-date","version":"0.2","title":"Event Date","category":"gatherpress","icon":"clock","example":{},"description":"The block with event dates.","attributes":{"blockId":{"type":"string"}},"supports":{"html":false},"textdomain":"gatherpress","editorScript":"file:./index.js","style":"file:./style-index.css","render":"file:./render.php"}');

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
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
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
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"blocks/event-date/index": 0,
/******/ 			"blocks/event-date/style-index": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = globalThis["webpackChunkgatherpress"] = globalThis["webpackChunkgatherpress"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["blocks/event-date/style-index"], () => (__webpack_require__("./src/blocks/event-date/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=index.js.map