/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/components/RsvpResponse.js":
/*!****************************************!*\
  !*** ./src/components/RsvpResponse.js ***!
  \****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _RsvpResponseHeader__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./RsvpResponseHeader */ "./src/components/RsvpResponseHeader.js");
/* harmony import */ var _RsvpResponseContent__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./RsvpResponseContent */ "./src/components/RsvpResponseContent.js");
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");

/**
 * WordPress dependencies.
 */



/**
 * Internal dependencies.
 */





/**
 * Component for displaying and managing RSVP responses.
 *
 * This component renders a user interface for managing RSVP responses to an event.
 * It includes options for attending, being on the waiting list, or not attending,
 * and updates the status based on user interactions. The component also listens for
 * changes in RSVP status and updates the state accordingly.
 *
 * @param {Object} root0               The destructured props object.
 * @param {string} root0.defaultStatus The current default status for the RSVP response, defaults to 'attending'.
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered RSVP response component.
 */
const RsvpResponse = ({
  defaultStatus = 'attending'
}) => {
  const defaultLimit = 8;
  const hasEventPast = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('eventDetails.hasEventPast');
  const items = [{
    title: false === hasEventPast ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__._x)('Attending', 'Responded Status', 'gatherpress') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__._x)('Went', 'Responded Status', 'gatherpress'),
    value: 'attending'
  }, {
    title: false === hasEventPast ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__._x)('Waiting List', 'Responded Status', 'gatherpress') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__._x)('Wait Listed', 'Responded Status', 'gatherpress'),
    value: 'waiting_list'
  }, {
    title: false === hasEventPast ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__._x)('Not Attending', 'Responded Status', 'gatherpress') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__._x)("Didn't Go", 'Responded Status', 'gatherpress'),
    value: 'not_attending'
  }];
  const [rsvpStatus, setRsvpStatus] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(defaultStatus);
  const [rsvpLimit, setRsvpLimit] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(defaultLimit);
  const onTitleClick = (e, value) => {
    e.preventDefault();
    setRsvpStatus(value);
  };
  (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_5__.Listener)({
    setRsvpStatus
  }, (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('eventDetails.postId'));

  // Make sure rsvpStatus is a valid status, if not, set to default.
  if (!items.some(item => item.value === rsvpStatus)) {
    setRsvpStatus(defaultStatus);
  }
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "gp-rsvp-response"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_RsvpResponseHeader__WEBPACK_IMPORTED_MODULE_3__["default"], {
    items: items,
    activeValue: rsvpStatus,
    onTitleClick: onTitleClick,
    rsvpLimit: rsvpLimit,
    setRsvpLimit: setRsvpLimit,
    defaultLimit: defaultLimit
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_RsvpResponseContent__WEBPACK_IMPORTED_MODULE_4__["default"], {
    items: items,
    activeValue: rsvpStatus,
    limit: rsvpLimit
  }));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (RsvpResponse);

/***/ }),

/***/ "./src/components/RsvpResponseCard.js":
/*!********************************************!*\
  !*** ./src/components/RsvpResponseCard.js ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");

/**
 * WordPress dependencies.
 */


/**
 * Internal dependencies.
 */


/**
 * RsvpResponseCard component for GatherPress.
 *
 * This component displays detailed information about attendees who have responded to an event's RSVP.
 * It receives information about the RSVP responses, including the attendee's profile link, name, photo, role,
 * and the number of guests. The component renders each attendee's information in a structured layout.
 * It also provides a message when no attendees are found for the specified RSVP status.
 * The component dynamically updates based on changes to the RSVP responses.
 *
 * @since 1.0.0
 *
 * @param {Object} props                - Component props.
 * @param {string} props.value          - The RSVP status value ('attending', 'not_attending', etc.).
 * @param {number} props.limit          - The maximum number of responses to display.
 * @param {Array}  [props.responses=[]] - An array of RSVP responses for the specified status.
 *
 * @return {JSX.Element} The rendered React component.
 */
