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
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var clsx__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! clsx */ "./node_modules/clsx/dist/clsx.mjs");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../helpers/datetime */ "./src/helpers/datetime.js");
/* harmony import */ var _components_DateTimeRange__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../components/DateTimeRange */ "./src/components/DateTimeRange.js");
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../../helpers/globals */ "./src/helpers/globals.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__);
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
 * @param {string} dateTimeStart
 * @param {string} dateTimeEnd
 * @param {string} timezone
 * @return {string} Displayed date.
 */

const displayDateTime = (dateTimeStart, dateTimeEnd, timezone) => {
  const dateFormat = (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.convertPHPToMomentFormat)((0,_helpers_globals__WEBPACK_IMPORTED_MODULE_8__.getFromGlobal)('settings.dateFormat'));
  const timeFormat = (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.convertPHPToMomentFormat)((0,_helpers_globals__WEBPACK_IMPORTED_MODULE_8__.getFromGlobal)('settings.timeFormat'));
  const timezoneFormat = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_8__.getFromGlobal)('settings.showTimezone') ? 'z' : '';
  const startFormat = dateFormat + ' ' + timeFormat;
  timezone = (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.getTimezone)(timezone);
  let endFormat = dateFormat + ' ' + timeFormat + ' ' + timezoneFormat;
  if (moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeStart, timezone).format(dateFormat) === moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeEnd, timezone).format(dateFormat)) {
    endFormat = timeFormat + ' ' + timezoneFormat;
  }
  return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)( /* translators: %1$s: datetime start, %2$s: datetime end, %3$s timezone. */
  (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('%1$s to %2$s %3$s', 'gatherpress'), moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeStart, timezone).format(startFormat), moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeEnd, timezone).format(endFormat), (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.getUtcOffset)(timezone));
};

/**
 * Edit component for the GatherPress Event Date block.
 *
 * This component represents the editable view of the GatherPress Event Date block
 * in the WordPress block editor. It manages the state of the start and end date,
 * time, and timezone for the block, and renders the user interface accordingly.
 * The component includes a BlockControls toolbar, displays the formatted date and
 * time, and provides controls for editing the date and time range via the
 * DateTimeRange component within InspectorControls.
 *
 * @since 1.0.0
 *
 * @param {Object}   root0                      The props passed to the Edit component.
 * @param {Object}   root0.attributes           The block attributes.
 * @param {string}   root0.attributes.textAlign The text alignment for the block.
 * @param {Function} root0.setAttributes        Function to set block attributes.
 *
 * @return {JSX.Element} The rendered Edit component for the GatherPress Event Date block.
 *
 * @see {@link DateTimeRange} - Component for editing date and time range.
 * @see {@link AlignmentToolbar} - Toolbar for text alignment control.
 * @see {@link useBlockProps} - Custom hook for block props.
 * @see {@link displayDateTime} - Function for formatting and displaying date and time.
 */
const Edit = ({
  attributes: {
    textAlign
  },
  setAttributes
}) => {
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__.useBlockProps)({
    className: (0,clsx__WEBPACK_IMPORTED_MODULE_1__["default"])({
      [`has-text-align-${textAlign}`]: textAlign
    })
  });
  const {
    dateTimeStart,
    dateTimeEnd,
    timezone
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_5__.useSelect)(select => ({
    dateTimeStart: select('gatherpress/datetime').getDateTimeStart(),
    dateTimeEnd: select('gatherpress/datetime').getDateTimeEnd(),
    timezone: select('gatherpress/datetime').getTimezone()
  }), []);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
    ...blockProps,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__.BlockControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__.AlignmentToolbar, {
        value: textAlign,
        onChange: newAlign => setAttributes({
          textAlign: newAlign
        })
      })
    }), displayDateTime(dateTimeStart, dateTimeEnd, timezone), (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_8__.isGatherPressPostType)() && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__.InspectorControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.PanelBody, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_4__.__experimentalVStack, {
          spacing: 4,
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_components_DateTimeRange__WEBPACK_IMPORTED_MODULE_7__["default"], {})
        })
      })
    })]
  });
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




