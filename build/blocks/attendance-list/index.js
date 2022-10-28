/******/ (function() { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/blocks/attendance-list/edit.js":
/*!********************************************!*\
  !*** ./src/blocks/attendance-list/edit.js ***!
  \********************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _components_AttendanceList__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../components/AttendanceList */ "./src/components/AttendanceList.js");

/**
 * WordPress dependencies.
 */


/**
 * Internal dependencies.
 */

const Edit = () => {
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)();
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", blockProps, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_AttendanceList__WEBPACK_IMPORTED_MODULE_2__["default"], null));
};
/* harmony default export */ __webpack_exports__["default"] = (Edit);

/***/ }),

/***/ "./src/blocks/attendance-list/index.js":
/*!*********************************************!*\
  !*** ./src/blocks/attendance-list/index.js ***!
  \*********************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./edit */ "./src/blocks/attendance-list/edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./block.json */ "./src/blocks/attendance-list/block.json");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./style.scss */ "./src/blocks/attendance-list/style.scss");
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

/***/ "./src/components/AttendanceList.js":
/*!******************************************!*\
  !*** ./src/components/AttendanceList.js ***!
  \******************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _AttendanceListNavigation__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./AttendanceListNavigation */ "./src/components/AttendanceListNavigation.js");
/* harmony import */ var _AttendanceListContent__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./AttendanceListContent */ "./src/components/AttendanceListContent.js");
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");






const AttendanceList = () => {
  let defaultStatus = 'attending';
  const items = [{
    title:
    // eslint-disable-next-line no-undef
    '1' !== GatherPress.has_event_past ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Attending', 'gatherpress') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Went', 'gatherpress'),
    value: 'attending'
  }, {
    title:
    // eslint-disable-next-line no-undef
    '1' !== GatherPress.has_event_past ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Waiting List', 'gatherpress') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Wait Listed', 'gatherpress'),
    value: 'waiting_list'
  }, {
    title:
    // eslint-disable-next-line no-undef
    '1' !== GatherPress.has_event_past ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Not Attending', 'gatherpress') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Didn't Go", 'gatherpress'),
    value: 'not_attending'
  }];
  if ('object' === typeof GatherPress) {
    // @todo redo this logic and have it come from API and not GatherPress object.
    defaultStatus =
    // eslint-disable-next-line no-undef
    'undefined' !== typeof GatherPress.current_user.status &&
    // eslint-disable-next-line no-undef
    'attend' !== GatherPress.current_user.status
    // eslint-disable-next-line no-undef
    ? GatherPress.current_user.status : defaultStatus;
  }
  const defaultLimit = 10;
  const [attendanceStatus, setAttendanceStatus] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(defaultStatus);
  const [attendeeLimit, setAttendeeLimit] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(defaultLimit);

  // eslint-disable-next-line no-undef
  (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__.Listener)({
    setAttendanceStatus
  }, GatherPress.post_id);
  const onTitleClick = (e, value) => {
    e.preventDefault();
    setAttendanceStatus(value);
  };
  const updateLimit = e => {
    e.preventDefault();
    if (false !== attendeeLimit) {
      setAttendeeLimit(false);
    } else {
      setAttendeeLimit(defaultLimit);
    }
  };
  let loadListText;
  if (false === attendeeLimit) {
    loadListText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('See less', 'gatherpress');
  } else {
    loadListText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('See more', 'gatherpress');
  }
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "gp-attendance-list"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_AttendanceListNavigation__WEBPACK_IMPORTED_MODULE_2__["default"], {
    items: items,
    activeValue: attendanceStatus,
    onTitleClick: onTitleClick
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_AttendanceListContent__WEBPACK_IMPORTED_MODULE_3__["default"], {
    items: items,
    activeValue: attendanceStatus,
    limit: attendeeLimit
  })), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "has-text-align-right"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: "#",
    onClick: e => updateLimit(e)
  }, loadListText)));
};
/* harmony default export */ __webpack_exports__["default"] = (AttendanceList);

/***/ }),

/***/ "./src/components/AttendanceListContent.js":
/*!*************************************************!*\
  !*** ./src/components/AttendanceListContent.js ***!
  \*************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _AttendeeList__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./AttendeeList */ "./src/components/AttendeeList.js");