const RsvpResponseCard = ({
  value,
  limit,
  responses = []
}) => {
  let renderedItems = '';
  if ('object' === typeof responses && 'undefined' !== typeof responses[value]) {
    responses = [...responses[value].responses];
    if (limit) {
      responses = responses.splice(0, limit);
    }
    renderedItems = responses.map((response, index) => {
      const {
        profile,
        name,
        photo,
        role
      } = response;
      const {
        guests
      } = response;
      return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
        key: index,
        className: "gp-rsvp-response__item"
      }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("figure", {
        className: "gp-rsvp-response__member-avatar"
      }, '' !== profile ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
        href: profile
      }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
        alt: name,
        title: name,
        src: photo
      })) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
        alt: name,
        title: name,
        src: photo
      })), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
        className: "gp-rsvp-response__member-info"
      }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
        className: "gp-rsvp-response__member-name"
      }, '' !== profile ? (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
        href: profile,
        title: name
      }, name) : (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, name)), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
        className: "gp-rsvp-response__member-role"
      }, role), 0 !== guests && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("small", {
        className: "gp-rsvp-response__guests"
      }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)( /* translators: %d: Number of guests. */
      (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__._n)('+%d guest', '+%d guests', guests, 'gatherpress'), guests))));
    });
  }
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, 'attending' === value && 0 === renderedItems.length && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "gp-rsvp-response__no-responses"
  }, false === (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_2__.getFromGlobal)('eventDetails.hasEventPast') ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No one is attending this event yet.', 'gatherpress') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No one went to this event.', 'gatherpress')), renderedItems);
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (RsvpResponseCard);

/***/ }),

/***/ "./src/components/RsvpResponseContent.js":
/*!***********************************************!*\
  !*** ./src/components/RsvpResponseContent.js ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _RsvpResponseCard__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./RsvpResponseCard */ "./src/components/RsvpResponseCard.js");
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");

/**
 * Internal dependencies.
 */





/**
 * RsvpResponseContent component for GatherPress.
 *
 * This component displays the content of RSVP responses based on the selected RSVP status.
 * It receives an array of items representing different RSVP statuses and renders the content
 * of the active status using the RsvpResponseCard component. The component dynamically updates
 * based on changes to the RSVP responses.
 *
 * @since 1.0.0
 *
 * @param {Object}         props               - Component props.
 * @param {Array}          props.items         - An array of objects representing different RSVP statuses.
 * @param {string}         props.activeValue   - The currently active RSVP status value.
 * @param {number|boolean} [props.limit=false] - The maximum number of responses to display or false for no limit.
 *
 * @return {JSX.Element} The rendered React component.
 */
const RsvpResponseContent = ({
  items,
  activeValue,
  limit = false
}) => {
  const postId = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_2__.getFromGlobal)('eventDetails.postId');
  const [rsvpResponse, setRsvpResponse] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)((0,_helpers_globals__WEBPACK_IMPORTED_MODULE_2__.getFromGlobal)('eventDetails.responses'));
  (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__.Listener)({
    setRsvpResponse
  }, postId);
  const renderedItems = items.map((item, index) => {
    const {
      value
    } = item;
    const active = value === activeValue;
    if (active) {
      return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
        key: index,
        className: "gp-rsvp-response__items",
        id: `gp-rsvp-${value}`,
        role: "tabpanel",
        "aria-labelledby": `gp-rsvp-${value}-tab`
      }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_RsvpResponseCard__WEBPACK_IMPORTED_MODULE_1__["default"], {
        value: value,
        limit: limit,
        responses: rsvpResponse
      }));
    }
    return '';
  });
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "gp-rsvp-response__content"
  }, renderedItems);
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (RsvpResponseContent);

/***/ }),

/***/ "./src/components/RsvpResponseHeader.js":
/*!**********************************************!*\
  !*** ./src/components/RsvpResponseHeader.js ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _RsvpResponseNavigation__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./RsvpResponseNavigation */ "./src/components/RsvpResponseNavigation.js");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");

/**
 * WordPress dependencies.
 */


/**
 * Internal dependencies.
 */





/**
 * RsvpResponseHeader component for GatherPress.
 *
 * This component represents the header of the RSVP response section. It includes the navigation
 * for different RSVP statuses, a toggle to show/hide more responses, and an icon for visual indication.
 * The component allows users to toggle the number of responses displayed based on the configured limit.
 *
 * @since 1.0.0
 *
 * @param {Object}         props              - Component props.
 * @param {Array}          props.items        - An array of objects representing different RSVP statuses.
 * @param {string}         props.activeValue  - The currently active RSVP status value.
 * @param {Function}       props.onTitleClick - Callback function triggered when a title is clicked.
 * @param {number|boolean} props.rsvpLimit    - The current limit of responses to display or false for no limit.
 * @param {Function}       props.setRsvpLimit - Callback function to set the new RSVP response limit.
 * @param {number}         props.defaultLimit - The default limit of responses to display.
 *
 * @return {JSX.Element} The rendered React component.
 */
