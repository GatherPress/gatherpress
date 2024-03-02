/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/components/AnonymousRsvp.js":
/*!*****************************************!*\
  !*** ./src/components/AnonymousRsvp.js ***!
  \*****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");

/**
 * WordPress dependencies.
 */





/**
 * Internal dependencies.
 */


/**
 * AnonymousRsvp component.
 *
 * This component renders a checkbox control that allows toggling the anonymous RSVP feature for an event.
 * It handles the state and updates the post's metadata accordingly. When creating a new event, the default
 * state of the checkbox is determined by a global setting. For existing events, it uses the event's current
 * setting. The component ensures that changes are reflected in the post's metadata and also unlocks post saving.
 *
 * @return {JSX.Element} A checkbox control for enabling or disabling anonymous RSVPs.
 */
const AnonymousRsvp = () => {
  const {
    editPost,
    unlockPostSaving
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useDispatch)('core/editor');
  const isNewEvent = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useSelect)(select => {
    return select('core/editor').isCleanNewPost();
  }, []);
  let defaultAnonymousRsvp = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useSelect)(select => {
    return select('core/editor').getEditedPostAttribute('meta').enable_anonymous_rsvp;
  }, []);
  if (isNewEvent) {
    defaultAnonymousRsvp = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.getFromGlobal)('settings.enableAnonymousRsvp');
  }
  const [anonymousRsvp, setAnonymousRsvp] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(defaultAnonymousRsvp);
  const updateAnonymousRsvp = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useCallback)(value => {
    const meta = {
      enable_anonymous_rsvp: Number(value)
    };
    setAnonymousRsvp(value);
    editPost({
      meta
    });
    unlockPostSaving();
  }, [editPost, unlockPostSaving]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (isNewEvent && defaultAnonymousRsvp !== 0) {
      updateAnonymousRsvp(defaultAnonymousRsvp);
    }
  }, [isNewEvent, defaultAnonymousRsvp, updateAnonymousRsvp]);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CheckboxControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Enable Anonymous RSVP', 'gatherpress'),
    checked: anonymousRsvp,
    onChange: value => {
      updateAnonymousRsvp(value);
    }
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (AnonymousRsvp);

/***/ }),

/***/ "./src/components/DateTime.js":
/*!************************************!*\
  !*** ./src/components/DateTime.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   DateTimeEndLabel: () => (/* binding */ DateTimeEndLabel),
/* harmony export */   DateTimeEndPicker: () => (/* binding */ DateTimeEndPicker),
/* harmony export */   DateTimeStartLabel: () => (/* binding */ DateTimeStartLabel),
/* harmony export */   DateTimeStartPicker: () => (/* binding */ DateTimeStartPicker)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/date */ "@wordpress/date");
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_date__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../helpers/datetime */ "./src/helpers/datetime.js");

/**
 * WordPress dependencies.
 */




/**
 * Internal dependencies.
 */


/**
 * Formats the provided start date and time according to the specified label format
 * and returns the formatted result in the time zone configured for the plugin.
 *
 * @since 1.0.0
 *
 * @param {Object} props               - The properties object containing the start date and time.
 * @param {string} props.dateTimeStart - The start date and time to be formatted.
 *
 * @return {string} Formatted date and time label based on the configured format and time zone.
 */
const DateTimeStartLabel = props => {
  const {
    dateTimeStart
  } = props;
  return moment__WEBPACK_IMPORTED_MODULE_3___default().tz(dateTimeStart, (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.getTimeZone)()).format((0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.dateTimeLabelFormat)());
};

/**
 * Formats the provided end date and time according to the specified label format
 * and returns the formatted result in the time zone configured for the plugin.
 *
 * @since 1.0.0
 *
 * @param {Object} props               - The properties object containing the end date and time.
 * @param {string} props.dateTimeStart - The end date and time to be formatted.
 *
 * @return {string} Formatted date and time label based on the configured format and time zone.
 */
const DateTimeEndLabel = props => {
  const {
    dateTimeEnd
  } = props;
  return moment__WEBPACK_IMPORTED_MODULE_3___default().tz(dateTimeEnd, (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.getTimeZone)()).format((0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.dateTimeLabelFormat)());
};

/**
 * DateTimeStartPicker component for GatherPress.
 *
 * This component renders a DateTimePicker for selecting the start date and time of an event.
 * It takes the current date and time, as well as a callback function to update the state.
 * The component is configured based on the site's time settings (12-hour or 24-hour format).
 *
 * @since 1.0.0
 *
 * @param {Object}   props                  - Component props.
 * @param {Date}     props.dateTimeStart    - The current date and time for the picker.
 * @param {Function} props.setDateTimeStart - Callback function to update the start date and time.
 *
 * @return {JSX.Element} The rendered React component.
 */
const DateTimeStartPicker = props => {
  const {
    dateTimeStart,
    setDateTimeStart
  } = props;
  const settings = (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_1__.getSettings)();
  const is12HourTime = /a(?!\\)/i.test(settings.formats.time.toLowerCase().replace(/\\\\/g, '').split('').reverse().join(''));
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DateTimePicker, {
    currentDate: dateTimeStart,
    onChange: date => (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.updateDateTimeStart)(date, setDateTimeStart),
    is12Hour: is12HourTime
  });
};

/**
 * DateTimeEndPicker component for GatherPress.
 *
 * This component renders a DateTimePicker for selecting the end date and time of an event.
 * It takes the current date and time, as well as a callback function to update the state.
 * The component is configured based on the site's time settings (12-hour or 24-hour format).
 *
 * @since 1.0.0
 *
 * @param {Object}   props                - Component props.
 * @param {Date}     props.dateTimeEnd    - The current date and time for the picker.
 * @param {Function} props.setDateTimeEnd - Callback function to update the end date and time.
 *
 * @return {JSX.Element} The rendered React component.
 */
const DateTimeEndPicker = props => {
  const {
    dateTimeEnd,
    setDateTimeEnd
  } = props;
  const settings = (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_1__.getSettings)();
  const is12HourTime = /a(?!\\)/i.test(settings.formats.time.toLowerCase().replace(/\\\\/g, '').split('').reverse().join(''));
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DateTimePicker, {
    currentDate: dateTimeEnd,
    onChange: date => (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.updateDateTimeEnd)(date, setDateTimeEnd),
    is12Hour: is12HourTime
  });
};

/***/ }),

/***/ "./src/components/DateTimeEnd.js":
/*!***************************************!*\
  !*** ./src/components/DateTimeEnd.js ***!
  \***************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _DateTime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./DateTime */ "./src/components/DateTime.js");
/* harmony import */ var _helpers_event__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../helpers/event */ "./src/helpers/event.js");
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../helpers/datetime */ "./src/helpers/datetime.js");

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
 * DateTimeEnd component for GatherPress.
 *
 * This component renders the end date and time selection in the editor.
 * It includes a DateTimeEndPicker for selecting the end date and time.
 * The component also updates the state using the setDateTimeEnd callback.
 * Additionally, it broadcasts the end date and time using the Broadcaster utility.
 * If the event has passed, it displays a notice using hasEventPastNotice function.
 *
 * @since 1.0.0
 *
 * @param {Object}   props                - Component props.
 * @param {Date}     props.dateTimeEnd    - The current date and time for the picker.
 * @param {Function} props.setDateTimeEnd - Callback function to update the end date and time.
 *
 * @return {JSX.Element} The rendered React component.
 */
