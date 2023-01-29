/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/components/AttendanceList.js":
/*!******************************************!*\
  !*** ./src/components/AttendanceList.js ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _AttendanceListNavigation__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./AttendanceListNavigation */ "./src/components/AttendanceListNavigation.js");
/* harmony import */ var _AttendanceListContent__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./AttendanceListContent */ "./src/components/AttendanceListContent.js");
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");







const AttendanceList = () => {
  const defaultLimit = 10;
  let defaultStatus = 'attending';
  const hasEventPast = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.getFromGlobal)('has_event_past');
  const currentUserStatus = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.getFromGlobal)('current_user.status');
  const items = [{
    title: false === hasEventPast ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Attending', 'gatherpress') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Went', 'gatherpress'),
    value: 'attending'
  }, {
    title: false === hasEventPast ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Waiting List', 'gatherpress') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Wait Listed', 'gatherpress'),
    value: 'waiting_list'
  }, {
    title: false === hasEventPast ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Not Attending', 'gatherpress') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)("Didn't Go", 'gatherpress'),
    value: 'not_attending'
  }];

  // @todo redo this logic and have it come from API and not GatherPress object.
  defaultStatus = 'undefined' !== typeof currentUserStatus && 'attend' !== currentUserStatus ? currentUserStatus : defaultStatus;
  const [attendanceStatus, setAttendanceStatus] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(defaultStatus);
  const [attendeeLimit, setAttendeeLimit] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(defaultLimit);
  (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__.Listener)({
    setAttendanceStatus
  }, (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.getFromGlobal)('post_id'));
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
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (AttendanceList);

/***/ }),

/***/ "./src/components/AttendanceListContent.js":
/*!*************************************************!*\
  !*** ./src/components/AttendanceListContent.js ***!
  \*************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _AttendeeList__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./AttendeeList */ "./src/components/AttendeeList.js");
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");

/**
 * Internal dependencies.
 */


const AttendanceListContent = _ref => {
  let {
    items,
    activeValue,
    limit = false
  } = _ref;
  const postId = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_2__.getFromGlobal)('post_id');
  const attendees = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_2__.getFromGlobal)('attendees');
  const renderedItems = items.map((item, index) => {
    const {
      value
    } = item;
    const active = value === activeValue ? 'active' : 'hidden';
    return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      key: index,
      className: `gp-attendance-list__items gp-attendance-list__${active}`,
      id: `gp-attendance-${value}`,
      role: "tabpanel",
      "aria-labelledby": `gp-attendance-${value}-tab`
    }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_AttendeeList__WEBPACK_IMPORTED_MODULE_1__["default"], {
      eventId: postId,
      value: value,
      limit: limit,
      attendees: attendees
    }));
  });
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "gp-attendance-list__content"
  }, renderedItems);
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (AttendanceListContent);

/***/ }),

/***/ "./src/components/AttendanceListNavigation.js":
/*!****************************************************!*\
  !*** ./src/components/AttendanceListNavigation.js ***!
  \****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _AttendanceListNavigationItem__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./AttendanceListNavigationItem */ "./src/components/AttendanceListNavigationItem.js");
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");

/**
 * WordPress dependencies.
 */


/**
 * Internal dependencies.
 */



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

  for (const [key, value] of Object.entries((0,_helpers_globals__WEBPACK_IMPORTED_MODULE_3__.getFromGlobal)('attendees'))) {
    defaultCount[key] = value.count;
  }
  const [attendanceCount, setAttendanceCount] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(defaultCount);
  (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_2__.Listener)({
    setAttendanceCount
  }, (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_3__.getFromGlobal)('post_id'));
  const renderedItems = items.map((item, index) => {
    const additionalClasses = item.value === activeValue ? 'gp-attendance-list__current' : '';
    return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_AttendanceListNavigationItem__WEBPACK_IMPORTED_MODULE_1__["default"], {
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
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (AttendanceListNavigation);

/***/ }),

/***/ "./src/components/AttendanceListNavigationItem.js":
/*!********************************************************!*\
  !*** ./src/components/AttendanceListNavigationItem.js ***!
  \********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);

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
    className: `gp-attendance-list__navigation-item gp-attendance-list__${active} ${additionalClasses}`
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
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (AttendanceListNavigationItem);

/***/ }),

/***/ "./src/components/AttendeeList.js":
/*!****************************************!*\
  !*** ./src/components/AttendeeList.js ***!
  \****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");

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
        className: "gp-attendance-list__item"
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
  }, false === (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_3__.getFromGlobal)('has_event_past') ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No one is attending this event yet.', 'gatherpress') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No one went to this event.', 'gatherpress')), renderedItems);
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (AttendeeList);

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
/*!*******************************************************!*\
  !*** ./src/blocks/attendance-list/attendance-list.js ***!
  \*******************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/dom-ready */ "@wordpress/dom-ready");
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _components_AttendanceList__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../components/AttendanceList */ "./src/components/AttendanceList.js");

/**
 * WordPress dependencies.
 */



/**
 * Internal dependencies.
 */

_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_1___default()(() => {
  const containers = document.querySelectorAll(`[data-gp_block_name="attendance-list"]`);
  for (let i = 0; i < containers.length; i++) {
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.render)((0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_AttendanceList__WEBPACK_IMPORTED_MODULE_2__["default"], null), containers[i]);
  }
});
})();

/******/ })()
;
//# sourceMappingURL=attendance-list.js.map