const RsvpResponseHeader = ({
  items,
  activeValue,
  onTitleClick,
  rsvpLimit,
  setRsvpLimit,
  defaultLimit
}) => {
  const updateLimit = e => {
    e.preventDefault();
    if (false !== rsvpLimit) {
      setRsvpLimit(false);
    } else {
      setRsvpLimit(defaultLimit);
    }
  };
  let loadListText;
  if (false === rsvpLimit) {
    loadListText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('See fewer', 'gatherpress');
  } else {
    loadListText = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('See all', 'gatherpress');
  }
  let defaultRsvpSeeAllLink = false;
  const responses = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.getFromGlobal)('eventDetails.responses');
  if (responses && responses[activeValue]) {
    var _getFromGlobal$active;
    defaultRsvpSeeAllLink = ((_getFromGlobal$active = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.getFromGlobal)('eventDetails.responses')[activeValue].count) !== null && _getFromGlobal$active !== void 0 ? _getFromGlobal$active : 0) > defaultLimit;
  }
  const [rsvpSeeAllLink, setRsvpSeeAllLink] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(defaultRsvpSeeAllLink);
  (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__.Listener)({
    setRsvpSeeAllLink
  }, (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.getFromGlobal)('eventDetails.postId'));
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "gp-rsvp-response__header"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "dashicons dashicons-groups"
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_RsvpResponseNavigation__WEBPACK_IMPORTED_MODULE_2__["default"], {
    items: items,
    activeValue: activeValue,
    onTitleClick: onTitleClick,
    defaultLimit: defaultLimit
  }), rsvpSeeAllLink && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "gp-rsvp-response__see-all"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: "#",
    onClick: e => updateLimit(e)
  }, loadListText)));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (RsvpResponseHeader);

/***/ }),

/***/ "./src/components/RsvpResponseNavigation.js":
/*!**************************************************!*\
  !*** ./src/components/RsvpResponseNavigation.js ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _RsvpResponseNavigationItem__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./RsvpResponseNavigationItem */ "./src/components/RsvpResponseNavigationItem.js");
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");

/**
 * WordPress dependencies.
 */


/**
 * Internal dependencies.
 */




/**
 * RsvpResponseNavigation component for GatherPress.
 *
 * This component represents the navigation for different RSVP statuses. It includes a dropdown menu
 * to switch between RSVP statuses, displaying the count of responses for each status. The active RSVP
 * status is highlighted, and clicking on it toggles the dropdown menu. The component listens for
 * document clicks and keyboard events to close the dropdown when clicked outside or on the 'Escape' key.
 *
 * @since 1.0.0
 *
 * @param {Object}   props              - Component props.
 * @param {Array}    props.items        - An array of objects representing different RSVP statuses.
 * @param {string}   props.activeValue  - The currently active RSVP status value.
 * @param {Function} props.onTitleClick - Callback function triggered when a title is clicked.
 * @param {number}   props.defaultLimit - The default limit of responses to display.
 *
 * @return {JSX.Element} The rendered React component.
 */
const RsvpResponseNavigation = ({
  items,
  activeValue,
  onTitleClick,
  defaultLimit
}) => {
  var _getFromGlobal;
  const defaultCount = {
    all: 0,
    attending: 0,
    not_attending: 0,
    // eslint-disable-line camelcase
    waiting_list: 0 // eslint-disable-line camelcase
  };
  const responses = (_getFromGlobal = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('eventDetails.responses')) !== null && _getFromGlobal !== void 0 ? _getFromGlobal : {};
  for (const [key, value] of Object.entries(responses)) {
    defaultCount[key] = value.count;
  }
  const [rsvpCount, setRsvpCount] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(defaultCount);
  const [showNavigationDropdown, setShowNavigationDropdown] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [hideNavigationDropdown, setHideNavigationDropdown] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(true);
  const Tag = hideNavigationDropdown ? `span` : `a`;
  (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_3__.Listener)({
    setRsvpCount
  }, (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('eventDetails.postId'));
  let activeIndex = 0;
  const renderedItems = items.map((item, index) => {
    const activeItem = item.value === activeValue;
    if (activeItem) {
      activeIndex = index;
    }
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_RsvpResponseNavigationItem__WEBPACK_IMPORTED_MODULE_2__["default"], {
      key: index,
      item: item,
      count: rsvpCount[item.value],
      activeItem: activeItem,
      onTitleClick: onTitleClick,
      defaultLimit: defaultLimit
    });
  });
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    __webpack_require__.g.document.addEventListener('click', ({
      target
    }) => {
      if (!target.closest('.gp-rsvp-response__navigation-active')) {
        setShowNavigationDropdown(false);
      }
    });
    __webpack_require__.g.document.addEventListener('keydown', ({
      key
    }) => {
      if ('Escape' === key) {
        setShowNavigationDropdown(false);
      }
    });
  });
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    if (0 === rsvpCount.not_attending && 0 === rsvpCount.waiting_list) {
      setHideNavigationDropdown(true);
    } else {
      setHideNavigationDropdown(false);
    }
  }, [rsvpCount]);
  const toggleNavigation = e => {
    e.preventDefault();
    setShowNavigationDropdown(!showNavigationDropdown);
  };
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "gp-rsvp-response__navigation-wrapper"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Tag, {
    href: "#",
    className: "gp-rsvp-response__navigation-active",
    onClick: e => toggleNavigation(e)
  }, items[activeIndex].title), "\xA0", (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, "(", rsvpCount[activeValue], ")")), !hideNavigationDropdown && showNavigationDropdown && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("nav", {
    className: "gp-rsvp-response__navigation"
  }, renderedItems));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (RsvpResponseNavigation);

