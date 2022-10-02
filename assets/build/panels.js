/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

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

/***/ "./src/panels/event-settings/datetime/datetime-end/index.js":
/*!******************************************************************!*\
  !*** ./src/panels/event-settings/datetime/datetime-end/index.js ***!
  \******************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "DateTimeEnd": () => (/* binding */ DateTimeEnd)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/date */ "@wordpress/date");
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_date__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_compose__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/compose */ "@wordpress/compose");
/* harmony import */ var _wordpress_compose__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_compose__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _label__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./label */ "./src/panels/event-settings/datetime/datetime-end/label.js");


/**
 * WordPress dependencies.
 */
 // eslint-disable-next-line @wordpress/no-unsafe-wp-apis



/**
 * Internal dependencies.
 */


const DateTimeEnd = (0,_wordpress_compose__WEBPACK_IMPORTED_MODULE_3__.withState)()(_ref => {
  let {
    setState
  } = _ref;

  const settings = (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_2__.__experimentalGetSettings)();

  const is12HourTime = /a(?!\\)/i.test(settings.formats.time.toLowerCase().replace(/\\\\/g, '').split('').reverse().join(''));
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.DateTimePicker, {
    currentDate: (0,_label__WEBPACK_IMPORTED_MODULE_4__.getDateTimeEnd)(),
    onChange: date => (0,_label__WEBPACK_IMPORTED_MODULE_4__.updateDateTimeEnd)(date, setState),
    is12Hour: is12HourTime
  });
});

/***/ }),

/***/ "./src/panels/event-settings/datetime/datetime-end/label.js":
/*!******************************************************************!*\
  !*** ./src/panels/event-settings/datetime/datetime-end/label.js ***!
  \******************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "DateTimeEndLabel": () => (/* binding */ DateTimeEndLabel),
/* harmony export */   "getDateTimeEnd": () => (/* binding */ getDateTimeEnd),
/* harmony export */   "hasEventPastNotice": () => (/* binding */ hasEventPastNotice),
/* harmony export */   "updateDateTimeEnd": () => (/* binding */ updateDateTimeEnd)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/date */ "@wordpress/date");
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_date__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../helpers */ "./src/panels/event-settings/datetime/helpers.js");
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../../helpers */ "./src/panels/helpers.js");
/**
 * External dependencies.
 */

/**
 * WordPress dependencies.
 */
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis



/**
 * Internal dependencies.
 */




function updateDateTimeEnd(dateTime) {
  let setState = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;
  (0,_helpers__WEBPACK_IMPORTED_MODULE_4__.validateDateTimeEnd)(dateTime); // eslint-disable-next-line no-undef

  GatherPress.event_datetime.datetime_end = dateTime;
  this.setState({
    dateTime
  });

  if (null !== setState) {
    setState({
      dateTime
    });
  }

  const payload = {
    setDateTimeEnd: dateTime
  };
  (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_3__.Broadcaster)(payload);
  (0,_helpers__WEBPACK_IMPORTED_MODULE_5__.enableSave)();
}
function getDateTimeEnd() {
  // eslint-disable-next-line no-undef
  GatherPress.event_datetime.datetime_end = this.state.dateTime;
  hasEventPastNotice();
  return this.state.dateTime;
}
function hasEventPastNotice() {
  const id = 'gp_event_past';
  const notices = wp.data.dispatch('core/notices');
  const eventPastStatus = (0,_helpers__WEBPACK_IMPORTED_MODULE_5__.hasEventPast)();
  notices.removeNotice(id);

  if (eventPastStatus) {
    notices.createNotice('warning', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('This event has already past.', 'gatherpress'), {
      id,
      isDismissible: true
    });
  }
}
class DateTimeEndLabel extends react__WEBPACK_IMPORTED_MODULE_0__.Component {
  constructor(props) {
    super(props);
    this.state = {
      // eslint-disable-next-line no-undef
      dateTime: GatherPress.event_datetime.datetime_end
    };
  }