const AttendanceListContent = _ref => {
  let {
    items,
    activeValue,
    limit = false
  } = _ref;
  const renderedItems = items.map((item, index) => {
    const {
      value
    } = item;
    const active = value === activeValue ? 'active' : 'hidden';
    // eslint-disable-next-line no-undef
    const postId = GatherPress.post_id;
    // eslint-disable-next-line no-undef
    const attendees = GatherPress.attendees;
    return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      key: index,
      className: `gp-attendance-list__items gp-attendance-list__${active}`,
      id: `gp-attendance-${value}`,
      role: "tabpanel",
      "aria-labelledby": `gp-attendance-${value}-tab`
    }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_AttendeeList__WEBPACK_IMPORTED_MODULE_2__["default"], {
      eventId: postId,
      value: value,
      limit: limit,
      attendees: attendees
    }));
  });
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "gp-attendance-list__container"
  }, renderedItems);
};
/* harmony default export */ __webpack_exports__["default"] = (AttendanceListContent);

/***/ }),

/***/ "./src/components/AttendanceListNavigation.js":
/*!****************************************************!*\
  !*** ./src/components/AttendanceListNavigation.js ***!
  \****************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _AttendanceListNavigationItem__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./AttendanceListNavigationItem */ "./src/components/AttendanceListNavigationItem.js");
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");




const AttendanceListNavigation = _ref => {
  let {
    items,
    activeValue,
    onTitleClick
  } = _ref;
  const defaultCount = {
    all: 0,
    attending: 0,
    not_attending: 0,
    // eslint-disable-line camelcase
    waiting_list: 0 // eslint-disable-line camelcase
  };

  if ('object' === typeof GatherPress) {
    // eslint-disable-next-line no-undef
    for (const [key, value] of Object.entries(GatherPress.attendees)) {
      defaultCount[key] = value.count;
    }
  }
  const [attendanceCount, setAttendanceCount] = (0,react__WEBPACK_IMPORTED_MODULE_1__.useState)(defaultCount);

  // eslint-disable-next-line no-undef
  (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_3__.Listener)({
    setAttendanceCount
  }, GatherPress.post_id);
  const renderedItems = items.map((item, index) => {
    const additionalClasses = item.value === activeValue ? 'gp-attendance-list__navigation--current' : '';
    return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_AttendanceListNavigationItem__WEBPACK_IMPORTED_MODULE_2__["default"], {
      key: index,
      item: item,
      count: attendanceCount[item.value],
      additionalClasses: additionalClasses,
      onTitleClick: onTitleClick
    });
  });
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("nav", {
    className: "gp-attendance-list__navigation"
  }, renderedItems);
};
/* harmony default export */ __webpack_exports__["default"] = (AttendanceListNavigation);

/***/ }),

/***/ "./src/components/AttendanceListNavigationItem.js":
/*!********************************************************!*\
  !*** ./src/components/AttendanceListNavigationItem.js ***!
  \********************************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);


const AttendanceListNavigationItem = _ref => {
  let {
    item,
    additionalClasses,
    count,
    onTitleClick
  } = _ref;
  const {
    title,
    value
  } = item;
  const active = 0 === count && 'attending' !== value ? 'hidden' : 'active';
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: `gp-attendance-list__navigation--item gp-attendance-list__${active} ${additionalClasses}`
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    className: "gp-attendance-list__anchor",
    "data-item": value,
    "data-toggle": "tab",
    href: "#",
    role: "tab",
    "aria-controls": `#gp-attendance-${value}`,
    onClick: e => onTitleClick(e, value)
  }, title), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "gp-attendance-list__count"
  }, "(", count, ")"));
};
/* harmony default export */ __webpack_exports__["default"] = (AttendanceListNavigationItem);

/***/ }),

/***/ "./src/components/AttendeeList.js":
/*!****************************************!*\
  !*** ./src/components/AttendeeList.js ***!
  \****************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");

/**
 * WordPress dependencies.
 */



/**
 * Internal dependencies.
 */