const DateTimeEnd = props => {
  const {
    dateTimeEnd,
    setDateTimeEnd
  } = props;
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useEffect)(() => {
    setDateTimeEnd(moment__WEBPACK_IMPORTED_MODULE_1___default().tz((0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_8__.getDateTimeEnd)(), (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_8__.getTimeZone)()).format(_helpers_datetime__WEBPACK_IMPORTED_MODULE_8__.dateTimeMomentFormat));
    (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_7__.Broadcaster)({
      setDateTimeEnd: dateTimeEnd
    });
    (0,_helpers_event__WEBPACK_IMPORTED_MODULE_6__.hasEventPastNotice)();
  });
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelRow, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Flex, {
    direction: "column",
    gap: "0"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FlexItem, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    htmlFor: "gp-datetime-end"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('End', 'gatherpress'))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FlexItem, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Dropdown, {
    popoverProps: {
      placement: 'bottom-end'
    },
    renderToggle: ({
      isOpen,
      onToggle
    }) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
      id: "gp-datetime-end",
      onClick: onToggle,
      "aria-expanded": isOpen,
      isLink: true
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_DateTime__WEBPACK_IMPORTED_MODULE_5__.DateTimeEndLabel, {
      dateTimeEnd: dateTimeEnd
    })),
    renderContent: () => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_DateTime__WEBPACK_IMPORTED_MODULE_5__.DateTimeEndPicker, {
      dateTimeEnd: dateTimeEnd,
      setDateTimeEnd: setDateTimeEnd
    })
  }))));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (DateTimeEnd);

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

/***/ "./src/components/DateTimeRange.js":
/*!*****************************************!*\
  !*** ./src/components/DateTimeRange.js ***!
  \*****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../helpers/datetime */ "./src/helpers/datetime.js");
/* harmony import */ var _components_DateTimeStart__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../components/DateTimeStart */ "./src/components/DateTimeStart.js");
/* harmony import */ var _components_DateTimeEnd__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../components/DateTimeEnd */ "./src/components/DateTimeEnd.js");
/* harmony import */ var _components_TimeZone__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../components/TimeZone */ "./src/components/TimeZone.js");

/**
 * WordPress dependencies.
 */




/**
 * Internal dependencies.
 */





/**
 * DateTimeRange component for GatherPress.
 *
 * This component manages the date and time range selection. It includes
 * DateTimeStart, DateTimeEnd, and TimeZone components. The selected values
 * for start date and time, end date and time, and timezone are managed in the
 * component's state. The component subscribes to the saveDateTime function,
 * which is triggered to save the selected date and time values.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const DateTimeRange = () => {
  const [dateTimeStart, setDateTimeStart] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)();
  const [dateTimeEnd, setDateTimeEnd] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)();
  const [timezone, setTimezone] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)();
  (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.subscribe)(_helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.saveDateTime);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h3", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Date & time', 'gatherpress')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_DateTimeStart__WEBPACK_IMPORTED_MODULE_5__["default"], {
    dateTimeStart: dateTimeStart,
    setDateTimeStart: setDateTimeStart
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_DateTimeEnd__WEBPACK_IMPORTED_MODULE_6__["default"], {
    dateTimeEnd: dateTimeEnd,
    setDateTimeEnd: setDateTimeEnd
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_TimeZone__WEBPACK_IMPORTED_MODULE_7__["default"], {
    timezone: timezone,
    setTimezone: setTimezone
  }));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (DateTimeRange);

/***/ }),

/***/ "./src/components/DateTimeStart.js":
/*!*****************************************!*\
  !*** ./src/components/DateTimeStart.js ***!
  \*****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _DateTime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./DateTime */ "./src/components/DateTime.js");
/* harmony import */ var _helpers_event__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../helpers/event */ "./src/helpers/event.js");
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../helpers/datetime */ "./src/helpers/datetime.js");

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
 * DateTimeStart component for GatherPress.
 *
 * This component manages the selection of the start date and time. It uses
 * DateTimeStartPicker for the user to pick the date and time. The selected
 * values are formatted and broadcasted using Broadcaster. The component
 * subscribes to the saveDateTime function and triggers the hasEventPastNotice
 * function to handle any event past notices.
 *
 * @since 1.0.0
 *
 * @param {Object}   props                  - Component properties.
 * @param {string}   props.dateTimeStart    - The current start date and time.
 * @param {Function} props.setDateTimeStart - Function to set the start date and time.
 *
 * @return {JSX.Element} The rendered React component.
 */
const DateTimeStart = props => {
  const {
    dateTimeStart,
    setDateTimeStart
  } = props;
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useEffect)(() => {
    setDateTimeStart(moment__WEBPACK_IMPORTED_MODULE_1___default().tz((0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_8__.getDateTimeStart)(), (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_8__.getTimeZone)()).format(_helpers_datetime__WEBPACK_IMPORTED_MODULE_8__.dateTimeMomentFormat));
    (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_7__.Broadcaster)({
      setDateTimeStart: dateTimeStart
    });
    (0,_helpers_event__WEBPACK_IMPORTED_MODULE_6__.hasEventPastNotice)();
  });
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelRow, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Flex, {
    direction: "column",
    gap: "0"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FlexItem, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    htmlFor: "gp-datetime-start"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Start', 'gatherpress'))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FlexItem, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Dropdown, {
    popoverProps: {
      placement: 'bottom-end'
    },
    renderToggle: ({
      isOpen,
      onToggle
    }) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
      id: "gp-datetime-start",
      onClick: onToggle,
      "aria-expanded": isOpen,
      isLink: true
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_DateTime__WEBPACK_IMPORTED_MODULE_5__.DateTimeStartLabel, {
      dateTimeStart: dateTimeStart
    })),
    renderContent: () => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_DateTime__WEBPACK_IMPORTED_MODULE_5__.DateTimeStartPicker, {
      dateTimeStart: dateTimeStart,
      setDateTimeStart: setDateTimeStart
    })
  }))));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (DateTimeStart);

/***/ }),

/***/ "./src/components/GuestLimit.js":
/*!**************************************!*\
  !*** ./src/components/GuestLimit.js ***!
  \**************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");

/**
 * WordPress dependencies.
 */





/**
 * Internal dependencies.
 */


/**
 * AnonymousRsvp component.
 *
 * This component renders a checkbox control that allows toggling the anonymous RSVP feature for an event.
 * It handles the state and updates the post's metadata accordingly. When creating a new event, the default
 * state of the checkbox is determined by a global setting. For existing events, it uses the event's current
 * setting. The component ensures that changes are reflected in the post's metadata and also unlocks post saving.
 *
 * @return {JSX.Element} A checkbox control for enabling or disabling anonymous RSVPs.
 */
const GuestLimit = () => {
  const {
    editPost,
    unlockPostSaving
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useDispatch)('core/editor');
  const isNewEvent = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useSelect)(select => {
    return select('core/editor').isCleanNewPost();
  }, []);
  let defaultGuestLimit = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useSelect)(select => {
    return select('core/editor').getEditedPostAttribute('meta').max_guest_limit;
  }, []);
  if (isNewEvent) {
    defaultGuestLimit = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.getFromGlobal)('settings.maxGuestLimit');
  }
  if (false === defaultGuestLimit) {
    defaultGuestLimit = 0;
  }
  const [guestLimit, setGuestLimit] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(defaultGuestLimit);
  const updateGuestLimit = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useCallback)(value => {
    const meta = {
      max_guest_limit: Number(value)
    };
    setGuestLimit(value);
    editPost({
      meta
    });
    unlockPostSaving();
  }, [editPost, unlockPostSaving]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (isNewEvent && defaultGuestLimit !== 0) {
      updateGuestLimit(defaultGuestLimit);
    }
  }, [isNewEvent, defaultGuestLimit, updateGuestLimit]);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.__experimentalNumberControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Maximum Number of Guests', 'gatherpress'),
    value: guestLimit,
    min: 0,
    max: 5,
    onChange: value => {
      updateGuestLimit(value);
    }
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (GuestLimit);