/**
 * Register the GatherPress Event Date block.
 *
 * This code registers the GatherPress Event Date block in the WordPress block editor.
 * It includes metadata from the 'block.json' file, defines the block styles with 'style.scss',
 * and specifies the 'edit' and 'save' components for the block. The 'edit' component is responsible
 * for the block's appearance and behavior in the editor, while the 'save' component defines how
 * the block should be rendered on the front end.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_2__, {
  edit: _edit__WEBPACK_IMPORTED_MODULE_1__["default"],
  save: () => null
});

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
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _helpers_event__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/event */ "./src/helpers/event.js");
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../helpers/datetime */ "./src/helpers/datetime.js");
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @wordpress/date */ "@wordpress/date");
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(_wordpress_date__WEBPACK_IMPORTED_MODULE_7__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__);
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
 * If the event has passed, it displays a notice using the hasEventPastNotice function.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */

const DateTimeEnd = () => {
  const {
    dateTimeEnd
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useSelect)(select => ({
    dateTimeEnd: select('gatherpress/datetime').getDateTimeEnd()
  }), []);
  const {
    setDateTimeEnd,
    setDateTimeStart
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useDispatch)('gatherpress/datetime');
  const settings = (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_7__.getSettings)();
  const is12HourTime = /a(?!\\)/i.test(settings.formats.time.toLowerCase().replace(/\\\\/g, '').split('').reverse().join(''));
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    setDateTimeEnd(moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeEnd, (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.getTimezone)()).format(_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.dateTimeDatabaseFormat));
    (0,_helpers_event__WEBPACK_IMPORTED_MODULE_5__.hasEventPastNotice)();
  });
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.PanelRow, {
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Flex, {
      direction: "column",
      gap: "1",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.FlexItem, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("h3", {
          style: {
            marginBottom: 0
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("label", {
            htmlFor: "gatherpress-datetime-end",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Date & time end', 'gatherpress')
          })
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.FlexItem, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Dropdown, {
          popoverProps: {
            placement: 'bottom-end'
          },
          renderToggle: ({
            isOpen,
            onToggle
          }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            id: "gatherpress-datetime-end",
            onClick: onToggle,
            "aria-expanded": isOpen,
            isLink: true,
            children: moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeEnd, (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.getTimezone)()).format((0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.dateTimeLabelFormat)())
          }),
          renderContent: () => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.DateTimePicker, {
            currentDate: dateTimeEnd,
            onChange: date => (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.updateDateTimeEnd)(date, setDateTimeEnd, setDateTimeStart),
            is12Hour: is12HourTime
          })
        })
      })]
    })
  });
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
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/date */ "@wordpress/date");
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_date__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
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
  const [dateTimeFormat, setDateTimeFormat] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(value);
  const input = document.querySelector(`[name="${name}"]`);
  input.addEventListener('input', e => {
    setDateTimeFormat(e.target.value);
  }, {
    once: true
  });
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.Fragment, {
    children: dateTimeFormat && (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_0__.format)(dateTimeFormat)
  });
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
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../helpers/datetime */ "./src/helpers/datetime.js");
/* harmony import */ var _components_DateTimeStart__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../components/DateTimeStart */ "./src/components/DateTimeStart.js");
/* harmony import */ var _components_DateTimeEnd__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../components/DateTimeEnd */ "./src/components/DateTimeEnd.js");
/* harmony import */ var _Timezone__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./Timezone */ "./src/components/Timezone.js");
/* harmony import */ var _components_Duration__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../components/Duration */ "./src/components/Duration.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__);
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
 * DateTimeRange component for GatherPress.
 *
 * This component manages the selection of a date and time range for events.
 * It includes DateTimeStart, DateTimeEnd, and Timezone components to allow users
 * to set the event's start date, end date, and timezone. The component pulls
 * these values from the state using WordPress data stores and subscribes to changes
 * via the `saveDateTime` function. On changes, the component updates the post meta
 * with the selected date and time values, formatted for the database.
 *
 * The component also handles the duration of the event, checking if the end time
 * matches a predefined duration option and updating the duration accordingly.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered DateTimeRange React component.
 */