  componentDidMount() {
    this.updateDateTimeEnd = updateDateTimeEnd;
    this.getDateTimeEnd = getDateTimeEnd;
    updateDateTimeEnd = updateDateTimeEnd.bind(this);
    getDateTimeEnd = getDateTimeEnd.bind(this);
    hasEventPastNotice();
  }

  componentWillUnmount() {
    updateDateTimeEnd = this.updateDateTimeEnd;
    getDateTimeEnd = this.getDateTimeEnd;
  }

  componentDidUpdate() {
    hasEventPastNotice();
  }

  render() {
    const settings = (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_1__.__experimentalGetSettings)();

    return (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_1__.dateI18n)(`${settings.formats.date} ${settings.formats.time}`, this.state.dateTime);
  }

}

/***/ }),

/***/ "./src/panels/event-settings/datetime/datetime-start/index.js":
/*!********************************************************************!*\
  !*** ./src/panels/event-settings/datetime/datetime-start/index.js ***!
  \********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "DateTimeStart": () => (/* binding */ DateTimeStart)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/date */ "@wordpress/date");
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_date__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_compose__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/compose */ "@wordpress/compose");
/* harmony import */ var _wordpress_compose__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_compose__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _label__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./label */ "./src/panels/event-settings/datetime/datetime-start/label.js");


/**
 * WordPress dependencies.
 */
 // eslint-disable-next-line @wordpress/no-unsafe-wp-apis



/**
 * Internal dependencies.
 */


const DateTimeStart = (0,_wordpress_compose__WEBPACK_IMPORTED_MODULE_3__.withState)()(_ref => {
  let {
    setState
  } = _ref;

  const settings = (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_2__.__experimentalGetSettings)();

  const is12HourTime = /a(?!\\)/i.test(settings.formats.time.toLowerCase().replace(/\\\\/g, '').split('').reverse().join(''));
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.DateTimePicker, {
    currentDate: (0,_label__WEBPACK_IMPORTED_MODULE_4__.getDateTimeStart)(),
    onChange: date => (0,_label__WEBPACK_IMPORTED_MODULE_4__.updateDateTimeStart)(date, setState),
    is12Hour: is12HourTime
  });
});

/***/ }),

/***/ "./src/panels/event-settings/datetime/datetime-start/label.js":
/*!********************************************************************!*\
  !*** ./src/panels/event-settings/datetime/datetime-start/label.js ***!
  \********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "DateTimeStartLabel": () => (/* binding */ DateTimeStartLabel),
/* harmony export */   "getDateTimeStart": () => (/* binding */ getDateTimeStart),
/* harmony export */   "updateDateTimeStart": () => (/* binding */ updateDateTimeStart)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/date */ "@wordpress/date");
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_date__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../../../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../helpers */ "./src/panels/event-settings/datetime/helpers.js");
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../../helpers */ "./src/panels/helpers.js");
/**
 * External dependencies.
 */

/**
 * WordPress dependencies.
 */
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis


/**
 * Internal dependencies.
 */




function updateDateTimeStart(dateTime) {
  let setState = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;
  (0,_helpers__WEBPACK_IMPORTED_MODULE_3__.validateDateTimeStart)(dateTime); // eslint-disable-next-line no-undef

  GatherPress.event_datetime.datetime_start = dateTime;
  this.setState({
    dateTime
  });

  if (null !== setState) {
    setState({
      dateTime
    });
  }

  const payload = {
    setDateTimeStart: dateTime
  };
  (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_2__.Broadcaster)(payload);
  (0,_helpers__WEBPACK_IMPORTED_MODULE_4__.enableSave)();
}
function getDateTimeStart() {
  // eslint-disable-next-line no-undef
  GatherPress.event_datetime.datetime_start = this.state.dateTime;
  return this.state.dateTime;
}
class DateTimeStartLabel extends react__WEBPACK_IMPORTED_MODULE_0__.Component {
  constructor(props) {
    super(props);
    this.state = {
      // eslint-disable-next-line no-undef
      dateTime: GatherPress.event_datetime.datetime_start
    };
  }

