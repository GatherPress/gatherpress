/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/panels/event-settings/datetime-panel/datetime-end/index.js":
/*!************************************************************************!*\
  !*** ./src/panels/event-settings/datetime-panel/datetime-end/index.js ***!
  \************************************************************************/
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
/* harmony import */ var _label__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./label */ "./src/panels/event-settings/datetime-panel/datetime-end/label.js");





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

/***/ "./src/panels/event-settings/datetime-panel/datetime-end/label.js":
/*!************************************************************************!*\
  !*** ./src/panels/event-settings/datetime-panel/datetime-end/label.js ***!
  \************************************************************************/
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
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../helpers */ "./src/panels/event-settings/datetime-panel/helpers.js");
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../../helpers */ "./src/panels/helpers.js");





function updateDateTimeEnd(dateTime) {
  let setState = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;
  (0,_helpers__WEBPACK_IMPORTED_MODULE_3__.validateDateTimeEnd)(dateTime);
  GatherPress.event_datetime.datetime_end = dateTime;
  this.setState({
    dateTime: dateTime
  });

  if (null !== setState) {
    setState({
      dateTime
    });
  }

  (0,_helpers__WEBPACK_IMPORTED_MODULE_4__.enableSave)();
}
function getDateTimeEnd() {
  GatherPress.event_datetime.datetime_end = this.state.dateTime;
  hasEventPastNotice();
  return this.state.dateTime;
}
function hasEventPastNotice() {
  const id = 'gp_event_past';
  const notices = wp.data.dispatch('core/notices');
  const eventPastStatus = (0,_helpers__WEBPACK_IMPORTED_MODULE_4__.hasEventPast)();
  notices.removeNotice(id);

  if (eventPastStatus) {
    notices.createNotice('warning', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('This event has already past.', 'gatherpress'), {
      id: id,
      isDismissible: true
    });
  }
}
class DateTimeEndLabel extends react__WEBPACK_IMPORTED_MODULE_0__.Component {
  constructor(props) {
    super(props);
    this.state = {
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

/***/ "./src/panels/event-settings/datetime-panel/datetime-start/index.js":
/*!**************************************************************************!*\
  !*** ./src/panels/event-settings/datetime-panel/datetime-start/index.js ***!
  \**************************************************************************/
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
/* harmony import */ var _label__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./label */ "./src/panels/event-settings/datetime-panel/datetime-start/label.js");





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

/***/ "./src/panels/event-settings/datetime-panel/datetime-start/label.js":
/*!**************************************************************************!*\
  !*** ./src/panels/event-settings/datetime-panel/datetime-start/label.js ***!
  \**************************************************************************/
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
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../helpers */ "./src/panels/event-settings/datetime-panel/helpers.js");
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../helpers */ "./src/panels/helpers.js");




function updateDateTimeStart(dateTime) {
  let setState = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;
  (0,_helpers__WEBPACK_IMPORTED_MODULE_2__.validateDateTimeStart)(dateTime);
  GatherPress.event_datetime.datetime_start = dateTime;
  this.setState({
    dateTime: dateTime
  });

  if (null !== setState) {
    setState({
      dateTime
    });
  }

  (0,_helpers__WEBPACK_IMPORTED_MODULE_3__.enableSave)();
}
function getDateTimeStart() {
  GatherPress.event_datetime.datetime_start = this.state.dateTime;
  return this.state.dateTime;
}
class DateTimeStartLabel extends react__WEBPACK_IMPORTED_MODULE_0__.Component {
  constructor(props) {
    super(props);
    this.state = {
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

/***/ "./src/panels/event-settings/datetime-panel/helpers.js":
/*!*************************************************************!*\
  !*** ./src/panels/event-settings/datetime-panel/helpers.js ***!
  \*************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "dateTimeFormat": () => (/* binding */ dateTimeFormat),
/* harmony export */   "saveDateTime": () => (/* binding */ saveDateTime),
/* harmony export */   "validateDateTimeEnd": () => (/* binding */ validateDateTimeEnd),
/* harmony export */   "validateDateTimeStart": () => (/* binding */ validateDateTimeStart)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _datetime_start_label__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./datetime-start/label */ "./src/panels/event-settings/datetime-panel/datetime-start/label.js");
/* harmony import */ var _datetime_end_label__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./datetime-end/label */ "./src/panels/event-settings/datetime-panel/datetime-end/label.js");