const DateTimeRange = () => {
  const editPost = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)('core/editor').editPost;
  let dateTimeMetaData = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => select('core/editor').getEditedPostAttribute('meta')?.gatherpress_datetime);
  try {
    dateTimeMetaData = dateTimeMetaData ? JSON.parse(dateTimeMetaData) : {};
  } catch (e) {
    dateTimeMetaData = {};
  }
  const {
    dateTimeStart,
    dateTimeEnd,
    duration,
    timezone
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => ({
    dateTimeStart: select('gatherpress/datetime').getDateTimeStart(),
    dateTimeEnd: select('gatherpress/datetime').getDateTimeEnd(),
    duration: select('gatherpress/datetime').getDuration(),
    timezone: select('gatherpress/datetime').getTimezone()
  }), []);
  const {
    setDuration
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)('gatherpress/datetime');
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    const payload = JSON.stringify({
      ...dateTimeMetaData,
      ...{
        dateTimeStart: moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeStart, timezone).format(_helpers_datetime__WEBPACK_IMPORTED_MODULE_3__.dateTimeDatabaseFormat),
        dateTimeEnd: moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeEnd, timezone).format(_helpers_datetime__WEBPACK_IMPORTED_MODULE_3__.dateTimeDatabaseFormat),
        timezone
      }
    });
    const meta = {
      gatherpress_datetime: payload
    };
    editPost({
      meta
    });
  }, [dateTimeStart, dateTimeEnd, timezone, dateTimeMetaData, editPost, setDuration, duration]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("section", {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_components_DateTimeStart__WEBPACK_IMPORTED_MODULE_4__["default"], {})
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("section", {
      children: duration ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_components_Duration__WEBPACK_IMPORTED_MODULE_7__["default"], {}) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_components_DateTimeEnd__WEBPACK_IMPORTED_MODULE_5__["default"], {})
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("section", {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_Timezone__WEBPACK_IMPORTED_MODULE_6__["default"], {})
    })]
  });
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
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _helpers_event__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/event */ "./src/helpers/event.js");
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../helpers/datetime */ "./src/helpers/datetime.js");
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @wordpress/date */ "@wordpress/date");
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(_wordpress_date__WEBPACK_IMPORTED_MODULE_7__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__);
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
 * values are formatted and saved. The component subscribes to the saveDateTime
 * function and triggers the hasEventPastNotice function to handle any event past notices.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */

const DateTimeStart = () => {
  const {
    dateTimeStart,
    duration
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useSelect)(select => ({
    dateTimeStart: select('gatherpress/datetime').getDateTimeStart(),
    duration: select('gatherpress/datetime').getDuration()
  }), []);
  const {
    setDateTimeStart,
    setDateTimeEnd
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useDispatch)('gatherpress/datetime');
  const settings = (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_7__.getSettings)();
  const is12HourTime = /a(?!\\)/i.test(settings.formats.time.toLowerCase().replace(/\\\\/g, '').split('').reverse().join(''));
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    setDateTimeStart(moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeStart, (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.getTimezone)()).format(_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.dateTimeDatabaseFormat));
    if (duration) {
      setDateTimeEnd((0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.dateTimeOffset)(duration));
    }
    (0,_helpers_event__WEBPACK_IMPORTED_MODULE_5__.hasEventPastNotice)();
  }, [dateTimeStart, duration, setDateTimeStart, setDateTimeEnd]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.PanelRow, {
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Flex, {
      direction: "column",
      gap: "1",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.FlexItem, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("h3", {
          style: {
            marginBottom: 0
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("label", {
            htmlFor: "gatherpress-datetime-start",
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Date & time start', 'gatherpress')
          })
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.FlexItem, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Dropdown, {
          popoverProps: {
            placement: 'bottom-end'
          },
          renderToggle: ({
            isOpen,
            onToggle
          }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            id: "gatherpress-datetime-start",
            onClick: onToggle,
            "aria-expanded": isOpen,
            isLink: true,
            children: moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeStart, (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.getTimezone)()).format((0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.dateTimeLabelFormat)())
          }),
          renderContent: () => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.DateTimePicker, {
            currentDate: dateTimeStart,
            onChange: date => {
              (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_6__.updateDateTimeStart)(date, setDateTimeStart, setDateTimeEnd);
            },
            is12Hour: is12HourTime
          })
        })
      })]
    })
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (DateTimeStart);

/***/ }),

/***/ "./src/components/Duration.js":
/*!************************************!*\
  !*** ./src/components/Duration.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../helpers/datetime */ "./src/helpers/datetime.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);
/**
 * WordPress dependencies.
 */