/***/ }),

/***/ "./src/components/InitialDecline.js":
/*!******************************************!*\
  !*** ./src/components/InitialDecline.js ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");

/**
 * WordPress dependencies.
 */





/**
 * Internal dependencies.
 */


/**
 * InitialDecline component.
 *
 * This component renders a checkbox control that allows toggling the initial declining feature for an event.
 * It handles the state and updates the post's metadata accordingly. When creating a new event, the default
 * state of the checkbox is determined by a global setting. For existing events, it uses the event's current
 * setting. The component ensures that changes are reflected in the post's metadata and also unlocks post saving.
 *
 * @return {JSX.Element} A checkbox control for enabling or disabling initial declining.
 */
const InitialDecline = () => {
  const {
    editPost,
    unlockPostSaving
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useDispatch)('core/editor');
  const isNewEvent = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useSelect)(select => {
    return select('core/editor').isCleanNewPost();
  }, []);
  let defaultInitialDecline = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useSelect)(select => {
    return select('core/editor').getEditedPostAttribute('meta').enable_initial_decline;
  }, []);
  if (isNewEvent) {
    defaultInitialDecline = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.getFromGlobal)('settings.enableInitialDecline');
  }
  const [initialDecline, setInitialDecline] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(defaultInitialDecline);
  const updateInitialDecline = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useCallback)(value => {
    const meta = {
      enable_initial_decline: Number(value)
    };
    setInitialDecline(value);
    editPost({
      meta
    });
    unlockPostSaving();
  }, [editPost, unlockPostSaving]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (isNewEvent && defaultInitialDecline !== 0) {
      updateInitialDecline(defaultInitialDecline);
    }
  }, [isNewEvent, defaultInitialDecline, updateInitialDecline]);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CheckboxControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Allow attendees to select "not attending" immediately', 'gatherpress'),
    checked: initialDecline,
    onChange: value => {
      updateInitialDecline(value);
    }
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (InitialDecline);

/***/ }),

/***/ "./src/components/OnlineEventLink.js":
/*!*******************************************!*\
  !*** ./src/components/OnlineEventLink.js ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");

/**
 * WordPress dependencies.
 */





/**
 * Internal dependencies.
 */



/**
 * OnlineEventLink component for GatherPress.
 *
 * This component provides a TextControl input for adding or editing the online event link
 * associated with a post in the WordPress editor. It updates the post meta and broadcasts
 * the change to other components.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const OnlineEventLink = () => {
  const {
    editPost,
    unlockPostSaving
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useDispatch)('core/editor');
  const onlineEventLinkMetaData = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useSelect)(select => select('core/editor').getEditedPostAttribute('meta').online_event_link);
  const [onlineEventLink, setOnlineEventLink] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(onlineEventLinkMetaData);
  const updateEventLink = value => {
    const meta = {
      online_event_link: value
    };
    editPost({
      meta
    });
    setOnlineEventLink(value);
    (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_5__.Broadcaster)({
      setOnlineEventLink: value
    }, (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('eventDetails.postId'));
    unlockPostSaving();
  };
  (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_5__.Listener)({
    setOnlineEventLink
  }, (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('eventDetails.postId'));
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Online event link', 'gatherpress'),
    value: onlineEventLink,
    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Add link to online event', 'gatherpress'),
    onChange: value => {
      updateEventLink(value);
    }
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (OnlineEventLink);

/***/ }),

/***/ "./src/components/TimeZone.js":
/*!************************************!*\
  !*** ./src/components/TimeZone.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../helpers/datetime */ "./src/helpers/datetime.js");

/**
 * WordPress dependencies.
 */




/**
 * Internal dependencies.
 */




/**
 * TimeZone component for GatherPress.
 *
 * This component allows users to select their preferred time zone from a list of choices.
 * It includes a SelectControl with options grouped by regions. The selected time zone is
 * stored in the state and broadcasted using the Broadcaster utility.
 *
 * @since 1.0.0
 *
 * @param {Object}   props             - Component props.
 * @param {string}   props.timezone    - The current selected time zone.
 * @param {Function} props.setTimezone - Callback function to set the selected time zone.
 *
 * @return {JSX.Element} The rendered React component.
 */
const TimeZone = props => {
  const {
    timezone,
    setTimezone
  } = props;
  const choices = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.getFromGlobal)('misc.timezoneChoices');

  // Run only once.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    setTimezone((0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.getFromGlobal)('eventDetails.dateTime.timezone'));
  }, [setTimezone]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__.Broadcaster)({
      setTimezone: (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.getFromGlobal)('eventDetails.dateTime.timezone')
    });
  });
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.PanelRow, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Time Zone', 'gatherpress'),
    value: (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.maybeConvertUtcOffsetForSelect)(timezone),
    onChange: value => {
      value = (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.maybeConvertUtcOffsetForDatabase)(value);
      setTimezone(value);
      (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.setToGlobal)('eventDetails.dateTime.timezone', value);
      (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.enableSave)();
    }
  }, Object.keys(choices).map(group => {
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("optgroup", {
      key: group,
      label: group
    }, Object.keys(choices[group]).map(item => {
      return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("option", {
        key: item,
        value: item
      }, choices[group][item]);
    }));
  })));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (TimeZone);

/***/ }),

/***/ "./src/components/VenueInformation.js":
/*!********************************************!*\
  !*** ./src/components/VenueInformation.js ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");

/**
 * WordPress dependencies.
 */





/**
 * Internal dependencies.
 */


/**
 * VenueInformation component for GatherPress.
 *
 * This component allows users to input and update venue information, including full address,
 * phone number, and website. It uses the `TextControl` component from the Gutenberg editor
 * package to provide input fields for each type of information. The entered data is stored
 * in post meta as JSON and updated using the `editPost` method from the editor package.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const VenueInformation = () => {
  var _venueInformationMeta, _venueInformationMeta2, _venueInformationMeta3;
  const editPost = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useDispatch)('core/editor').editPost;
  const updateVenueMeta = (key, value) => {
    const payload = JSON.stringify({
      ...venueInformationMetaData,
      [key]: value
    });
    const meta = {
      venue_information: payload
    };
    editPost({
      meta
    });
  };
  let venueInformationMetaData = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => select('core/editor').getEditedPostAttribute('meta').venue_information);
  if (venueInformationMetaData) {
    venueInformationMetaData = JSON.parse(venueInformationMetaData);
  } else {
    venueInformationMetaData = {};
  }
  const [fullAddress, setFullAddress] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)((_venueInformationMeta = venueInformationMetaData.fullAddress) !== null && _venueInformationMeta !== void 0 ? _venueInformationMeta : '');
  const [phoneNumber, setPhoneNumber] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)((_venueInformationMeta2 = venueInformationMetaData.phoneNumber) !== null && _venueInformationMeta2 !== void 0 ? _venueInformationMeta2 : '');
  const [website, setWebsite] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)((_venueInformationMeta3 = venueInformationMetaData.website) !== null && _venueInformationMeta3 !== void 0 ? _venueInformationMeta3 : '');
  (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_5__.Listener)({
    setFullAddress,
    setPhoneNumber,
    setWebsite
  });
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Full Address', 'gatherpress'),
    value: fullAddress,
    onChange: value => {
      (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_5__.Broadcaster)({
        setFullAddress: value
      });
      updateVenueMeta('fullAddress', value);
    }
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Phone Number', 'gatherpress'),
    value: phoneNumber,
    onChange: value => {
      (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_5__.Broadcaster)({
        setPhoneNumber: value
      });
      updateVenueMeta('phoneNumber', value);
    }
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Website', 'gatherpress'),
    value: website,
    type: "url",
    onChange: value => {
      (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_5__.Broadcaster)({
        setWebsite: value
      });
      updateVenueMeta('website', value);
    }
  }));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (VenueInformation);

/***/ }),

