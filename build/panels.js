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
/* harmony export */   DateTimeEndLabel: () => (/* binding */ DateTimeEndLabel),
/* harmony export */   DateTimeEndPicker: () => (/* binding */ DateTimeEndPicker),
/* harmony export */   DateTimeStartLabel: () => (/* binding */ DateTimeStartLabel),
/* harmony export */   DateTimeStartPicker: () => (/* binding */ DateTimeStartPicker)
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
  return moment__WEBPACK_IMPORTED_MODULE_3___default().tz(dateTimeStart, (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.getTimeZone)()).format(_helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.dateTimeLabelFormat);
};
const DateTimeEndLabel = props => {
  const {
    dateTimeEnd
  } = props;
  return moment__WEBPACK_IMPORTED_MODULE_3___default().tz(dateTimeEnd, (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.getTimeZone)()).format(_helpers_datetime__WEBPACK_IMPORTED_MODULE_4__.dateTimeLabelFormat);
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

/***/ "./src/components/DateTimeEnd.js":
/*!***************************************!*\
  !*** ./src/components/DateTimeEnd.js ***!
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
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _DateTime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./DateTime */ "./src/components/DateTime.js");
/* harmony import */ var _helpers_event__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/event */ "./src/helpers/event.js");
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../helpers/datetime */ "./src/helpers/datetime.js");

/**
 * External dependencies.
 */


/**
 * WordPress dependencies.
 */




/**
 * Internal dependencies.
 */




const DateTimeEnd = props => {
  const {
    dateTimeEnd,
    setDateTimeEnd
  } = props;
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    setDateTimeEnd(moment__WEBPACK_IMPORTED_MODULE_1___default().tz((0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_7__.getDateTimeEnd)(), (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_7__.getTimeZone)()).format(_helpers_datetime__WEBPACK_IMPORTED_MODULE_7__.dateTimeMomentFormat));
    (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_6__.Broadcaster)({
      setDateTimeEnd: dateTimeEnd
    });
    (0,_helpers_event__WEBPACK_IMPORTED_MODULE_5__.hasEventPastNotice)();
  });
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelRow, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Flex, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FlexItem, null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('End', 'gatherpress')), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FlexItem, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Dropdown, {
    position: "bottom left",
    renderToggle: ({
      isOpen,
      onToggle
    }) => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
      onClick: onToggle,
      "aria-expanded": isOpen,
      isLink: true
    }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_DateTime__WEBPACK_IMPORTED_MODULE_4__.DateTimeEndLabel, {
      dateTimeEnd: dateTimeEnd
    })),
    renderContent: () => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_DateTime__WEBPACK_IMPORTED_MODULE_4__.DateTimeEndPicker, {
      dateTimeEnd: dateTimeEnd,
      setDateTimeEnd: setDateTimeEnd
    })
  }))));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (DateTimeEnd);

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
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _DateTime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./DateTime */ "./src/components/DateTime.js");
/* harmony import */ var _helpers_event__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/event */ "./src/helpers/event.js");
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../helpers/datetime */ "./src/helpers/datetime.js");

/**
 * External dependencies.
 */


/**
 * WordPress dependencies.
 */




/**
 * Internal dependencies.
 */




const DateTimeStart = props => {
  const {
    dateTimeStart,
    setDateTimeStart
  } = props;
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    setDateTimeStart(moment__WEBPACK_IMPORTED_MODULE_1___default().tz((0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_7__.getDateTimeStart)(), (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_7__.getTimeZone)()).format(_helpers_datetime__WEBPACK_IMPORTED_MODULE_7__.dateTimeMomentFormat));
    (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_6__.Broadcaster)({
      setDateTimeStart: dateTimeStart
    });
    (0,_helpers_event__WEBPACK_IMPORTED_MODULE_5__.hasEventPastNotice)();
  });
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelRow, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Flex, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FlexItem, null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Start', 'gatherpress')), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.FlexItem, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Dropdown, {
    position: "bottom left",
    renderToggle: ({
      isOpen,
      onToggle
    }) => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
      onClick: onToggle,
      "aria-expanded": isOpen,
      isLink: true
    }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_DateTime__WEBPACK_IMPORTED_MODULE_4__.DateTimeStartLabel, {
      dateTimeStart: dateTimeStart
    })),
    renderContent: () => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_DateTime__WEBPACK_IMPORTED_MODULE_4__.DateTimeStartPicker, {
      dateTimeStart: dateTimeStart,
      setDateTimeStart: setDateTimeStart
    })
  }))));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (DateTimeStart);

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
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");

