/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/components/DateTime.js":
/*!************************************!*\
  !*** ./src/components/DateTime.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "DateTimeEndLabel": () => (/* binding */ DateTimeEndLabel),
/* harmony export */   "DateTimeEndPicker": () => (/* binding */ DateTimeEndPicker),
/* harmony export */   "DateTimeStartLabel": () => (/* binding */ DateTimeStartLabel),
/* harmony export */   "DateTimeStartPicker": () => (/* binding */ DateTimeStartPicker)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
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

const DateTimeStartLabel = props => {
  const {
    dateTimeStart
  } = props;
  return moment__WEBPACK_IMPORTED_MODULE_3___default().tz(dateTimeStart, _helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.timeZone).format(_helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.dateTimeLabelFormat);
};
const DateTimeEndLabel = props => {
  const {
    dateTimeEnd
  } = props;
  return moment__WEBPACK_IMPORTED_MODULE_3___default().tz(dateTimeEnd, _helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.timeZone).format(_helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.dateTimeLabelFormat);
};
const DateTimeStartPicker = props => {
  const {
    dateTimeStart,
    setDateTimeStart
  } = props;
  const settings = (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_1__.getSettings)();
  const is12HourTime = /a(?!\\)/i.test(settings.formats.time.toLowerCase().replace(/\\\\/g, '').split('').reverse().join(''));
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DateTimePicker, {
    currentDate: dateTimeStart,
    onChange: date => (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.updateDateTimeStart)(date, setDateTimeStart),
    is12Hour: is12HourTime
  });
};
const DateTimeEndPicker = props => {
  const {
    dateTimeEnd,
    setDateTimeEnd
  } = props;
  const settings = (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_1__.getSettings)();
  const is12HourTime = /a(?!\\)/i.test(settings.formats.time.toLowerCase().replace(/\\\\/g, '').split('').reverse().join(''));
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.DateTimePicker, {
    currentDate: dateTimeEnd,
    onChange: date => (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.updateDateTimeEnd)(date, setDateTimeEnd),
    is12Hour: is12HourTime
  });
};

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

/***/ "./src/panels/event-settings/datetime/index.js":
/*!*****************************************************!*\
  !*** ./src/panels/event-settings/datetime/index.js ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _components_DateTime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../../components/DateTime */ "./src/components/DateTime.js");
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../../helpers/datetime */ "./src/helpers/datetime.js");
/* harmony import */ var _helpers_event__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../../helpers/event */ "./src/helpers/event.js");
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../../../helpers/broadcasting */ "./src/helpers/broadcasting.js");

/**
 * External dependencies.
 */


/**
 * WordPress dependencies.
 */





/**
 * Internal dependencies.
 */




(0,_helpers_event__WEBPACK_IMPORTED_MODULE_7__.hasEventPastNotice)();
(0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.subscribe)(_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.saveDateTime);
const DateTimePanel = () => {
  const [dateTimeStart, setDateTimeStart] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)();
  const [dateTimeEnd, setDateTimeEnd] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)();
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    setDateTimeStart(moment__WEBPACK_IMPORTED_MODULE_1___default().tz((0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.getDateTimeStart)(), _helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.timeZone).format(_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.dateTimeMomentFormat));
    setDateTimeEnd(moment__WEBPACK_IMPORTED_MODULE_1___default().tz((0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.getDateTimeEnd)(), _helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.timeZone).format(_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.dateTimeMomentFormat));
    (0,_helpers_event__WEBPACK_IMPORTED_MODULE_7__.hasEventPastNotice)();
    (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_8__.Broadcaster)({
      setDateTimeStart: dateTimeStart,
      setDateTimeEnd: dateTimeEnd
    });
  });
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("h3", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Date & time', 'gatherpress')), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelRow, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Flex, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.FlexItem, null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Start', 'gatherpress')), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.FlexItem, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Dropdown, {
    position: "bottom left",
    renderToggle: _ref => {
      let {
        isOpen,
        onToggle
      } = _ref;
      return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
        onClick: onToggle,
        "aria-expanded": isOpen,
        isLink: true
      }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_DateTime__WEBPACK_IMPORTED_MODULE_5__.DateTimeStartLabel, {
        dateTimeStart: dateTimeStart
      }));
    },
    renderContent: () => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_DateTime__WEBPACK_IMPORTED_MODULE_5__.DateTimeStartPicker, {
      dateTimeStart: dateTimeStart,
      setDateTimeStart: setDateTimeStart
    })
  })))), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.PanelRow, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Flex, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.FlexItem, null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('End', 'gatherpress')), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.FlexItem, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Dropdown, {
    position: "bottom left",
    renderToggle: _ref2 => {
      let {
        isOpen,
        onToggle
      } = _ref2;
      return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
        onClick: onToggle,
        "aria-expanded": isOpen,
        isLink: true
      }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_DateTime__WEBPACK_IMPORTED_MODULE_5__.DateTimeEndLabel, {
        dateTimeEnd: dateTimeEnd
      }));
    },
    renderContent: () => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_DateTime__WEBPACK_IMPORTED_MODULE_5__.DateTimeEndPicker, {
      dateTimeEnd: dateTimeEnd,
      setDateTimeEnd: setDateTimeEnd
    })
  })))));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (DateTimePanel);