/***/ "./src/components/VenueSelector.js":
/*!*****************************************!*\
  !*** ./src/components/VenueSelector.js ***!
  \*****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");

/**
 * WordPress dependencies.
 */





/**
 * Internal dependencies.
 */


/**
 * VenueSelector component for GatherPress.
 *
 * This component is responsible for selecting a venue for an event in the GatherPress application.
 * It includes a dropdown menu with a list of available venues, and it updates the event's venue
 * information based on the selected venue. It manages the state for venue-related data such as
 * name, fullAddress, phoneNumber, website, and isOnlineEventTerm. The selected venue is stored as a
 * term associated with the event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const VenueSelector = () => {
  // eslint-disable-next-line no-unused-vars
  const [name, setName] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)('');
  // eslint-disable-next-line no-unused-vars
  const [fullAddress, setFullAddress] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)('');
  // eslint-disable-next-line no-unused-vars
  const [phoneNumber, setPhoneNumber] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)('');
  // eslint-disable-next-line no-unused-vars
  const [website, setWebsite] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)('');
  // eslint-disable-next-line no-unused-vars
  const [isOnlineEventTerm, setIsOnlineEventTerm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)(false);
  const [venue, setVenue] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)('');
  const editPost = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useDispatch)('core/editor').editPost;
  const {
    unlockPostSaving
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useDispatch)('core/editor');
  const venueTermId = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => select('core/editor').getEditedPostAttribute('_gp_venue'));
  const venueTerm = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => select('core').getEntityRecord('taxonomy', '_gp_venue', venueTermId));
  const slug = venueTerm?.slug.replace(/^_/, '');
  const [venueSlug, setVenueSlug] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)('');
  const venueValue = venueTermId + ':' + venueSlug;
  const venuePost = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => select('core').getEntityRecords('postType', 'gp_venue', {
    per_page: 1,
    slug: venueSlug
  }));
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useEffect)(() => {
    var _venueInformation$nam, _venueInformation$ful, _venueInformation$pho, _venueInformation$web;
    let venueInformation = {};
    if (venueSlug && Array.isArray(venuePost)) {
      var _venuePost$0$meta$ven;
      const jsonString = (_venuePost$0$meta$ven = venuePost[0]?.meta?.venue_information) !== null && _venuePost$0$meta$ven !== void 0 ? _venuePost$0$meta$ven : '{}';
      if (jsonString) {
        var _venuePost$0$title$re;
        venueInformation = JSON.parse(jsonString);
        venueInformation.name = (_venuePost$0$title$re = venuePost[0]?.title.rendered) !== null && _venuePost$0$title$re !== void 0 ? _venuePost$0$title$re : '';
      }
    }
    const nameUpdated = (_venueInformation$nam = venueInformation?.name) !== null && _venueInformation$nam !== void 0 ? _venueInformation$nam : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No venue selected.', 'gatherpress');
    const fullAddressUpdated = (_venueInformation$ful = venueInformation?.fullAddress) !== null && _venueInformation$ful !== void 0 ? _venueInformation$ful : '';
    const phoneNumberUpdated = (_venueInformation$pho = venueInformation?.phoneNumber) !== null && _venueInformation$pho !== void 0 ? _venueInformation$pho : '';
    const websiteUpdated = (_venueInformation$web = venueInformation?.website) !== null && _venueInformation$web !== void 0 ? _venueInformation$web : '';

    // Will unset the venue if slug is `undefined` here.
    if (slug) {
      setVenueSlug(slug);
    }
    setVenue(venueValue ? String(venueValue) : '');
    setName(nameUpdated);
    setFullAddress(fullAddressUpdated);
    setPhoneNumber(phoneNumberUpdated);
    setWebsite(websiteUpdated);
    (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_5__.Broadcaster)({
      setName: nameUpdated,
      setFullAddress: fullAddressUpdated,
      setPhoneNumber: phoneNumberUpdated,
      setWebsite: websiteUpdated,
      setIsOnlineEventTerm: venueSlug === 'online-event'
    });
  }, [venueSlug, venuePost, slug, venueValue]);
  let venues = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => {
    return select('core').getEntityRecords('taxonomy', '_gp_venue', {
      per_page: -1,
      context: 'view'
    });
  }, []);
  if (venues) {
    venues = venues.map(item => ({
      label: item.name,
      value: item.id + ':' + item.slug.replace(/^_/, '')
    }));
    venues.unshift({
      value: ':',
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Choose a venue', 'gatherpress')
    });
  } else {
    venues = [];
  }
  const updateTerm = value => {
    setVenue(value);
    value = value.split(':');
    const term = '' !== value[0] ? [value[0]] : [];
    editPost({
      _gp_venue: term
    });
    setVenueSlug(value[1]);
    unlockPostSaving();
  };
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelRow, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Venue Selector', 'gatherpress'),
    value: venue,
    onChange: value => {
      updateTerm(value);
    },
    options: venues
  }));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (VenueSelector);

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

/***/ "./src/helpers/datetime.js":
/*!*********************************!*\
  !*** ./src/helpers/datetime.js ***!
  \*********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   convertPHPToMomentFormat: () => (/* binding */ convertPHPToMomentFormat),
/* harmony export */   dateTimeDatabaseFormat: () => (/* binding */ dateTimeDatabaseFormat),
/* harmony export */   dateTimeLabelFormat: () => (/* binding */ dateTimeLabelFormat),
/* harmony export */   dateTimeMomentFormat: () => (/* binding */ dateTimeMomentFormat),
/* harmony export */   dateTimePreview: () => (/* binding */ dateTimePreview),
/* harmony export */   defaultDateTimeEnd: () => (/* binding */ defaultDateTimeEnd),
/* harmony export */   defaultDateTimeStart: () => (/* binding */ defaultDateTimeStart),
/* harmony export */   getDateTimeEnd: () => (/* binding */ getDateTimeEnd),
/* harmony export */   getDateTimeStart: () => (/* binding */ getDateTimeStart),
/* harmony export */   getTimeZone: () => (/* binding */ getTimeZone),
/* harmony export */   getUtcOffset: () => (/* binding */ getUtcOffset),
/* harmony export */   maybeConvertUtcOffsetForDatabase: () => (/* binding */ maybeConvertUtcOffsetForDatabase),
/* harmony export */   maybeConvertUtcOffsetForDisplay: () => (/* binding */ maybeConvertUtcOffsetForDisplay),
/* harmony export */   maybeConvertUtcOffsetForSelect: () => (/* binding */ maybeConvertUtcOffsetForSelect),
/* harmony export */   saveDateTime: () => (/* binding */ saveDateTime),
/* harmony export */   updateDateTimeEnd: () => (/* binding */ updateDateTimeEnd),
/* harmony export */   updateDateTimeStart: () => (/* binding */ updateDateTimeStart),
/* harmony export */   validateDateTimeEnd: () => (/* binding */ validateDateTimeEnd),
/* harmony export */   validateDateTimeStart: () => (/* binding */ validateDateTimeStart)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _globals__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./globals */ "./src/helpers/globals.js");
/* harmony import */ var _event__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./event */ "./src/helpers/event.js");
/* harmony import */ var _components_DateTimePreview__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../components/DateTimePreview */ "./src/components/DateTimePreview.js");

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
 * Date and time format string for use with Moment.js.
 *
 * This format is designed to represent date and time in the format
 * "YYYY-MM-DDTHH:mm:ss" for compatibility with Moment.js library.
 *
 * @since 1.0.0
 *
 * @type {string}
 */
const dateTimeMomentFormat = 'YYYY-MM-DDTHH:mm:ss';