/***/ }),

/***/ "./src/components/RsvpResponseNavigationItem.js":
/*!******************************************************!*\
  !*** ./src/components/RsvpResponseNavigationItem.js ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");

/**
 * WordPress dependencies.
 */


/**
 * Internal dependencies.
 */



/**
 * RsvpResponseNavigationItem component for GatherPress.
 *
 * This component represents an individual navigation item for different RSVP statuses.
 * It includes a link or span based on whether the item is active, and displays the count
 * of responses for that status. Clicking on the item triggers the `onTitleClick` callback.
 * The component is used within the `RsvpResponseNavigation` component.
 *
 * @since 1.0.0
 *
 * @param {Object}   props                    - Component props.
 * @param {Object}   props.item               - An object representing an RSVP status with `title` and `value`.
 * @param {boolean}  [props.activeItem=false] - Indicates whether the item is currently active.
 * @param {number}   props.count              - The count of responses for the RSVP status.
 * @param {Function} props.onTitleClick       - Callback function triggered when a title is clicked.
 * @param {number}   props.defaultLimit       - The default limit of responses to display.
 *
 * @return {JSX.Element|null} The rendered React component or null if not active.
 */
const RsvpResponseNavigationItem = ({
  item,
  activeItem = false,
  count,
  onTitleClick,
  defaultLimit
}) => {
  const {
    title,
    value
  } = item;
  const active = !(0 === count && 'attending' !== value);
  const Tag = activeItem ? `span` : `a`;
  const postId = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_3__.getFromGlobal)('eventDetails.postId');
  const rsvpSeeAllLink = count > defaultLimit;
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    if (activeItem) {
      (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_2__.Broadcaster)({
        setRsvpSeeAllLink: rsvpSeeAllLink
      }, postId);
    }
  });
  if (active) {
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: "gp-rsvp-response__navigation-item"
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Tag, {
      className: "gp-rsvp-response__anchor",
      "data-item": value,
      "data-toggle": "tab",
      href: "#",
      role: "tab",
      "aria-controls": `#gp-rsvp-${value}`,
      onClick: e => onTitleClick(e, value)
    }, title), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      className: "gp-rsvp-response__count"
    }, "(", count, ")"));
  }
  return '';
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (RsvpResponseNavigationItem);

/***/ }),

/***/ "./src/helpers/broadcasting.js":
/*!*************************************!*\
  !*** ./src/helpers/broadcasting.js ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Broadcaster: () => (/* binding */ Broadcaster),
/* harmony export */   Listener: () => (/* binding */ Listener)
/* harmony export */ });
/**
 * Broadcasts custom events based on the provided payload, optionally appending an identifier to each event type.
 *
 * @since 1.0.0
 *
 * @param {Object} payload    - An object containing data to be dispatched with custom events.
 * @param {string} identifier - An optional identifier to append to each event type.
 *
 * @return {void}
 */
const Broadcaster = (payload, identifier = '') => {
  for (const [key, value] of Object.entries(payload)) {
    let type = key;
    if (identifier) {
      type += '_' + String(identifier);
    }
    const dispatcher = new CustomEvent(type, {
      detail: value
    });
    dispatchEvent(dispatcher);
  }
};