/***/ }),

/***/ "./src/panels/event-settings/index.js":
/*!********************************************!*\
  !*** ./src/panels/event-settings/index.js ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
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
/* harmony import */ var _datetime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./datetime */ "./src/panels/event-settings/datetime/index.js");
/* harmony import */ var _venue__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./venue */ "./src/panels/event-settings/venue/index.js");

/**
 * WordPress dependencies.
 */






/**
 * Internal dependencies.
 */



const EventSettings = () => {
  return (0,_helpers_event__WEBPACK_IMPORTED_MODULE_6__.isEventPostType)() && (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_edit_post__WEBPACK_IMPORTED_MODULE_5__.PluginDocumentSettingPanel, {
    name: "gp-event-settings",
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Event settings', 'gatherpress'),
    initialOpen: true,
    className: "gp-event-settings"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.__experimentalVStack, {
    spacing: 2
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_datetime__WEBPACK_IMPORTED_MODULE_7__["default"], null), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.__experimentalDivider, null), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_venue__WEBPACK_IMPORTED_MODULE_8__["default"], null)));
};
(0,_wordpress_plugins__WEBPACK_IMPORTED_MODULE_4__.registerPlugin)('gp-event-settings', {
  render: EventSettings,
  icon: ''
});
(0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.dispatch)('core/edit-post').toggleEditorPanelOpened('gp-event-settings/gp-event-settings');

/***/ }),

/***/ "./src/panels/event-settings/venue/index.js":
/*!**************************************************!*\
  !*** ./src/panels/event-settings/venue/index.js ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../../helpers/broadcasting */ "./src/helpers/broadcasting.js");

/**
 * WordPress dependencies.
 */





/**
 * Internal dependencies.
 */

const VenuePanel = () => {
  const [venue, setVenue] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const editPost = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useDispatch)('core/editor').editPost;
  const {
    unlockPostSaving
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useDispatch)('core/editor');
  const venueTermId = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => select('core/editor').getEditedPostAttribute('_gp_venue'));
  const venueTerm = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => select('core').getEntityRecord('taxonomy', '_gp_venue', venueTermId));
  const venueId = venueTerm?.slug.replace('_venue_', '');
  const venueValue = venueTermId + ':' + venueId;
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    var _String;
    setVenue((_String = String(venueValue)) !== null && _String !== void 0 ? _String : '');
    (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__.Broadcaster)({
      setVenueId: venueId
    });
  });
  let venues = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => {
    return select('core').getEntityRecords('taxonomy', '_gp_venue', {
      per_page: -1,
      context: 'view'
    });
  }, [venue]);
  if (venues) {
    venues = venues.map(item => ({
      label: item.name,
      value: item.id + ':' + item.slug.replace('_venue_', '')
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
    (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__.Broadcaster)({
      setVenueId: value[1]
    });
    unlockPostSaving();
  };
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelRow, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Flex, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FlexItem, null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Venue', 'gatherpress')), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FlexItem, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Venue', 'gatherpress'),
    hideLabelFromVision: "true",
    value: venue,
    onChange: value => {
      updateTerm(value);
    },
    options: venues,
    style: {
      width: '11rem'
    }
  }))));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (VenuePanel);

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
/**
 * Internal dependencies
 */

})();

/******/ })()
;
//# sourceMappingURL=panels.js.map