/**
 * Database-compatible date and time format string for storage.
 *
 * This format is designed to represent date and time in the format
 * "YYYY-MM-DD HH:mm:ss" for compatibility with database storage.
 *
 * @since 1.0.0
 *
 * @type {string}
 */
const dateTimeDatabaseFormat = 'YYYY-MM-DD HH:mm:ss';

/**
 * The default start date and time for an event.
 * It is set to the current date and time plus one day at 18:00:00 in the application's timezone.
 *
 * @since 1.0.0
 *
 * @type {string} Formatted default start date and time in the application's timezone.
 */
const defaultDateTimeStart = moment__WEBPACK_IMPORTED_MODULE_1___default().tz(getTimeZone()).add(1, 'day').set('hour', 18).set('minute', 0).set('second', 0).format(dateTimeMomentFormat);

/**
 * The default end date and time for an event.
 * It is calculated based on the default start date and time plus two hours in the application's timezone.
 *
 * @since 1.0.0
 *
 * @type {string} Formatted default end date and time in the application's timezone.
 */
const defaultDateTimeEnd = moment__WEBPACK_IMPORTED_MODULE_1___default().tz(defaultDateTimeStart, getTimeZone()).add(2, 'hours').format(dateTimeMomentFormat);

/**
 * Get the combined date and time format for event labels.
 *
 * This function retrieves the date and time formats from global settings
 * and combines them to create a formatted label for event start and end times.
 *
 * @since 1.0.0
 *
 * @return {string} The combined date and time format for event labels.
 */