/**
 * Duration component for GatherPress.
 *
 * This component allows users to select the duration of an event from predefined options.
 * It uses the `SelectControl` component to display a dropdown menu with duration options,
 * such as 1 hour, 1.5 hours, etc., as well as an option to set a custom end time.
 * The selected duration is managed through the WordPress data store and updated accordingly.
 *
 * When a duration is selected, the component calculates the new end time based on the
 * duration and updates the event's end date and time. It also updates the duration value
 * in the state.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered Duration React component.
 */

const Duration = () => {
  const {
    duration
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.useSelect)(select => ({
    duration: select('gatherpress/datetime').getDuration()
  }), []);
  const dispatch = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.useDispatch)();
  const {
    setDateTimeEnd,
    setDuration
  } = dispatch('gatherpress/datetime');
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Duration', 'gatherpress'),
    value: _helpers_datetime__WEBPACK_IMPORTED_MODULE_3__.durationOptions.some(option => option.value === duration) ? duration : false,
    options: _helpers_datetime__WEBPACK_IMPORTED_MODULE_3__.durationOptions,
    onChange: value => {
      value = 'false' === value ? false : parseFloat(value);
      if (value) {
        setDateTimeEnd((0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_3__.dateTimeOffset)(value));
      }
      setDuration(value);
    },
    __nexthasnomarginbottom: true
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Duration);

/***/ }),

/***/ "./src/components/Timezone.js":
/*!************************************!*\
  !*** ./src/components/Timezone.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../helpers/globals */ "./src/helpers/globals.js");
/* harmony import */ var _helpers_datetime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../helpers/datetime */ "./src/helpers/datetime.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);
/**
 * WordPress dependencies.
 */





/**
 * Internal dependencies.
 */



/**
 * Timezone component for GatherPress.
 *
 * This component allows users to select their preferred time zone from a list of choices.
 * It includes a SelectControl with options grouped by regions. The selected time zone is
 * stored in the state and updated via the setTimezone function.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */

const Timezone = () => {
  const {
    timezone
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => ({
    timezone: select('gatherpress/datetime').getTimezone()
  }), []);
  const {
    setTimezone
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useDispatch)('gatherpress/datetime');
  const choices = (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('misc.timezoneChoices');

  // Run only once.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    setTimezone((0,_helpers_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('eventDetails.dateTime.timezone'));
  }, [setTimezone]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.PanelRow, {
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.SelectControl, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Time Zone', 'gatherpress'),
      value: (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_5__.maybeConvertUtcOffsetForSelect)(timezone),
      onChange: value => {
        value = (0,_helpers_datetime__WEBPACK_IMPORTED_MODULE_5__.maybeConvertUtcOffsetForDatabase)(value);
        setTimezone(value);
        (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_4__.enableSave)();
      },
      __nexthasnomarginbottom: true,
      children: Object.keys(choices).map(group => {
        return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("optgroup", {
          label: group,
          children: Object.keys(choices[group]).map(item => {
            return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("option", {
              value: item,
              children: choices[group][item]
            }, item);
          })
        }, group);
      })
    })
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Timezone);

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
/* harmony export */   dateTimeOffset: () => (/* binding */ dateTimeOffset),
/* harmony export */   dateTimePreview: () => (/* binding */ dateTimePreview),
/* harmony export */   defaultDateTimeEnd: () => (/* binding */ defaultDateTimeEnd),
/* harmony export */   defaultDateTimeStart: () => (/* binding */ defaultDateTimeStart),
/* harmony export */   durationOptions: () => (/* binding */ durationOptions),
/* harmony export */   getDateTimeEnd: () => (/* binding */ getDateTimeEnd),
/* harmony export */   getDateTimeOffset: () => (/* binding */ getDateTimeOffset),
/* harmony export */   getDateTimeStart: () => (/* binding */ getDateTimeStart),
/* harmony export */   getTimezone: () => (/* binding */ getTimezone),
/* harmony export */   getUtcOffset: () => (/* binding */ getUtcOffset),
/* harmony export */   maybeConvertUtcOffsetForDatabase: () => (/* binding */ maybeConvertUtcOffsetForDatabase),
/* harmony export */   maybeConvertUtcOffsetForDisplay: () => (/* binding */ maybeConvertUtcOffsetForDisplay),
/* harmony export */   maybeConvertUtcOffsetForSelect: () => (/* binding */ maybeConvertUtcOffsetForSelect),
/* harmony export */   updateDateTimeEnd: () => (/* binding */ updateDateTimeEnd),
/* harmony export */   updateDateTimeStart: () => (/* binding */ updateDateTimeStart),
/* harmony export */   validateDateTimeEnd: () => (/* binding */ validateDateTimeEnd),
/* harmony export */   validateDateTimeStart: () => (/* binding */ validateDateTimeStart)
/* harmony export */ });
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! moment */ "moment");
/* harmony import */ var moment__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(moment__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _globals__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./globals */ "./src/helpers/globals.js");
/* harmony import */ var _components_DateTimePreview__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../components/DateTimePreview */ "./src/components/DateTimePreview.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
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
const defaultDateTimeStart = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(getTimezone()).add(1, 'day').set('hour', 18).set('minute', 0).set('second', 0).format(dateTimeDatabaseFormat);