const dateTimeFormat = 'YYYY-MM-DDTHH:mm:ss';
function validateDateTimeStart(dateTime) {
  const dateTimeEndNumeric = moment(GatherPress.event_datetime.datetime_end).valueOf();
  const dateTimeNumeric = moment(dateTime).valueOf();

  if (dateTimeNumeric >= dateTimeEndNumeric) {
    const dateTimeEnd = moment(dateTimeNumeric).add(2, 'hours').format(dateTimeFormat);
    (0,_datetime_end_label__WEBPACK_IMPORTED_MODULE_2__.updateDateTimeEnd)(dateTimeEnd);
  }

  (0,_datetime_end_label__WEBPACK_IMPORTED_MODULE_2__.hasEventPastNotice)();
}
function validateDateTimeEnd(dateTime) {
  const dateTimeStartNumeric = moment(GatherPress.event_datetime.datetime_start).valueOf();
  const dateTimeNumeric = moment(dateTime).valueOf();

  if (dateTimeNumeric <= dateTimeStartNumeric) {
    const dateTimeStart = moment(dateTimeNumeric).subtract(2, 'hours').format(dateTimeFormat);
    (0,_datetime_start_label__WEBPACK_IMPORTED_MODULE_1__.updateDateTimeStart)(dateTimeStart);
  }

  (0,_datetime_end_label__WEBPACK_IMPORTED_MODULE_2__.hasEventPastNotice)();
} // @todo maybe put this is a save_post hook.
// https://www.ibenic.com/use-wordpress-hooks-package-javascript-apps/
// Then move button enabler

function saveDateTime() {
  let isSavingPost = wp.data.select('core/editor').isSavingPost(),
      isAutosavingPost = wp.data.select('core/editor').isAutosavingPost();

  if (isSavingPost && !isAutosavingPost) {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path: '/gatherpress/v1/event/datetime/',
      method: 'POST',
      data: {
        post_id: GatherPress.post_id,
        datetime_start: moment(GatherPress.event_datetime.datetime_start).format('YYYY-MM-DD HH:mm:ss'),
        datetime_end: moment(GatherPress.event_datetime.datetime_end).format('YYYY-MM-DD HH:mm:ss'),
        _wpnonce: GatherPress.nonce
      }
    }).then(res => {// Saved.
    });
  }
}

/***/ }),

/***/ "./src/panels/event-settings/datetime-panel/index.js":
/*!***********************************************************!*\
  !*** ./src/panels/event-settings/datetime-panel/index.js ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "DateTimeStartSettingPanel": () => (/* binding */ DateTimeStartSettingPanel)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./helpers */ "./src/panels/event-settings/datetime-panel/helpers.js");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _datetime_start__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./datetime-start */ "./src/panels/event-settings/datetime-panel/datetime-start/index.js");
/* harmony import */ var _datetime_start_label__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./datetime-start/label */ "./src/panels/event-settings/datetime-panel/datetime-start/label.js");
/* harmony import */ var _datetime_end__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./datetime-end */ "./src/panels/event-settings/datetime-panel/datetime-end/index.js");
/* harmony import */ var _datetime_end_label__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./datetime-end/label */ "./src/panels/event-settings/datetime-panel/datetime-end/label.js");







const {
  __
} = wp.i18n;
const currentDateTime = moment().format(_helpers__WEBPACK_IMPORTED_MODULE_1__.dateTimeFormat);
let dateTimeStart = GatherPress.event_datetime.datetime_start;
let dateTimeEnd = GatherPress.event_datetime.datetime_end;
wp.data.subscribe(_helpers__WEBPACK_IMPORTED_MODULE_1__.saveDateTime);
dateTimeStart = '' !== dateTimeStart ? moment(dateTimeStart).format(_helpers__WEBPACK_IMPORTED_MODULE_1__.dateTimeFormat) : currentDateTime;
dateTimeEnd = '' !== dateTimeEnd ? moment(dateTimeEnd).format(_helpers__WEBPACK_IMPORTED_MODULE_1__.dateTimeFormat) : moment(currentDateTime).add(2, 'hours').format(_helpers__WEBPACK_IMPORTED_MODULE_1__.dateTimeFormat);
GatherPress.event_datetime.datetime_start = dateTimeStart;
GatherPress.event_datetime.datetime_end = dateTimeEnd;
const DateTimeStartSettingPanel = () => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("h3", null, __('Date & time', 'gatherpress')), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelRow, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, __('Start', 'gatherpress')), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Dropdown, {
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
    }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_datetime_start_label__WEBPACK_IMPORTED_MODULE_4__.DateTimeStartLabel, null));
  },
  renderContent: () => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_datetime_start__WEBPACK_IMPORTED_MODULE_3__.DateTimeStart, null)
})), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelRow, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, __('End', 'gatherpress')), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Dropdown, {
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
    }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_datetime_end_label__WEBPACK_IMPORTED_MODULE_6__.DateTimeEndLabel, null));
  },
  renderContent: () => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_datetime_end__WEBPACK_IMPORTED_MODULE_5__.DateTimeEnd, null)
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
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../helpers */ "./src/panels/helpers.js");
/* harmony import */ var _datetime_panel__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./datetime-panel */ "./src/panels/event-settings/datetime-panel/index.js");
/* harmony import */ var _options_panel__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./options-panel */ "./src/panels/event-settings/options-panel/index.js");