function dateTimeLabelFormat() {
  const dateFormat = convertPHPToMomentFormat((0,_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('settings.dateFormat'));
  const timeFormat = convertPHPToMomentFormat((0,_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('settings.timeFormat'));
  return dateFormat + ' ' + timeFormat;
}

/**
 * Retrieves the timezone for the application based on the provided timezone or the global setting.
 * If the provided timezone is invalid, the default timezone is set to 'GMT'.
 *
 * @since 1.0.0
 *
 * @param {string} timezone - The timezone to be used, defaults to the global setting 'event_datetime.timezone'.
 *
 * @return {string} The retrieved timezone, or 'GMT' if the provided timezone is invalid.
 */
function getTimeZone(timezone = (0,_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('eventDetails.dateTime.timezone')) {
  if (!!moment__WEBPACK_IMPORTED_MODULE_1___default().tz.zone(timezone)) {
    return timezone;
  }
  return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('GMT', 'gatherpress');
}

/**
 * Retrieves the UTC offset for a given timezone.
 * If the timezone is not set to 'GMT', an empty string is returned.
 *
 * @since 1.0.0
 *
 * @param {string} timezone - The timezone for which to retrieve the UTC offset.
 *
 * @return {string} UTC offset without the colon if the timezone is set to 'GMT', otherwise an empty string.
 */
function getUtcOffset(timezone) {
  timezone = getTimeZone(timezone);
  if ((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('GMT', 'gatherpress') !== timezone) {
    return '';
  }
  const offset = (0,_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('eventDetails.dateTime.timezone');
  return maybeConvertUtcOffsetForDisplay(offset);
}

/**
 * Converts a UTC offset string to a format suitable for display,
 * removing the colon (:) between hours and minutes.
 *
 * @since 1.0.0
 *
 * @param {string} offset - The UTC offset string to be converted.
 *
 * @return {string} Converted UTC offset without the colon, suitable for display.
 */
function maybeConvertUtcOffsetForDisplay(offset = '') {
  return offset.replace(':', '');
}

/**
 * Converts a UTC offset string to a standardized format suitable for database storage.
 * The function accepts offsets in the form of 'UTC+HH:mm', 'UTC-HH:mm', 'UTC+HH', or 'UTC-HH'.
 * The resulting format is '+HH:mm' or '-HH:mm'.
 *
 * @since 1.0.0
 *
 * @param {string} offset - The UTC offset string to be converted.
 *
 * @return {string} Converted UTC offset in the format '+HH:mm' or '-HH:mm'.
 */
function maybeConvertUtcOffsetForDatabase(offset = '') {
  // Regex: https://regex101.com/r/9bMgJd/2.
  const pattern = /^UTC([+-])(\d+)(.\d+)?$/;
  const sign = offset.replace(pattern, '$1');
  if (sign !== offset) {
    const hour = offset.replace(pattern, '$2').padStart(2, '0');
    let minute = offset.replace(pattern, '$3');
    if ('' === minute) {
      minute = ':00';
    }
    minute = minute.replace('.25', ':15').replace('.5', ':30').replace('.75', ':45');
    return sign + hour + minute;
  }
  return offset;
}

/**
 * Converts a UTC offset string to a format suitable for dropdown selection,
 * specifically in the format '+HH:mm' or '-HH:mm'.
 *
 * @since 1.0.0
 *
 * @param {string} offset - The UTC offset string to be converted.
 *
 * @return {string} Converted UTC offset in the format '+HH:mm' or '-HH:mm'.
 */
function maybeConvertUtcOffsetForSelect(offset = '') {
  // Regex: https://regex101.com/r/nOXCPo/2.
  const pattern = /^([+-])(\d{2}):(00|15|30|45)$/;
  const sign = offset.replace(pattern, '$1');
  if (sign !== offset) {
    const hour = parseInt(offset.replace(pattern, '$2')).toString();
    const minute = offset.replace(pattern, '$3').replace('00', '').replace('15', '.25').replace('30', '.5').replace('45', '.75');
    return 'UTC' + sign + hour + minute;
  }
  return offset;
}

/**
 * Retrieves the start date and time for an event, formatted based on the plugin's timezone.
 * If the start date and time is not set, it defaults to a predefined value.
 * The formatted datetime is then stored in the global settings for future access.
 *
 * @since 1.0.0
 *
 * @return {string} The formatted start date and time for the event.
 */
function getDateTimeStart() {
  let dateTime = (0,_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('eventDetails.dateTime.datetime_start');
  dateTime = '' !== dateTime ? moment__WEBPACK_IMPORTED_MODULE_1___default().tz(dateTime, getTimeZone()).format(dateTimeMomentFormat) : defaultDateTimeStart;
  (0,_globals__WEBPACK_IMPORTED_MODULE_6__.setToGlobal)('eventDetails.dateTime.datetime_start', dateTime);
  return dateTime;
}

/**
 * Retrieves the end date and time for an event, formatted based on the plugin's timezone.
 * If the end date and time is not set, it defaults to a predefined value.
 * The formatted datetime is then stored in the global settings for future access.
 *
 * @since 1.0.0
 *
 * @return {string} The formatted end date and time for the event.
 */
function getDateTimeEnd() {
  let dateTime = (0,_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('eventDetails.dateTime.datetime_end');
  dateTime = '' !== dateTime ? moment__WEBPACK_IMPORTED_MODULE_1___default().tz(dateTime, getTimeZone()).format(dateTimeMomentFormat) : defaultDateTimeEnd;
  (0,_globals__WEBPACK_IMPORTED_MODULE_6__.setToGlobal)('eventDetails.dateTime.datetime_end', dateTime);
  return dateTime;
}

/**
 * Updates the start date and time for an event, performs validation, and triggers the save functionality.
 *
 * @since 1.0.0
 *
 * @param {string}   date             - The new start date and time to be set.
 * @param {Function} setDateTimeStart - Optional callback function to update the state or perform additional actions.
 *
 * @return {void}
 */
function updateDateTimeStart(date, setDateTimeStart = null) {
  validateDateTimeStart(date);
  (0,_globals__WEBPACK_IMPORTED_MODULE_6__.setToGlobal)('eventDetails.dateTime.datetime_start', date);
  if ('function' === typeof setDateTimeStart) {
    setDateTimeStart(date);
  }
  (0,_globals__WEBPACK_IMPORTED_MODULE_6__.enableSave)();
}

/**
 * Update the end date and time of the event and trigger necessary actions.
 *
 * This function sets the end date and time of the event to the specified value,
 * validates the input, and triggers additional actions such as updating the UI.
 *
 * @since 1.0.0
 *
 * @param {string}        date           - The new end date and time in a valid format.
 * @param {Function|null} setDateTimeEnd - Optional callback to update the UI with the new end date and time.
 *
 * @return {void}
 */
function updateDateTimeEnd(date, setDateTimeEnd = null) {
  validateDateTimeEnd(date);
  (0,_globals__WEBPACK_IMPORTED_MODULE_6__.setToGlobal)('eventDetails.dateTime.datetime_end', date);
  if (null !== setDateTimeEnd) {
    setDateTimeEnd(date);
  }
  (0,_globals__WEBPACK_IMPORTED_MODULE_6__.enableSave)();
}

/**
 * Validate the start date and time of the event and perform necessary adjustments if needed.
 *
 * This function compares the provided start date and time with the current end date
 * and time of the event. If the start date is greater than or equal to the end date,
 * it adjusts the end date to ensure a minimum two-hour duration.
 *
 * @since 1.0.0
 *
 * @param {string} dateTimeStart - The start date and time in a valid format.
 *
 * @return {void}
 */
function validateDateTimeStart(dateTimeStart) {
  const dateTimeEndNumeric = moment__WEBPACK_IMPORTED_MODULE_1___default().tz((0,_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('eventDetails.dateTime.datetime_end'), getTimeZone()).valueOf();
  const dateTimeStartNumeric = moment__WEBPACK_IMPORTED_MODULE_1___default().tz(dateTimeStart, getTimeZone()).valueOf();
  if (dateTimeStartNumeric >= dateTimeEndNumeric) {
    const dateTimeEnd = moment__WEBPACK_IMPORTED_MODULE_1___default().tz(dateTimeStartNumeric, getTimeZone()).add(2, 'hours').format(dateTimeMomentFormat);
    updateDateTimeEnd(dateTimeEnd);
  }
}

/**
 * Validate the end date and time of the event and perform necessary adjustments if needed.
 *
 * This function compares the provided end date and time with the current start date
 * and time of the event. If the end date is less than or equal to the start date,
 * it adjusts the start date to ensure a minimum two-hour duration.
 *
 * @since 1.0.0
 *
 * @param {string} dateTimeEnd - The end date and time in a valid format.
 *
 * @return {void}
 */
function validateDateTimeEnd(dateTimeEnd) {
  const dateTimeStartNumeric = moment__WEBPACK_IMPORTED_MODULE_1___default().tz((0,_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('eventDetails.dateTime.datetime_start'), getTimeZone()).valueOf();
  const dateTimeEndNumeric = moment__WEBPACK_IMPORTED_MODULE_1___default().tz(dateTimeEnd, getTimeZone()).valueOf();
  if (dateTimeEndNumeric <= dateTimeStartNumeric) {
    const dateTimeStart = moment__WEBPACK_IMPORTED_MODULE_1___default().tz(dateTimeEndNumeric, getTimeZone()).subtract(2, 'hours').format(dateTimeMomentFormat);
    updateDateTimeStart(dateTimeStart);
  }
}

/**
 * Save the event date, time, and timezone to the server.
 *
 * This function sends a POST request to the server with the updated event date,
 * time, and timezone information for storage. It is triggered during the process
 * of saving an event post in the WordPress editor.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
function saveDateTime() {
  const isSavingPost = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.select)('core/editor').isSavingPost(),
    isAutosavingPost = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.select)('core/editor').isAutosavingPost();
  if ((0,_event__WEBPACK_IMPORTED_MODULE_7__.isEventPostType)() && isSavingPost && !isAutosavingPost) {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
      path: (0,_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('urls.eventRestApi') + '/datetime',
      method: 'POST',
      data: {
        post_id: (0,_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('eventDetails.postId'),
        datetime_start: moment__WEBPACK_IMPORTED_MODULE_1___default().tz((0,_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('eventDetails.dateTime.datetime_start'), getTimeZone()).format(dateTimeDatabaseFormat),
        datetime_end: moment__WEBPACK_IMPORTED_MODULE_1___default().tz((0,_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('eventDetails.dateTime.datetime_end'), getTimeZone()).format(dateTimeDatabaseFormat),
        timezone: (0,_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('eventDetails.dateTime.timezone'),
        _wpnonce: (0,_globals__WEBPACK_IMPORTED_MODULE_6__.getFromGlobal)('misc.nonce')
      }
    }).then(() => {
      (0,_event__WEBPACK_IMPORTED_MODULE_7__.triggerEventCommuncation)();
    });
  }
}

/**
 * Convert PHP date format to Moment.js date format.
 *
 * This function converts a PHP date format string to its equivalent Moment.js date format.
 * It uses a mapping of PHP format characters to Moment.js format characters.
 *
 * @see https://gist.github.com/neilrackett/7881b5bef4cb4ae63af5c3a6a244cffa
 *
 * @since 1.0.0
 *
 * @param {string} format - The PHP date format to be converted.
 * @return {string} The equivalent Moment.js date format.
 */
function convertPHPToMomentFormat(format) {
  const replacements = {
    d: 'DD',
    D: 'ddd',
    j: 'D',
    l: 'dddd',
    N: 'E',
    S: 'o',
    w: 'e',
    z: 'DDD',
    W: 'W',
    F: 'MMMM',
    m: 'MM',
    M: 'MMM',
    n: 'M',
    t: '',
    // no equivalent
    L: '',
    // no equivalent
    o: 'YYYY',
    Y: 'YYYY',
    y: 'YY',
    a: 'a',
    A: 'A',
    B: '',
    // no equivalent
    g: 'h',
    G: 'H',
    h: 'hh',
    H: 'HH',
    i: 'mm',
    s: 'ss',
    u: 'SSS',
    e: 'zz',
    // deprecated since Moment.js 1.6.0
    I: '',
    // no equivalent
    O: '',
    // no equivalent
    P: '',
    // no equivalent
    T: '',
    // no equivalent
    Z: '',
    // no equivalent
    c: '',
    // no equivalent
    r: '',
    // no equivalent
    U: 'X'
  };
  return String(format).split('').map(chr => chr in replacements ? replacements[chr] : chr).join('');
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
function dateTimePreview() {
  // Select all elements with the attribute 'data-gp_component_name' set to 'datetime-preview'.
  const dateTimePreviewContainers = document.querySelectorAll(`[data-gp_component_name="datetime-preview"]`);

  // Iterate through each matched element and initialize DateTimePreview component.
  for (let i = 0; i < dateTimePreviewContainers.length; i++) {
    // Parse attributes from the 'data-gp_component_attrs' attribute.
    const attrs = JSON.parse(dateTimePreviewContainers[i].dataset.gp_component_attrs);

    // Create a root element and render the DateTimePreview component with the parsed attributes.
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_5__.createRoot)(dateTimePreviewContainers[i]).render((0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_DateTimePreview__WEBPACK_IMPORTED_MODULE_8__["default"], {
      attrs: attrs
    }));
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
/* harmony export */   hasEventPast: () => (/* binding */ hasEventPast),
/* harmony export */   hasEventPastNotice: () => (/* binding */ hasEventPastNotice),
/* harmony export */   isEventPostType: () => (/* binding */ isEventPostType),
/* harmony export */   triggerEventCommuncation: () => (/* binding */ triggerEventCommuncation)
/* harmony export */ });
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _datetime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./datetime */ "./src/helpers/datetime.js");
/* harmony import */ var _globals__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./globals */ "./src/helpers/globals.js");
/* harmony import */ var _broadcasting__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./broadcasting */ "./src/helpers/broadcasting.js");
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
 * Checks if the current post type is an event in the GatherPress application.
 *
 * This function queries the current post type using the `select` function from the `core/editor` package.
 * It returns `true` if the current post type is 'gp_event', indicating that the post is an event,
 * and `false` otherwise.
 *
 * @since 1.0.0
 *
 * @return {boolean} True if the current post type is 'gp_event', false otherwise.
 */
function isEventPostType() {
  return 'gp_event' === (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor').getCurrentPostType();
}

/**
 * Check if the event has already passed.
 *
 * This function compares the current time with the end time of the event
 * to determine if the event has already taken place.
 *
 * @return {boolean} True if the event has passed; false otherwise.
 */
function hasEventPast() {
  const dateTimeEnd = moment__WEBPACK_IMPORTED_MODULE_0___default().tz((0,_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('eventDetails.dateTime.datetime_end'), (0,_datetime__WEBPACK_IMPORTED_MODULE_3__.getTimeZone)());
  return 'gp_event' === (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor')?.getCurrentPostType() && moment__WEBPACK_IMPORTED_MODULE_0___default().tz((0,_datetime__WEBPACK_IMPORTED_MODULE_3__.getTimeZone)()).valueOf() > dateTimeEnd.valueOf();
}

/**
 * Display a notice if the event has already passed.
 *
 * This function checks if the event has passed and displays a warning notice
 * if so. The notice is non-dismissible to ensure the user is informed about
 * the event status.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
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

/**
 * Trigger communication notice for event updates.
 *
 * This function checks if the event is published and not yet passed,
 * then displays a success notice prompting the user to send an event update
 * to members via email. The notice includes an action to compose the message.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
function triggerEventCommuncation() {
  const id = 'gp_event_communcation';
  const notices = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.dispatch)('core/notices');
  notices.removeNotice(id);
  if ('publish' === (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor').getEditedPostAttribute('status') && !hasEventPast()) {
    notices.createNotice('success', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Send an event update to members via email?', 'gatherpress'), {
      id,
      isDismissible: true,
      actions: [{
        onClick: () => {
          (0,_broadcasting__WEBPACK_IMPORTED_MODULE_5__.Broadcaster)({
            setOpen: true
          });
        },
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Compose Message', 'gatherpress')
      }]
    });
  }
}

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

/***/ "./src/helpers/venue.js":
/*!******************************!*\
  !*** ./src/helpers/venue.js ***!
  \******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isVenuePostType: () => (/* binding */ isVenuePostType)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/**
 * WordPress dependencies.
 */


/**
 * Check if the current post type is a venue.
 *
 * This function determines whether the current post type in the WordPress editor
 * is associated with venue content.
 *
 * @since 1.0.0
 *
 * @return {boolean} True if the current post type is a venue; false otherwise.
 */
function isVenuePostType() {
  return 'gp_venue' === (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.select)('core/editor')?.getCurrentPostType();
}

/***/ }),

/***/ "./src/panels/event-settings/anonymous-rsvp/index.js":
/*!***********************************************************!*\
  !*** ./src/panels/event-settings/anonymous-rsvp/index.js ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_AnonymousRsvp__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../components/AnonymousRsvp */ "./src/components/AnonymousRsvp.js");

/**
 * Internal dependencies.
 */


/**
 * A panel component for managing the online event link.
 *
 * This component renders a section containing the `OnlineEventLink` component,
 * allowing users to set and manage the link for an online event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the OnlineEventLinkPanel.
 */
const AnonymousRsvpPanel = () => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_AnonymousRsvp__WEBPACK_IMPORTED_MODULE_1__["default"], null));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (AnonymousRsvpPanel);

/***/ }),

/***/ "./src/panels/event-settings/datetime-range/index.js":
/*!***********************************************************!*\
  !*** ./src/panels/event-settings/datetime-range/index.js ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_DateTimeRange__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../components/DateTimeRange */ "./src/components/DateTimeRange.js");