  componentDidMount() {
    this.updateDateTimeStart = updateDateTimeStart;
    this.getDateTimeStart = getDateTimeStart;
    updateDateTimeStart = updateDateTimeStart.bind(this);
    getDateTimeStart = getDateTimeStart.bind(this);
  }

  componentWillUnmount() {
    updateDateTimeStart = this.updateDateTimeStart;
    getDateTimeStart = this.getDateTimeStart;
  }

  render() {
    const settings = (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_1__.__experimentalGetSettings)();

    return (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_1__.dateI18n)(`${settings.formats.date} ${settings.formats.time}`, this.state.dateTime);
  }

}

/***/ }),

/***/ "./src/panels/event-settings/datetime/helpers.js":
/*!*******************************************************!*\
  !*** ./src/panels/event-settings/datetime/helpers.js ***!
  \*******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "dateTimeFormat": () => (/* binding */ dateTimeFormat),
/* harmony export */   "saveDateTime": () => (/* binding */ saveDateTime),
/* harmony export */   "validateDateTimeEnd": () => (/* binding */ validateDateTimeEnd),
/* harmony export */   "validateDateTimeStart": () => (/* binding */ validateDateTimeStart)
/* harmony export */ });
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../helpers */ "./src/panels/helpers.js");
/* harmony import */ var _datetime_start_label__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./datetime-start/label */ "./src/panels/event-settings/datetime/datetime-start/label.js");
/* harmony import */ var _datetime_end_label__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./datetime-end/label */ "./src/panels/event-settings/datetime/datetime-end/label.js");
/**
 * External dependencies.
 */

/**
 * WordPress dependencies.
 */



/**
 * Internal dependencies.
 */



const dateTimeFormat = 'YYYY-MM-DDTHH:mm:ss';
function validateDateTimeStart(dateTime) {
  const dateTimeEndNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default()( // eslint-disable-next-line no-undef
  GatherPress.event_datetime.datetime_end).valueOf();
  const dateTimeNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default()(dateTime).valueOf();

  if (dateTimeNumeric >= dateTimeEndNumeric) {
    const dateTimeEnd = moment__WEBPACK_IMPORTED_MODULE_0___default()(dateTimeNumeric).add(2, 'hours').format(dateTimeFormat);
    (0,_datetime_end_label__WEBPACK_IMPORTED_MODULE_4__.updateDateTimeEnd)(dateTimeEnd);
  }

  (0,_datetime_end_label__WEBPACK_IMPORTED_MODULE_4__.hasEventPastNotice)();
}
function validateDateTimeEnd(dateTime) {
  const dateTimeStartNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default()( // eslint-disable-next-line no-undef
  GatherPress.event_datetime.datetime_start).valueOf();
  const dateTimeNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default()(dateTime).valueOf();

  if (dateTimeNumeric <= dateTimeStartNumeric) {
    const dateTimeStart = moment__WEBPACK_IMPORTED_MODULE_0___default()(dateTimeNumeric).subtract(2, 'hours').format(dateTimeFormat);
    (0,_datetime_start_label__WEBPACK_IMPORTED_MODULE_3__.updateDateTimeStart)(dateTimeStart);
  }

  (0,_datetime_end_label__WEBPACK_IMPORTED_MODULE_4__.hasEventPastNotice)();
} // @todo maybe put this is a save_post hook.
// https://www.ibenic.com/use-wordpress-hooks-package-javascript-apps/
// Then move button enabler