const {
  registerPlugin
} = wp.plugins;
const {
  __
} = wp.i18n;
const {
  PluginDocumentSettingPanel
} = wp.editPost;

const EventSettings = () => {
  return (0,_helpers__WEBPACK_IMPORTED_MODULE_1__.isEventPostType)() && (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(PluginDocumentSettingPanel, {
    name: "gp-event-settings",
    title: __('Event settings', 'gatherpress'),
    initialOpen: true,
    className: "gp-event-settings"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_datetime_panel__WEBPACK_IMPORTED_MODULE_2__.DateTimeStartSettingPanel, null), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("hr", null), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_options_panel__WEBPACK_IMPORTED_MODULE_3__.OptionsPanel, null));
};

registerPlugin('gp-event-settings', {
  render: EventSettings,
  icon: ''
});

/***/ }),

/***/ "./src/panels/event-settings/options-panel/announce-event/index.js":
/*!*************************************************************************!*\
  !*** ./src/panels/event-settings/options-panel/announce-event/index.js ***!
  \*************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "AnnounceEvent": () => (/* binding */ AnnounceEvent)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _helpers__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../../helpers */ "./src/panels/helpers.js");





const {
  __
} = wp.i18n;
class AnnounceEvent extends react__WEBPACK_IMPORTED_MODULE_1__.Component {
  constructor(props) {
    super(props);
    this.state = {
      announceEventSent: '0' !== GatherPress.event_announced
    };
  }

  announce() {
    if (confirm(__('Ready to announce this event to all members?', 'gatherpress'))) {
      _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
        path: '/gatherpress/v1/event/announce/',
        method: 'POST',
        data: {
          post_id: GatherPress.post_id,
          _wpnonce: GatherPress.nonce
        }
      }).then(res => {
        GatherPress.event_announced = res.success ? '1' : '0';
        this.setState({
          announceEventSent: res.success
        });
      });
    }
  }

  shouldDisable() {
    return this.state.announceEventSent || 'publish' !== wp.data.select('core/editor').getEditedPostAttribute('status') || (0,_helpers__WEBPACK_IMPORTED_MODULE_4__.hasEventPast)();
  }

  render() {
    return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("h3", null, __('Options', 'gatherpress')), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelRow, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, __('Announce event', 'gatherpress')), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
      className: "components-button is-primary",
      "aria-disabled": this.shouldDisable(),
      onClick: () => this.announce(),
      disabled: this.shouldDisable()
    }, this.state.announceEventSent ? __('Sent', 'gatherpress') : __('Send', 'gatherpress'))));
  }

}

/***/ }),

/***/ "./src/panels/event-settings/options-panel/index.js":
/*!**********************************************************!*\
  !*** ./src/panels/event-settings/options-panel/index.js ***!
  \**********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "OptionsPanel": () => (/* binding */ OptionsPanel)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _announce_event__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./announce-event */ "./src/panels/event-settings/options-panel/announce-event/index.js");


const OptionsPanel = () => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_announce_event__WEBPACK_IMPORTED_MODULE_1__.AnnounceEvent, null);

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
    notices.createNotice('warning', __('This event has already past.', 'gatherpress'), {
      id: id,
      isDismissible: true
    });
  }
}
function hasEventPast() {
  if (moment().valueOf() > moment(GatherPress.event_datetime.datetime_end).valueOf()) {
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