/**
 * Internal dependencies.
 */


/**
 * A panel component for managing date and time ranges.
 *
 * This component serves as a panel containing the `DateTimeRange` component
 * for managing date and time ranges in a specific context.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the DateTimeRangePanel.
 */
const DateTimeRangePanel = () => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_DateTimeRange__WEBPACK_IMPORTED_MODULE_1__["default"], null));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (DateTimeRangePanel);

/***/ }),

/***/ "./src/panels/event-settings/guest-limit/index.js":
/*!********************************************************!*\
  !*** ./src/panels/event-settings/guest-limit/index.js ***!
  \********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_GuestLimit__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../components/GuestLimit */ "./src/components/GuestLimit.js");

/**
 * Internal dependencies.
 */


/**
 * A panel component for managing the online event link.
 *
 * This component renders a section containing the `OnlineEventLink` component,
 * allowing users to set and manage the link for an online event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the OnlineEventLinkPanel.
 */
const GuestLimitPanel = () => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_GuestLimit__WEBPACK_IMPORTED_MODULE_1__["default"], null));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (GuestLimitPanel);

/***/ }),

/***/ "./src/panels/event-settings/index.js":
/*!********************************************!*\
  !*** ./src/panels/event-settings/index.js ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_plugins__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/plugins */ "@wordpress/plugins");
/* harmony import */ var _wordpress_plugins__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_plugins__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_edit_post__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/edit-post */ "@wordpress/edit-post");
/* harmony import */ var _wordpress_edit_post__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_edit_post__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _helpers_event__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../helpers/event */ "./src/helpers/event.js");
/* harmony import */ var _anonymous_rsvp__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./anonymous-rsvp */ "./src/panels/event-settings/anonymous-rsvp/index.js");
/* harmony import */ var _initial_decline__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./initial-decline */ "./src/panels/event-settings/initial-decline/index.js");
/* harmony import */ var _datetime_range__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./datetime-range */ "./src/panels/event-settings/datetime-range/index.js");
/* harmony import */ var _guest_limit__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./guest-limit */ "./src/panels/event-settings/guest-limit/index.js");
/* harmony import */ var _notify_members__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ./notify-members */ "./src/panels/event-settings/notify-members/index.js");
/* harmony import */ var _online_link__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ./online-link */ "./src/panels/event-settings/online-link/index.js");
/* harmony import */ var _venue_selector__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! ./venue-selector */ "./src/panels/event-settings/venue-selector/index.js");

/**
 * WordPress dependencies.
 */






/**
 * Internal dependencies.
 */









/**
 * A settings panel for event-specific settings in the block editor.
 *
 * This component renders a `PluginDocumentSettingPanel` containing various
 * subpanels for configuring event-related settings, such as date and time,
 * venue selection, online event link, and notifying members.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element | null} The JSX element for the EventSettings panel if
 * the current post type is an event; otherwise, returns null.
 */
const EventSettings = () => {
  return (0,_helpers_event__WEBPACK_IMPORTED_MODULE_6__.isEventPostType)() && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_edit_post__WEBPACK_IMPORTED_MODULE_5__.PluginDocumentSettingPanel, {
    name: "gp-event-settings",
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Event settings', 'gatherpress'),
    initialOpen: true,
    className: "gp-event-settings"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.__experimentalVStack, {
    spacing: 6
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_datetime_range__WEBPACK_IMPORTED_MODULE_9__["default"], null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_venue_selector__WEBPACK_IMPORTED_MODULE_13__["default"], null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_online_link__WEBPACK_IMPORTED_MODULE_12__["default"], null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_guest_limit__WEBPACK_IMPORTED_MODULE_10__["default"], null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_anonymous_rsvp__WEBPACK_IMPORTED_MODULE_7__["default"], null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_initial_decline__WEBPACK_IMPORTED_MODULE_8__["default"], null), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_notify_members__WEBPACK_IMPORTED_MODULE_11__["default"], null)));
};