function saveDateTime() {
  const isSavingPost = wp.data.select('core/editor').isSavingPost(),
        isAutosavingPost = wp.data.select('core/editor').isAutosavingPost();

  if ((0,_helpers__WEBPACK_IMPORTED_MODULE_2__.isEventPostType)() && isSavingPost && !isAutosavingPost) {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path: '/gatherpress/v1/event/datetime/',
      method: 'POST',
      data: {
        // eslint-disable-next-line no-undef
        post_id: GatherPress.post_id,
        datetime_start: moment__WEBPACK_IMPORTED_MODULE_0___default()( // eslint-disable-next-line no-undef
        GatherPress.event_datetime.datetime_start).format('YYYY-MM-DD HH:mm:ss'),
        datetime_end: moment__WEBPACK_IMPORTED_MODULE_0___default()( // eslint-disable-next-line no-undef
        GatherPress.event_datetime.datetime_end).format('YYYY-MM-DD HH:mm:ss'),
        // eslint-disable-next-line no-undef
        _wpnonce: GatherPress.nonce
      }
    }).then(() => {// Saved.
    });
  }
}

/***/ }),

/***/ "./src/panels/event-settings/datetime/index.js":
/*!*****************************************************!*\
  !*** ./src/panels/event-settings/datetime/index.js ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "DateTimeStartSettingPanel": () => (/* binding */ DateTimeStartSettingPanel)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./helpers */ "./src/panels/event-settings/datetime/helpers.js");
/* harmony import */ var _datetime_start__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./datetime-start */ "./src/panels/event-settings/datetime/datetime-start/index.js");
/* harmony import */ var _datetime_start_label__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./datetime-start/label */ "./src/panels/event-settings/datetime/datetime-start/label.js");
/* harmony import */ var _datetime_end__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./datetime-end */ "./src/panels/event-settings/datetime/datetime-end/index.js");
/* harmony import */ var _datetime_end_label__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./datetime-end/label */ "./src/panels/event-settings/datetime/datetime-end/label.js");


/**
 * External dependencies.
 */

/**
 * WordPress dependencies.
 */




/**
 * Internal dependencies.
 */






const currentDateTime = moment__WEBPACK_IMPORTED_MODULE_1___default()().format(_helpers__WEBPACK_IMPORTED_MODULE_5__.dateTimeFormat); // eslint-disable-next-line no-undef

let dateTimeStart = GatherPress.event_datetime.datetime_start; // eslint-disable-next-line no-undef

let dateTimeEnd = GatherPress.event_datetime.datetime_end;
(0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.subscribe)(_helpers__WEBPACK_IMPORTED_MODULE_5__.saveDateTime);
dateTimeStart = '' !== dateTimeStart ? moment__WEBPACK_IMPORTED_MODULE_1___default()(dateTimeStart).format(_helpers__WEBPACK_IMPORTED_MODULE_5__.dateTimeFormat) : currentDateTime;
dateTimeEnd = '' !== dateTimeEnd ? moment__WEBPACK_IMPORTED_MODULE_1___default()(dateTimeEnd).format(_helpers__WEBPACK_IMPORTED_MODULE_5__.dateTimeFormat) : moment__WEBPACK_IMPORTED_MODULE_1___default()(currentDateTime).add(2, 'hours').format(_helpers__WEBPACK_IMPORTED_MODULE_5__.dateTimeFormat); // eslint-disable-next-line no-undef

GatherPress.event_datetime.datetime_start = dateTimeStart; // eslint-disable-next-line no-undef