/**
 * WordPress dependencies.
 */





/**
 * Internal dependencies.
 */


const OnlineEventLink = () => {
  const {
    editPost,
    unlockPostSaving
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useDispatch)('core/editor');
  const onlineEventLinkMetaData = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => select('core/editor').getEditedPostAttribute('meta')._online_event_link);
  const [onlineEventLink, setOnlineEventLink] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(onlineEventLinkMetaData);
  const updateEventLink = value => {
    const meta = {
      _online_event_link: value
    };
    editPost({
      meta
    });
    setOnlineEventLink(value);
    (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__.Broadcaster)({
      setOnlineEventLink: value
    }, (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.getFromGlobal)('post_id'));
    unlockPostSaving();
  };
  (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__.Listener)({
    setOnlineEventLink
  }, (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_5__.getFromGlobal)('post_id'));
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Online event link', 'gatherpress'),
    value: onlineEventLink,
    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Add link to online event', 'gatherpress'),
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
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/datetime */ "./src/helpers/datetime.js");

/**
 * WordPress dependencies.
 */




/**
 * Internal dependencies.
 */



const TimeZone = props => {
  const {
    timezone,
    setTimezone
  } = props;
  const choices = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('timezone_choices');

  // Run only once.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    setTimezone((0,_helpers_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('event_datetime.timezone'));
  }, [setTimezone]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_3__.Broadcaster)({
      setTimezone: (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('event_datetime.timezone')
    });
  });
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.PanelRow, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Time Zone', 'gatherpress'),
    value: (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_5__.maybeConvertUtcOffsetForSelect)(timezone),
    onChange: value => {
      value = (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_5__.maybeConvertUtcOffsetForDatabase)(value);
      setTimezone(value);
      (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_4__.setToGlobal)('event_datetime.timezone', value);
      (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_4__.enableSave)();
    }
  }, Object.keys(choices).map(group => {
    return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("optgroup", {
      key: group,
      label: group
    }, Object.keys(choices[group]).map(item => {
      return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("option", {
        key: item,
        value: item
      }, choices[group][item]);
    }));
  })));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (TimeZone);

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
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../helpers/broadcasting */ "./src/helpers/broadcasting.js");

/**
 * WordPress dependencies.
 */





/**
 * Internal dependencies.
 */