/**
 * Registers the 'gp-event-settings' plugin.
 *
 * This function registers a custom plugin named 'gp-event-settings' and
 * associates it with the `EventSettings` component for rendering.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
(0,_wordpress_plugins__WEBPACK_IMPORTED_MODULE_4__.registerPlugin)('gp-event-settings', {
  render: EventSettings
});

/**
 * Toggles the visibility of the 'gp-event-settings' panel in the Block Editor.
 *
 * This function uses the `dispatch` function from the `@wordpress/data` package
 * to toggle the visibility of the 'gp-event-settings' panel in the Block Editor.
 * The panel is identified by the string 'gp-event-settings/gp-event-settings'.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
(0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.dispatch)('core/edit-post').toggleEditorPanelOpened('gp-event-settings/gp-event-settings');

/***/ }),

/***/ "./src/panels/event-settings/initial-decline/index.js":
/*!************************************************************!*\
  !*** ./src/panels/event-settings/initial-decline/index.js ***!
  \************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_InitialDecline__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../components/InitialDecline */ "./src/components/InitialDecline.js");

/**
 * Internal dependencies.
 */


/**
 * A panel component for managing the initial decline option.
 *
 * This component renders a section containing the `InitialDecline` component,
 * allowing users to set and manage the initial decline option for an event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the InitialDeclinePanel.
 */
const InitialDeclinePanel = () => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_InitialDecline__WEBPACK_IMPORTED_MODULE_1__["default"], null));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (InitialDeclinePanel);

/***/ }),

/***/ "./src/panels/event-settings/notify-members/index.js":
/*!***********************************************************!*\
  !*** ./src/panels/event-settings/notify-members/index.js ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_event__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../../helpers/event */ "./src/helpers/event.js");

/**
 * WordPress dependencies.
 */




/**
 * Internal dependencies.
 */



/**
 * A panel component for notifying members about an event update.
 *
 * This component checks if the current post is published and the event has not yet occurred.
 * If the conditions are met, it displays a section with a button to compose a message for members.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element | null} The JSX element for the NotifyMembersPanel or null if conditions are not met.
 */
const NotifyMembersPanel = () => {
  return 'publish' === (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.select)('core/editor').getEditedPostAttribute('status') && !(0,_helpers_event__WEBPACK_IMPORTED_MODULE_5__.hasEventPast)() && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h3", {
    style: {
      marginBottom: '0.5rem'
    }
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Send an event update', 'gatherpress')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    variant: "secondary",
    onClick: () => (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__.Broadcaster)({
      setOpen: true
    })
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Compose Message', 'gatherpress')));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (NotifyMembersPanel);

/***/ }),

/***/ "./src/panels/event-settings/online-link/index.js":
/*!********************************************************!*\
  !*** ./src/panels/event-settings/online-link/index.js ***!
  \********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_OnlineEventLink__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../components/OnlineEventLink */ "./src/components/OnlineEventLink.js");

/**
 * Internal dependencies.
 */


/**
 * A panel component for managing the online event link.
 *
 * This component renders a section containing the `OnlineEventLink` component,
 * allowing users to set and manage the link for an online event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the OnlineEventLinkPanel.
 */
const OnlineEventLinkPanel = () => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_OnlineEventLink__WEBPACK_IMPORTED_MODULE_1__["default"], null));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (OnlineEventLinkPanel);

/***/ }),

/***/ "./src/panels/event-settings/venue-selector/index.js":
/*!***********************************************************!*\
  !*** ./src/panels/event-settings/venue-selector/index.js ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_VenueSelector__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../components/VenueSelector */ "./src/components/VenueSelector.js");

/**
 * Internal dependencies.
 */


/**
 * A panel component for selecting and managing the venue for an event.
 *
 * This component renders a section containing the `VenueSelector` component,
 * allowing users to choose a venue for the event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the VenueSelectorPanel.
 */
const VenueSelectorPanel = () => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_VenueSelector__WEBPACK_IMPORTED_MODULE_1__["default"], null));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (VenueSelectorPanel);

/***/ }),

/***/ "./src/panels/venue-settings/index.js":
/*!********************************************!*\
  !*** ./src/panels/venue-settings/index.js ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_plugins__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/plugins */ "@wordpress/plugins");
/* harmony import */ var _wordpress_plugins__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_plugins__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_edit_post__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/edit-post */ "@wordpress/edit-post");
/* harmony import */ var _wordpress_edit_post__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_edit_post__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _helpers_venue__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../helpers/venue */ "./src/helpers/venue.js");
/* harmony import */ var _venue_information__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./venue-information */ "./src/panels/venue-settings/venue-information/index.js");

/**
 * WordPress dependencies.
 */






/**
 * Internal dependencies.
 */



/**
 * VenueSettings Component
 *
 * This component represents a panel in the Block Editor for venue settings.
 * It includes the VenueInformationPanel component to manage and display venue details.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the VenueSettings.
 */
const VenueSettings = () => {
  return (0,_helpers_venue__WEBPACK_IMPORTED_MODULE_6__.isVenuePostType)() && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_edit_post__WEBPACK_IMPORTED_MODULE_5__.PluginDocumentSettingPanel, {
    name: "gp-venue-settings",
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Venue settings', 'gatherpress'),
    initialOpen: true,
    className: "gp-venue-settings"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.__experimentalVStack, {
    spacing: 6
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_venue_information__WEBPACK_IMPORTED_MODULE_7__["default"], null)));
};

/**
 * Register Venue Settings Plugin
 *
 * This function registers the VenueSettings component as a plugin to be rendered in the Block Editor.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
(0,_wordpress_plugins__WEBPACK_IMPORTED_MODULE_4__.registerPlugin)('gp-venue-settings', {
  render: VenueSettings
});

/**
 * Toggle Venue Settings Panel
 *
 * This function dispatches an action to toggle the visibility of the Venue Settings panel in the Block Editor.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
(0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.dispatch)('core/edit-post').toggleEditorPanelOpened('gp-venue-settings/gp-venue-settings');

/***/ }),

/***/ "./src/panels/venue-settings/venue-information/index.js":
/*!**************************************************************!*\
  !*** ./src/panels/venue-settings/venue-information/index.js ***!
  \**************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_VenueInformation__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../components/VenueInformation */ "./src/components/VenueInformation.js");

/**
 * Internal dependencies.
 */


/**
 * VenueInformationPanel Component
 *
 * This component represents a panel in the Block Editor containing venue information.
 * It includes the VenueInformation component to manage and display venue details.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the VenueInformationPanel.
 */
const VenueInformationPanel = () => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_VenueInformation__WEBPACK_IMPORTED_MODULE_1__["default"], null));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (VenueInformationPanel);

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

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

/***/ "@wordpress/date":
/*!******************************!*\
  !*** external ["wp","date"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["date"];

/***/ }),

/***/ "@wordpress/edit-post":
/*!**********************************!*\
  !*** external ["wp","editPost"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["editPost"];

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

/***/ "@wordpress/plugins":
/*!*********************************!*\
  !*** external ["wp","plugins"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["plugins"];

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
/*!*****************************!*\
  !*** ./src/panels/index.js ***!
  \*****************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _event_settings__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./event-settings */ "./src/panels/event-settings/index.js");
/* harmony import */ var _venue_settings__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./venue-settings */ "./src/panels/venue-settings/index.js");
/**
 * Internal dependencies.
 */


})();

/******/ })()
;
//# sourceMappingURL=panels.js.map