GatherPress.event_datetime.datetime_end = dateTimeEnd;
const DateTimeStartSettingPanel = () => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("h3", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Date & time', 'gatherpress')), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelRow, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Start', 'gatherpress')), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Dropdown, {
  position: "bottom left",
  renderToggle: _ref => {
    let {
      isOpen,
      onToggle
    } = _ref;
    return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
      onClick: onToggle,
      "aria-expanded": isOpen,
      isLink: true
    }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_datetime_start_label__WEBPACK_IMPORTED_MODULE_7__.DateTimeStartLabel, null));
  },
  renderContent: () => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_datetime_start__WEBPACK_IMPORTED_MODULE_6__.DateTimeStart, null)
})), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelRow, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('End', 'gatherpress')), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Dropdown, {
  position: "bottom left",
  renderToggle: _ref2 => {
    let {
      isOpen,
      onToggle
    } = _ref2;
    return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
      onClick: onToggle,
      "aria-expanded": isOpen,
      isLink: true
    }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_datetime_end_label__WEBPACK_IMPORTED_MODULE_9__.DateTimeEndLabel, null));
  },
  renderContent: () => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_datetime_end__WEBPACK_IMPORTED_MODULE_8__.DateTimeEnd, null)
})));

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
/* harmony import */ var _wordpress_plugins__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/plugins */ "@wordpress/plugins");
/* harmony import */ var _wordpress_plugins__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_plugins__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_edit_post__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/edit-post */ "@wordpress/edit-post");
/* harmony import */ var _wordpress_edit_post__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_edit_post__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../helpers */ "./src/panels/helpers.js");
/* harmony import */ var _datetime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./datetime */ "./src/panels/event-settings/datetime/index.js");
/* harmony import */ var _venue__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./venue */ "./src/panels/event-settings/venue/index.js");


/**
 * WordPress dependencies.
 */



/**
 * Internal dependencies.
 */



 // import { OptionsPanel } from './options';



const EventSettings = () => {
  const [venue, setVenue] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  return (0,_helpers__WEBPACK_IMPORTED_MODULE_4__.isEventPostType)() && (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_edit_post__WEBPACK_IMPORTED_MODULE_3__.PluginDocumentSettingPanel, {
    name: "gp-event-settings",
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Event settings', 'gatherpress'),
    initialOpen: true,
    className: "gp-event-settings"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_datetime__WEBPACK_IMPORTED_MODULE_5__.DateTimeStartSettingPanel, null), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("hr", null), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_venue__WEBPACK_IMPORTED_MODULE_6__["default"], {
    venue: venue,
    setVenue: setVenue
  }));
};

(0,_wordpress_plugins__WEBPACK_IMPORTED_MODULE_2__.registerPlugin)('gp-event-settings', {
  render: EventSettings,
  icon: ''
});

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



const VenuePanel = props => {
  const {
    venue,
    setVenue
  } = props;
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

/***/ "./src/panels/helpers.js":
/*!*******************************!*\
  !*** ./src/panels/helpers.js ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "enableSave": () => (/* binding */ enableSave),
/* harmony export */   "hasEventPast": () => (/* binding */ hasEventPast),
/* harmony export */   "hasEventPastNotice": () => (/* binding */ hasEventPastNotice),
/* harmony export */   "isEventPostType": () => (/* binding */ isEventPostType)
/* harmony export */ });
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/**
 * External dependencies.
 */

/**
 * WordPress dependencies.
 */

 // Checks if the post type is for events.

function isEventPostType() {
  const getPostType = wp.data.select('core/editor').getCurrentPostType(); // Gets the current post type.

  return 'gp_event' === getPostType;
} // @todo hack approach to enabling Save buttons after update
// https://github.com/WordPress/gutenberg/issues/13774

function enableSave() {
  wp.data.dispatch('core/editor').editPost({
    meta: {
      _non_existing_meta: true
    }
  });
}
function hasEventPastNotice() {
  const id = 'gp_event_past';
  const notices = wp.data.dispatch('core/notices');
  notices.removeNotice(id);

  if (hasEventPast()) {
    notices.createNotice('warning', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('This event has already past.', 'gatherpress'), {
      id,
      isDismissible: true
    });
  }
}
function hasEventPast() {
  if (moment__WEBPACK_IMPORTED_MODULE_0___default()().valueOf() > // eslint-disable-next-line no-undef
  moment__WEBPACK_IMPORTED_MODULE_0___default()(GatherPress.event_datetime.datetime_end).valueOf()) {
    return true;
  }

  return false;
}

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

/***/ "@wordpress/compose":
/*!*********************************!*\
  !*** external ["wp","compose"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["compose"];

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