/**
 * Sets up event listeners for custom events based on the provided payload, optionally appending an identifier to each event type.
 * When an event is triggered, the corresponding listener callback is executed with the event detail.
 *
 * @since 1.0.0
 *
 * @param {Object} payload    - An object specifying event types and their corresponding listener callbacks.
 * @param {string} identifier - An optional identifier to append to each event type.
 *
 * @return {void}
 */
const Listener = (payload, identifier = '') => {
  for (const [key, value] of Object.entries(payload)) {
    let type = key;
    if (identifier) {
      type += '_' + String(identifier);
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
/* harmony export */   enableSave: () => (/* binding */ enableSave),
/* harmony export */   getFromGlobal: () => (/* binding */ getFromGlobal),
/* harmony export */   isSinglePostInEditor: () => (/* binding */ isSinglePostInEditor),
/* harmony export */   setToGlobal: () => (/* binding */ setToGlobal)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);


/**
 * Enable the Save buttons after making an update.
 *
 * This function uses a hacky approach to trigger a change in the post's meta, which prompts
 * Gutenberg to recognize that changes have been made and enables the Save buttons.
 * It dispatches an editPost action with a non-existing meta key.
 *
 * @since 1.0.0
 *
 * @todo This is a hacky approach and relies on the behavior described in
 *       https://github.com/WordPress/gutenberg/issues/13774.
 *       Monitor the issue for any updates or changes in the Gutenberg behavior.
 */
function enableSave() {
  (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.dispatch)('core/editor')?.editPost({
    meta: {
      _non_existing_meta: true
    }
  });
}

/**
 * Checks if the current editor session is editing a post type entity.
 *
 * This function determines if the current context within the WordPress editor
 * is focused on editing an entity that is classified as a post type. This includes
 * single posts, pages, and custom post types. It is particularly useful for distinguishing
 * editor sessions that are editing post type entities from those editing other types of content,
 * such as widget areas or templates in the full site editor, ensuring that specific actions or features
 * are correctly applied only when editing post type entities.
 *
 * @return {boolean} True if the current editor session is for editing a post type entity, false otherwise.
 */
function isSinglePostInEditor() {
  return 'string' === typeof (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.select)('core/editor')?.getCurrentPostType();
}

/**
 * Get a value from the global GatherPress object based on the provided dot-separated path.
 *
 * This function is designed to retrieve values from the global GatherPress object.
 * It takes a dot-separated path as an argument and traverses the object to return the specified value.
 * If the object or any level along the path is undefined, it returns undefined.
 *
 * @since 1.0.0
 *
 * @param {string} args - Dot-separated path to the desired property in the GatherPress global object.
 * @return {*} The value at the specified path in the GatherPress global object or undefined if not found.
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
 * Set a value to a global object based on the provided path.
 *
 * This function allows setting values within a nested global object using a dot-separated path.
 * If the global object (GatherPress) does not exist, it will be initialized.
 *
 * @since 1.0.0
 *
 * @param {string} args  - Dot-separated path to the property.
 * @param {*}      value - The value to set.
 *
 * @return {void}
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

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

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
/******/ 	/* webpack/runtime/global */
/******/ 	(() => {
/******/ 		__webpack_require__.g = (function() {
/******/ 			if (typeof globalThis === 'object') return globalThis;
/******/ 			try {
/******/ 				return this || new Function('return this')();
/******/ 			} catch (e) {
/******/ 				if (typeof window === 'object') return window;
/******/ 			}
/******/ 		})();
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
/*!***************************************************!*\
  !*** ./src/blocks/rsvp-response/rsvp-response.js ***!
  \***************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/dom-ready */ "@wordpress/dom-ready");
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _components_RsvpResponse__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../components/RsvpResponse */ "./src/components/RsvpResponse.js");

/**
 * WordPress dependencies.
 */



/**
 * Internal dependencies.
 */


/**
 * Initialize all GatherPress RSVP Response blocks.
 *
 * This code initializes the GatherPress RSVP Response blocks on the DOM when it's ready.
 * It selects all elements with the data attribute 'data-gp_block_name="rsvp-response"'
 * and renders the RsvpResponse component within those elements.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_1___default()(() => {
  const containers = document.querySelectorAll(`[data-gp_block_name="rsvp-response"]`);
  for (let i = 0; i < containers.length; i++) {
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.createRoot)(containers[i]).render((0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_RsvpResponse__WEBPACK_IMPORTED_MODULE_3__["default"], null));
  }
});
})();

/******/ })()
;
//# sourceMappingURL=rsvp-response.js.map