const AttendeeList = _ref => {
  let {
    eventId,
    value,
    limit,
    attendees = [],
    avatarOnly = false
  } = _ref;
  const [attendanceList, setAttendanceList] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(attendees);
  (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_2__.Listener)({
    setAttendanceList
  }, eventId);
  let renderedItems = '';
  if ('object' === typeof attendanceList && 'undefined' !== typeof attendanceList[value]) {
    attendees = [...attendanceList[value].attendees];
    if (limit) {
      attendees = attendees.splice(0, limit);
    }
    renderedItems = attendees.map((attendee, index) => {
      const {
        profile,
        name,
        photo,
        role
      } = attendee;
      let {
        guests
      } = attendee;
      if (guests) {
        guests = ' +' + guests + ' guest(s)';
      } else {
        guests = '';
      }
      return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
        key: index,
        className: "gp-attendance-list__items--item"
      }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("figure", {
        className: "gp-attendance-list__member-avatar"
      }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
        href: profile
      }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
        alt: name,
        title: name,
        src: photo
      }))), false === avatarOnly && (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
        className: "gp-attendance-list__member-info"
      }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
        className: "gp-attendance-list__member-name"
      }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
        href: profile
      }, name)), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
        className: "gp-attendance-list__member-role"
      }, role), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("small", {
        className: "gp-attendance-list__guests"
      }, guests)));
    });
  }
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, 'attending' === value && 0 === renderedItems.length && false === avatarOnly && (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "gp-attendance-list__no-attendees"
  }, '1' !== GatherPress.has_event_past ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No one is attending this event yet.', 'gatherpress') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No one went to this event.', 'gatherpress')), renderedItems);
};
/* harmony default export */ __webpack_exports__["default"] = (AttendeeList);

/***/ }),

/***/ "./src/helpers/broadcasting.js":
/*!*************************************!*\
  !*** ./src/helpers/broadcasting.js ***!
  \*************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "Broadcaster": function() { return /* binding */ Broadcaster; },
/* harmony export */   "Listener": function() { return /* binding */ Listener; }
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

/***/ "./src/blocks/attendance-list/style.scss":
/*!***********************************************!*\
  !*** ./src/blocks/attendance-list/style.scss ***!
  \***********************************************/
/***/ (function(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ (function(module) {

module.exports = window["React"];

/***/ }),

/***/ "@wordpress/block-editor":
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
/***/ (function(module) {

module.exports = window["wp"]["blockEditor"];

/***/ }),

/***/ "@wordpress/blocks":
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
/***/ (function(module) {

module.exports = window["wp"]["blocks"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ (function(module) {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ (function(module) {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "./src/blocks/attendance-list/block.json":
/*!***********************************************!*\
  !*** ./src/blocks/attendance-list/block.json ***!
  \***********************************************/
/***/ (function(module) {

module.exports = JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":2,"name":"gatherpress/attendance-list","version":"0.1.1","title":"Attendance List","category":"gatherpress","icon":"groups","example":{},"description":"The block with attendance list.","attributes":{"blockId":{"type":"string"},"content":{"type":"string"},"color":{"type":"string"}},"supports":{"html":false},"textdomain":"gatherpress","editorScript":"file:./index.js","style":"file:./style-index.css"}');

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
/******/ 	!function() {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = function(result, chunkIds, fn, priority) {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var chunkIds = deferred[i][0];
/******/ 				var fn = deferred[i][1];
/******/ 				var priority = deferred[i][2];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every(function(key) { return __webpack_require__.O[key](chunkIds[j]); })) {
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
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	!function() {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = function(module) {
/******/ 			var getter = module && module.__esModule ?
/******/ 				function() { return module['default']; } :
/******/ 				function() { return module; };
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	!function() {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = function(exports, definition) {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	!function() {
/******/ 		__webpack_require__.o = function(obj, prop) { return Object.prototype.hasOwnProperty.call(obj, prop); }
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	!function() {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = function(exports) {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	}();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	!function() {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"blocks/attendance-list/index": 0,
/******/ 			"blocks/attendance-list/style-index": 0
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
/******/ 		__webpack_require__.O.j = function(chunkId) { return installedChunks[chunkId] === 0; };
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = function(parentChunkLoadingFunction, data) {
/******/ 			var chunkIds = data[0];
/******/ 			var moreModules = data[1];
/******/ 			var runtime = data[2];
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some(function(id) { return installedChunks[id] !== 0; })) {
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
/******/ 		var chunkLoadingGlobal = self["webpackChunkgatherpress"] = self["webpackChunkgatherpress"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	}();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["blocks/attendance-list/style-index"], function() { return __webpack_require__("./src/blocks/attendance-list/index.js"); })
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=index.js.map