/**
 * The default end date and time for an event.
 * It is calculated based on the default start date and time plus two hours in the application's timezone.
 *
 * @since 1.0.0
 *
 * @type {string} Formatted default end date and time in the application's timezone.
 */
const defaultDateTimeEnd = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(defaultDateTimeStart, getTimezone()).add(2, 'hours').format(dateTimeDatabaseFormat);

/**
 * Predefined duration options for event scheduling.
 *
 * This array contains a list of duration options in hours that can be selected
 * for an event. Each option includes a label for display and a corresponding
 * value representing the duration in hours. The last option allows the user
 * to set a custom end time by selecting `false`.
 *
 * @since 1.0.0
 *
 * @type {Array<Object>} durationOptions
 * @property {string}         label - The human-readable label for the duration option.
 * @property {number|boolean} value - The value representing the duration in hours, or `false` if a custom end time is to be set.
 */
const durationOptions = [{
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('1 hour', 'gatherpress'),
  value: 1
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('1.5 hours', 'gatherpress'),
  value: 1.5
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('2 hours', 'gatherpress'),
  value: 2
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('3 hours', 'gatherpress'),
  value: 3
}, {
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Set an end time…', 'gatherpress'),
  value: false
}];

/**
 * Calculates an offset in hours from the start date and time of an event.
 *
 * This function retrieves the event's start date and time, applies the provided
 * offset in hours, and returns the result formatted for database storage.
 *
 * @since 1.0.0
 *
 * @param {number} hours - The number of hours to offset from the event's start date and time.
 *
 * @return {string} The adjusted date and time formatted in a database-compatible format.
 */
function dateTimeOffset(hours) {
  return moment__WEBPACK_IMPORTED_MODULE_0___default().tz(getDateTimeStart(), getTimezone()).add(hours, 'hours').format(dateTimeDatabaseFormat);
}

/**
 * Retrieves the duration offset based on the end time of the event.
 *
 * This function checks the available duration options and compares
 * the offset value with the calculated end time of the event. If a
 * matching offset is found, it returns the corresponding value. If
 * no match is found, it returns false.
 *
 * @since 1.0.0
 *
 * @return {number|boolean} The matching duration value or false if no match is found.
 */
function getDateTimeOffset() {
  return durationOptions.find(option => dateTimeOffset(option.value) === getDateTimeEnd())?.value || false;
}

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
  const dateFormat = convertPHPToMomentFormat((0,_globals__WEBPACK_IMPORTED_MODULE_3__.getFromGlobal)('settings.dateFormat'));
  const timeFormat = convertPHPToMomentFormat((0,_globals__WEBPACK_IMPORTED_MODULE_3__.getFromGlobal)('settings.timeFormat'));
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
function getTimezone(timezone = (0,_globals__WEBPACK_IMPORTED_MODULE_3__.getFromGlobal)('eventDetails.dateTime.timezone')) {
  if (!!moment__WEBPACK_IMPORTED_MODULE_0___default().tz.zone(timezone)) {
    return timezone;
  }
  return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('GMT', 'gatherpress');
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
  timezone = getTimezone(timezone);
  if ((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('GMT', 'gatherpress') !== timezone) {
    return '';
  }
  const offset = (0,_globals__WEBPACK_IMPORTED_MODULE_3__.getFromGlobal)('eventDetails.dateTime.timezone');
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
  let dateTime = (0,_globals__WEBPACK_IMPORTED_MODULE_3__.getFromGlobal)('eventDetails.dateTime.datetime_start');
  dateTime = '' !== dateTime ? moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTime, getTimezone()).format(dateTimeDatabaseFormat) : defaultDateTimeStart;
  (0,_globals__WEBPACK_IMPORTED_MODULE_3__.setToGlobal)('eventDetails.dateTime.datetime_start', dateTime);
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
  let dateTime = (0,_globals__WEBPACK_IMPORTED_MODULE_3__.getFromGlobal)('eventDetails.dateTime.datetime_end');
  dateTime = '' !== dateTime ? moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTime, getTimezone()).format(dateTimeDatabaseFormat) : defaultDateTimeEnd;
  (0,_globals__WEBPACK_IMPORTED_MODULE_3__.setToGlobal)('eventDetails.dateTime.datetime_end', dateTime);
  return dateTime;
}