const VenueSelector = () => {
  const [venue, setVenue] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const editPost = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useDispatch)('core/editor').editPost;
  const {
    unlockPostSaving
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useDispatch)('core/editor');
  const venueTermId = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => select('core/editor').getEditedPostAttribute('_gp_venue'));
  const venueTerm = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => select('core').getEntityRecord('taxonomy', '_gp_venue', venueTermId));
  const venueSlug = venueTerm?.slug.replace(/^_/, '');
  const venueValue = venueTermId + ':' + venueSlug;
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    var _String;
    setVenue((_String = String(venueValue)) !== null && _String !== void 0 ? _String : '');
    (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__.Broadcaster)({
      setVenueSlug: venueSlug
    });
  }, [venueValue, venueSlug]);
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
    (0,_helpers_broadcasting__WEBPACK_IMPORTED_MODULE_4__.Broadcaster)({
      setVenueSlug: value[1]
    });
    unlockPostSaving();
  };
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelRow, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
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
const Broadcaster = (payload, identifier = false) => {
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
const Listener = (payload, identifier = false) => {
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
/* harmony export */   dateTimeDatabaseFormat: () => (/* binding */ dateTimeDatabaseFormat),
/* harmony export */   dateTimeLabelFormat: () => (/* binding */ dateTimeLabelFormat),
/* harmony export */   dateTimeMomentFormat: () => (/* binding */ dateTimeMomentFormat),
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
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _globals__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./globals */ "./src/helpers/globals.js");
/* harmony import */ var _event__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./event */ "./src/helpers/event.js");
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
const dateTimeLabelFormat = 'MMMM D, YYYY h:mm a';
const getTimeZone = (timezone = (0,_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('event_datetime.timezone')) => {
  if (!!moment__WEBPACK_IMPORTED_MODULE_0___default().tz.zone(timezone)) {
    return timezone;
  }
  return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('GMT', 'gatherpress');
};
const getUtcOffset = timezone => {
  timezone = getTimeZone(timezone);
  if ((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('GMT', 'gatherpress') !== timezone) {
    return '';
  }
  const offset = (0,_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('event_datetime.timezone');
  return maybeConvertUtcOffsetForDisplay(offset);
};
const maybeConvertUtcOffsetForDisplay = (offset = '') => {
  return offset.replace(':', '');
};
const maybeConvertUtcOffsetForDatabase = (offset = '') => {
  // Regex: https://regex101.com/r/9bMgJd/1.
  const pattern = /^UTC(\+|-)(\d+)(.\d+)?$/;
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
};
const maybeConvertUtcOffsetForSelect = (offset = '') => {
  // Regex: https://regex101.com/r/nOXCPo/1
  const pattern = /^(\+|-)(\d{2}):(00|15|30|45)$/;
  const sign = offset.replace(pattern, '$1');
  if (sign !== offset) {
    const hour = parseInt(offset.replace(pattern, '$2')).toString();
    const minute = offset.replace(pattern, '$3').replace('00', '').replace('15', '.25').replace('30', '.5').replace('45', '.75');
    return 'UTC' + sign + hour + minute;
  }
  return offset;
};
const defaultDateTimeStart = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(getTimeZone()).add(1, 'day').set('hour', 18).set('minute', 0).set('second', 0).format(dateTimeMomentFormat);
const defaultDateTimeEnd = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(defaultDateTimeStart, getTimeZone()).add(2, 'hours').format(dateTimeMomentFormat);
const getDateTimeStart = () => {
  let dateTime = (0,_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('event_datetime.datetime_start');
  dateTime = '' !== dateTime ? moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTime, getTimeZone()).format(dateTimeMomentFormat) : defaultDateTimeStart;
  (0,_globals__WEBPACK_IMPORTED_MODULE_4__.setToGlobal)('event_datetime.datetime_start', dateTime);
  return dateTime;
};
const getDateTimeEnd = () => {
  let dateTime = (0,_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('event_datetime.datetime_end');
  dateTime = '' !== dateTime ? moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTime, getTimeZone()).format(dateTimeMomentFormat) : defaultDateTimeEnd;
  (0,_globals__WEBPACK_IMPORTED_MODULE_4__.setToGlobal)('event_datetime.datetime_end', dateTime);
  return dateTime;
};
const updateDateTimeStart = (date, setDateTimeStart = null) => {
  validateDateTimeStart(date);
  (0,_globals__WEBPACK_IMPORTED_MODULE_4__.setToGlobal)('event_datetime.datetime_start', date);
  if ('function' === typeof setDateTimeStart) {
    setDateTimeStart(date);
  }
  (0,_globals__WEBPACK_IMPORTED_MODULE_4__.enableSave)();
};
const updateDateTimeEnd = (date, setDateTimeEnd = null) => {
  validateDateTimeEnd(date);
  (0,_globals__WEBPACK_IMPORTED_MODULE_4__.setToGlobal)('event_datetime.datetime_end', date);
  if (null !== setDateTimeEnd) {
    setDateTimeEnd(date);
  }
  (0,_globals__WEBPACK_IMPORTED_MODULE_4__.enableSave)();
};
function validateDateTimeStart(dateTimeStart) {
  const dateTimeEndNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default().tz((0,_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('event_datetime.datetime_end'), getTimeZone()).valueOf();
  const dateTimeStartNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeStart, getTimeZone()).valueOf();
  if (dateTimeStartNumeric >= dateTimeEndNumeric) {
    const dateTimeEnd = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeStartNumeric, getTimeZone()).add(2, 'hours').format(dateTimeMomentFormat);
    updateDateTimeEnd(dateTimeEnd);
  }
}
function validateDateTimeEnd(dateTimeEnd) {
  const dateTimeStartNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default().tz((0,_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('event_datetime.datetime_start'), getTimeZone()).valueOf();
  const dateTimeEndNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeEnd, getTimeZone()).valueOf();
  if (dateTimeEndNumeric <= dateTimeStartNumeric) {
    const dateTimeStart = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeEndNumeric, getTimeZone()).subtract(2, 'hours').format(dateTimeMomentFormat);
    updateDateTimeStart(dateTimeStart);
  }
}
function saveDateTime() {
  const isSavingPost = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor').isSavingPost(),
    isAutosavingPost = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor').isAutosavingPost();
  if ((0,_event__WEBPACK_IMPORTED_MODULE_5__.isEventPostType)() && isSavingPost && !isAutosavingPost) {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
      path: '/gatherpress/v1/event/datetime/',
      method: 'POST',
      data: {
        post_id: (0,_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('post_id'),
        datetime_start: moment__WEBPACK_IMPORTED_MODULE_0___default().tz((0,_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('event_datetime.datetime_start'), getTimeZone()).format(dateTimeDatabaseFormat),
        datetime_end: moment__WEBPACK_IMPORTED_MODULE_0___default().tz((0,_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('event_datetime.datetime_end'), getTimeZone()).format(dateTimeDatabaseFormat),
        timezone: (0,_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('event_datetime.timezone'),
        _wpnonce: (0,_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('nonce')
      }
    }).then(() => {
      (0,_event__WEBPACK_IMPORTED_MODULE_5__.triggerEventCommuncation)();
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
/* harmony export */   CheckCurrentPostType: () => (/* binding */ CheckCurrentPostType),
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



function isEventPostType() {
  return 'gp_event' === (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor').getCurrentPostType();
}
function CheckCurrentPostType() {
  return (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor').getCurrentPostType();
}
function hasEventPast() {
  const dateTimeEnd = moment__WEBPACK_IMPORTED_MODULE_0___default()((0,_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('event_datetime.datetime_end'));
  return moment__WEBPACK_IMPORTED_MODULE_0___default().tz((0,_datetime__WEBPACK_IMPORTED_MODULE_3__.getTimeZone)()).valueOf() > dateTimeEnd.tz((0,_datetime__WEBPACK_IMPORTED_MODULE_3__.getTimeZone)()).valueOf();
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
function triggerEventCommuncation() {
  const id = 'gp_event_communcation';
  const notices = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.dispatch)('core/notices');
  notices.removeNotice(id);
  if ((0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor').getEditedPostAttribute('status') === 'publish' && !hasEventPast()) {
    notices.createNotice('success', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Update members about this event via email?', 'gatherpress'), {
      id,
      isDismissible: true,
      actions: [{
        onClick: () => {
          (0,_broadcasting__WEBPACK_IMPORTED_MODULE_5__.Broadcaster)({
            setOpen: true
          });
        },
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Create Message', 'gatherpress')
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
/* harmony export */   setToGlobal: () => (/* binding */ setToGlobal)
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

function isVenuePostType() {
  return 'gp_venue' === (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.select)('core/editor').getCurrentPostType();
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
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../helpers/datetime */ "./src/helpers/datetime.js");
/* harmony import */ var _components_DateTimeStart__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../../components/DateTimeStart */ "./src/components/DateTimeStart.js");
/* harmony import */ var _components_DateTimeEnd__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../../components/DateTimeEnd */ "./src/components/DateTimeEnd.js");
/* harmony import */ var _components_TimeZone__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../../components/TimeZone */ "./src/components/TimeZone.js");

/**
 * WordPress dependencies.
 */




/**
 * Internal dependencies.
 */




(0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.subscribe)(_helpers_datetime__WEBPACK_IMPORTED_MODULE_3__.saveDateTime);
const DateTimePanel = () => {
  const [dateTimeStart, setDateTimeStart] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)();
  const [dateTimeEnd, setDateTimeEnd] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)();
  const [timezone, setTimezone] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)();
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("h3", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Date & time', 'gatherpress')), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_DateTimeStart__WEBPACK_IMPORTED_MODULE_4__["default"], {
    dateTimeStart: dateTimeStart,
    setDateTimeStart: setDateTimeStart
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_DateTimeEnd__WEBPACK_IMPORTED_MODULE_5__["default"], {
    dateTimeEnd: dateTimeEnd,
    setDateTimeEnd: setDateTimeEnd
  })), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_TimeZone__WEBPACK_IMPORTED_MODULE_6__["default"], {
    timezone: timezone,
    setTimezone: setTimezone
  })));
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
/* harmony import */ var _venue_selector__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./venue-selector */ "./src/panels/event-settings/venue-selector/index.js");
/* harmony import */ var _online_link__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./online-link */ "./src/panels/event-settings/online-link/index.js");

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
    spacing: 6
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_datetime__WEBPACK_IMPORTED_MODULE_7__["default"], null), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_venue_selector__WEBPACK_IMPORTED_MODULE_8__["default"], null), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_online_link__WEBPACK_IMPORTED_MODULE_9__["default"], null)));
};
(0,_wordpress_plugins__WEBPACK_IMPORTED_MODULE_4__.registerPlugin)('gp-event-settings', {
  render: EventSettings
});
(0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.dispatch)('core/edit-post').toggleEditorPanelOpened('gp-event-settings/gp-event-settings');

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
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_OnlineEventLink__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../components/OnlineEventLink */ "./src/components/OnlineEventLink.js");