/**
 * Updates the start date and time for an event, performs validation, and triggers the save functionality.
 *
 * This function sets the new start date and time of the event, validates the input
 * to ensure it meets the required criteria, and updates the global state. It also
 * triggers a save action if the `enableSave` function is available. If a `setDateTimeStart`
 * callback is provided, it is invoked with the new date.
 *
 * @since 1.0.0
 *
 * @param {string}        date             - The new start date and time to be set in a valid format.
 * @param {Function|null} setDateTimeStart - Optional callback function to update the state or perform additional actions with the new start date.
 * @param {Function|null} setDateTimeEnd   - Optional callback function to update the end date, if validation requires an update.
 *
 * @return {void}
 */
function updateDateTimeStart(date, setDateTimeStart = null, setDateTimeEnd = null) {
  validateDateTimeStart(date, setDateTimeEnd);
  (0,_globals__WEBPACK_IMPORTED_MODULE_3__.setToGlobal)('eventDetails.dateTime.datetime_start', date);
  if ('function' === typeof setDateTimeStart) {
    setDateTimeStart(date);
  }
  (0,_globals__WEBPACK_IMPORTED_MODULE_3__.enableSave)();
}

/**
 * Updates the end date and time of the event and triggers necessary actions.
 *
 * This function sets the end date and time of the event to the specified value,
 * validates the input, and triggers additional actions such as updating the UI and
 * enabling save functionality. The `setDateTimeEnd` callback can be used to update
 * the UI with the new end date and time, if provided. Optionally, `setDateTimeStart`
 * can be used for validation against the start date and time.
 *
 * @since 1.0.0
 *
 * @param {string}        date             - The new end date and time in a valid format.
 * @param {Function|null} setDateTimeEnd   - Optional callback to update the UI with the new end date and time.
 * @param {Function|null} setDateTimeStart - Optional callback for validating the end date against the start date.
 *
 * @return {void}
 */
function updateDateTimeEnd(date, setDateTimeEnd = null, setDateTimeStart = null) {
  validateDateTimeEnd(date, setDateTimeStart);
  (0,_globals__WEBPACK_IMPORTED_MODULE_3__.setToGlobal)('eventDetails.dateTime.datetime_end', date);
  if (null !== setDateTimeEnd) {
    setDateTimeEnd(date);
  }
  (0,_globals__WEBPACK_IMPORTED_MODULE_3__.enableSave)();
}

/**
 * Validates the start date and time of the event and performs necessary adjustments if needed.
 *
 * This function compares the provided start date and time with the current end date
 * and time of the event. If the start date is greater than or equal to the end date,
 * it adjusts the end date to ensure a minimum two-hour duration from the start date.
 * If `setDateTimeEnd` is provided, it updates the end date accordingly.
 *
 * @since 1.0.0
 *
 * @param {string}        dateTimeStart  - The start date and time in a valid format.
 * @param {Function|null} setDateTimeEnd - Optional callback to update the end date and time.
 *
 * @return {void}
 */
function validateDateTimeStart(dateTimeStart, setDateTimeEnd = null) {
  const dateTimeEndNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default().tz((0,_globals__WEBPACK_IMPORTED_MODULE_3__.getFromGlobal)('eventDetails.dateTime.datetime_end'), getTimezone()).valueOf();
  const dateTimeStartNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeStart, getTimezone()).valueOf();
  if (dateTimeStartNumeric >= dateTimeEndNumeric) {
    const dateTimeEnd = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeStartNumeric, getTimezone()).add(2, 'hours').format(dateTimeDatabaseFormat);
    updateDateTimeEnd(dateTimeEnd, setDateTimeEnd);
  }
}

/**
 * Validates the end date and time of the event and performs necessary adjustments if needed.
 *
 * This function compares the provided end date and time with the current start date
 * and time of the event. If the end date is less than or equal to the start date,
 * it adjusts the start date to ensure a minimum two-hour duration from the end date.
 * If `setDateTimeStart` is provided, it updates the start date accordingly.
 *
 * @since 1.0.0
 *
 * @param {string}        dateTimeEnd      - The end date and time in a valid format.
 * @param {Function|null} setDateTimeStart - Optional callback to update the start date and time.
 *
 * @return {void}
 */
function validateDateTimeEnd(dateTimeEnd, setDateTimeStart = null) {
  const dateTimeStartNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default().tz((0,_globals__WEBPACK_IMPORTED_MODULE_3__.getFromGlobal)('eventDetails.dateTime.datetime_start'), getTimezone()).valueOf();
  const dateTimeEndNumeric = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeEnd, getTimezone()).valueOf();
  if (dateTimeEndNumeric <= dateTimeStartNumeric) {
    const dateTimeStart = moment__WEBPACK_IMPORTED_MODULE_0___default().tz(dateTimeEndNumeric, getTimezone()).subtract(2, 'hours').format(dateTimeDatabaseFormat);
    updateDateTimeStart(dateTimeStart, setDateTimeStart);
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
  return String(format).split('').map((chr, index, elements) => {
    // Allow the format string to contain escaped chars, like ES or DE needs
    const last = elements[index - 1];
    if (chr in replacements && last !== '\\') {
      return replacements[chr];
    }
    return chr;
  }).join('');
}

/**
 * DateTime Preview Initialization
 *
 * This script initializes the DateTime Preview functionality for all elements
 * with the attribute 'data-gatherpress_component_name' set to 'datetime-preview'.
 * It iterates through all matching elements and initializes a DateTimePreview component
 * with the attributes provided in the 'data-gatherpress_component_attrs' attribute.
 *
 * @since 1.0.0
 */