/**
 * Internal dependencies.
 */

const OnlineEventLinkPanel = () => {
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_OnlineEventLink__WEBPACK_IMPORTED_MODULE_1__["default"], null));
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
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _components_VenueSelector__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../components/VenueSelector */ "./src/components/VenueSelector.js");

/**
 * Internal dependencies.
 */

const VenueSelectorPanel = () => {
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("section", null, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_components_VenueSelector__WEBPACK_IMPORTED_MODULE_1__["default"], null));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (VenueSelectorPanel);

/***/ }),

/***/ "./src/panels/venue-settings/index.js":
/*!********************************************!*\
  !*** ./src/panels/venue-settings/index.js ***!
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
/* harmony import */ var _helpers_venue__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../helpers/venue */ "./src/helpers/venue.js");

/**
 * WordPress dependencies.
 */






/**
 * Internal dependencies.
 */

const VenueSettings = () => {
  return (0,_helpers_venue__WEBPACK_IMPORTED_MODULE_6__.isVenuePostType)() && (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_edit_post__WEBPACK_IMPORTED_MODULE_5__.PluginDocumentSettingPanel, {
    name: "gp-venue-settings",
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Venue settings', 'gatherpress'),
    initialOpen: true,
    className: "gp-venue-settings"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.__experimentalVStack, {
    spacing: 6
  }));
};
(0,_wordpress_plugins__WEBPACK_IMPORTED_MODULE_4__.registerPlugin)('gp-venue-settings', {
  render: VenueSettings
});
(0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.dispatch)('core/edit-post').toggleEditorPanelOpened('gp-venue-settings/gp-venue-settings');

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