function dateTimePreview() {
  // Select all elements with the attribute 'data-gatherpress_component_name' set to 'datetime-preview'.
  const dateTimePreviewContainers = document.querySelectorAll(`[data-gatherpress_component_name="datetime-preview"]`);

  // Iterate through each matched element and initialize DateTimePreview component.
  for (let i = 0; i < dateTimePreviewContainers.length; i++) {
    // Parse attributes from the 'data-gatherpress_component_attrs' attribute.
    const attrs = JSON.parse(dateTimePreviewContainers[i].dataset.gatherpress_component_attrs);

    // Create a root element and render the DateTimePreview component with the parsed attributes.
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.createRoot)(dateTimePreviewContainers[i]).render( /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_components_DateTimePreview__WEBPACK_IMPORTED_MODULE_4__["default"], {
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
/* harmony export */   triggerEventCommunication: () => (/* binding */ triggerEventCommunication)
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
 * It returns `true` if the current post type is 'gatherpress_event', indicating that the post is an event,
 * and `false` otherwise.
 *
 * @since 1.0.0
 *
 * @return {boolean} True if the current post type is 'gatherpress_event', false otherwise.
 */
function isEventPostType() {
  return 'gatherpress_event' === (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor')?.getCurrentPostType();
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
  const dateTimeEnd = moment__WEBPACK_IMPORTED_MODULE_0___default().tz((0,_globals__WEBPACK_IMPORTED_MODULE_4__.getFromGlobal)('eventDetails.dateTime.datetime_end'), (0,_datetime__WEBPACK_IMPORTED_MODULE_3__.getTimezone)());
  return 'gatherpress_event' === (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor')?.getCurrentPostType() && moment__WEBPACK_IMPORTED_MODULE_0___default().tz((0,_datetime__WEBPACK_IMPORTED_MODULE_3__.getTimezone)()).valueOf() > dateTimeEnd.valueOf();
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
  const id = 'gatherpress_event_past';
  const notices = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.dispatch)('core/notices');
  notices.removeNotice(id);
  if (hasEventPast()) {
    notices.createNotice('warning', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('This event has already passed.', 'gatherpress'), {
      id,
      isDismissible: false
    });
  }
}

/**
 * Flag to prevent multiple event communication notices.
 *
 * @type {boolean}
 */
let isEventCommunicationNoticeCreated = false;

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
function triggerEventCommunication() {
  const id = 'gatherpress_event_communication';
  const notices = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.dispatch)('core/notices');
  const isSavingPost = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor').isSavingPost();
  const isAutosavingPost = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor').isAutosavingPost();

  // Only proceed if a save is in progress and it's not an autosave.
  if ('publish' === (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.select)('core/editor').getEditedPostAttribute('status') && isEventPostType() && isSavingPost && !isAutosavingPost && !hasEventPast() && !isEventCommunicationNoticeCreated) {
    // Mark notice as created.
    isEventCommunicationNoticeCreated = true;

    // Remove any previous notices with the same ID.
    notices.removeNotice(id);

    // Create a new notice with an action.
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

  // Reset the flag after the save operation completes.
  if (!isSavingPost) {
    isEventCommunicationNoticeCreated = false;
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
/* harmony export */   isGatherPressPostType: () => (/* binding */ isGatherPressPostType),
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
 * Checks if the current post type is a GatherPress event or venue.
 *
 * This function determines if the post type being edited in the WordPress block editor
 * is either 'gatherpress_event' or 'gatherpress_venue', which are custom post types
 * related to GatherPress. It is used to ensure that specific actions or functionality
 * are applied only to these post types.
 *
 * @since 1.0.0
 *
 * @return {boolean} True if the current post type is 'gatherpress_event' or 'gatherpress_venue', false otherwise.
 */
function isGatherPressPostType() {
  const postType = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.select)('core/editor')?.getCurrentPostType();
  return 'gatherpress_event' === postType || 'gatherpress_venue' === postType;
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

/***/ "./src/blocks/event-date/style.scss":
/*!******************************************!*\
  !*** ./src/blocks/event-date/style.scss ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["ReactJSXRuntime"];

/***/ }),

/***/ "moment":
/*!*************************!*\
  !*** external "moment" ***!
  \*************************/
/***/ ((module) => {

module.exports = window["moment"];

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

/***/ }),

/***/ "./node_modules/clsx/dist/clsx.mjs":
/*!*****************************************!*\
  !*** ./node_modules/clsx/dist/clsx.mjs ***!
  \*****************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   clsx: () => (/* binding */ clsx),
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
function r(e){var t,f,n="";if("string"==typeof e||"number"==typeof e)n+=e;else if("object"==typeof e)if(Array.isArray(e)){var o=e.length;for(t=0;t<o;t++)e[t]&&(f=r(e[t]))&&(n&&(n+=" "),n+=f)}else for(f in e)e[f]&&(n&&(n+=" "),n+=f);return n}function clsx(){for(var e,t,f=0,n="",o=arguments.length;f<o;f++)(e=arguments[f])&&(t=r(e))&&(n&&(n+=" "),n+=t);return n}/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (clsx);

/***/ }),

/***/ "./src/blocks/event-date/block.json":
/*!******************************************!*\
  !*** ./src/blocks/event-date/block.json ***!
  \******************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"gatherpress/event-date","version":"2.0.0","title":"Event Date","category":"gatherpress","icon":"clock","example":{"viewportWidth":350},"description":"Displays the date and time for an event.","attributes":{"eventEnd":{"type":"string"},"eventStart":{"type":"string"},"textAlign":{"type":"string"}},"supports":{"html":false,"color":{"gradients":true,"link":true,"__experimentalDefaultControls":{"background":true,"text":true,"link":true}},"spacing":{"margin":true,"padding":true},"typography":{"fontSize":true,"lineHeight":true,"__experimentalFontFamily":true,"__experimentalFontWeight":true,"__experimentalFontStyle":true,"__experimentalTextTransform":true,"__experimentalTextDecoration":true,"__experimentalLetterSpacing":true,"__experimentalDefaultControls":{"fontSize":true}},"interactivity":{"clientNavigation":true},"__experimentalBorder":{"radius":true,"color":true,"width":true,"style":true,"__experimentalDefaultControls":{"radius":true,"color":true,"width":true,"style":true}}},"textdomain":"gatherpress","editorScript":"file:./index.js","style":"file:./style-index.css","render":"file:./render.php"}');

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