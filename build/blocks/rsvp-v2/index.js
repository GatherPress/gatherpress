/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/blocks/rsvp-v2/edit.js":
/*!************************************!*\
  !*** ./src/blocks/rsvp-v2/edit.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _templates__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./templates */ "./src/blocks/rsvp-v2/templates.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__);
/**
 * WordPress dependencies.
 */







/**
 * Internal dependencies.
 */


/**
 * Helper function to convert a template to blocks.
 *
 * @param {Array} template The block template structure.
 * @return {Array} Array of blocks created from the template.
 */

function templateToBlocks(template) {
  return template.map(([name, attributes, innerBlocks]) => {
    return (0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_5__.createBlock)(name, attributes, templateToBlocks(innerBlocks || []));
  });
}

/**
 * Edit component for the GatherPress RSVP block.
 *
 * @param {Object}   props               The props passed to the component.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 * @param {string}   props.clientId      The unique ID of the block instance.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered edit interface for the RSVP block.
 */
const Edit = ({
  attributes,
  setAttributes,
  clientId
}) => {
  const {
    serializedInnerBlocks = '{}',
    selectedStatus
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.useBlockProps)();
  const {
    replaceInnerBlocks
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useDispatch)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.store);

  // Get the current inner blocks
  const innerBlocks = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useSelect)(select => select(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.store).getBlocks(clientId), [clientId]);

  // Save the provided inner blocks to the serializedInnerBlocks attribute
  const saveInnerBlocks = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useCallback)((state, newState, blocks) => {
    const currentSerializedBlocks = JSON.parse(serializedInnerBlocks || '{}');

    // Encode the serialized content for safe use in HTML attributes
    const sanitizedSerialized = (0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_5__.serialize)(blocks);
    const updatedBlocks = {
      ...currentSerializedBlocks,
      [state]: sanitizedSerialized
    };
    delete updatedBlocks[newState];
    setAttributes({
      serializedInnerBlocks: JSON.stringify(updatedBlocks)
    });
  }, [serializedInnerBlocks, setAttributes]);

  // Load inner blocks for a given state
  const loadInnerBlocksForState = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useCallback)(state => {
    const savedBlocks = JSON.parse(serializedInnerBlocks || '{}')[state];
    if (savedBlocks && savedBlocks.length > 0) {
      replaceInnerBlocks(clientId, (0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_5__.parse)(savedBlocks, {}));
    }
  }, [clientId, replaceInnerBlocks, serializedInnerBlocks]);

  // Handle status change: save current inner blocks and load new ones
  const handleStatusChange = newStatus => {
    loadInnerBlocksForState(newStatus); // Load blocks for the new state
    setAttributes({
      selectedStatus: newStatus
    }); // Update the state
    saveInnerBlocks(selectedStatus, newStatus, innerBlocks); // Save current inner blocks before switching state
  };

  // Hydrate inner blocks for all statuses if not set
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    const hydrateInnerBlocks = () => {
      const currentSerializedBlocks = JSON.parse(serializedInnerBlocks || '{}');
      const updatedBlocks = Object.keys(_templates__WEBPACK_IMPORTED_MODULE_6__["default"]).reduce((updatedSerializedBlocks, templateKey) => {
        if (currentSerializedBlocks[templateKey]) {
          updatedSerializedBlocks[templateKey] = currentSerializedBlocks[templateKey];
          return updatedSerializedBlocks;
        }
        if (templateKey !== selectedStatus) {
          const blocks = templateToBlocks(_templates__WEBPACK_IMPORTED_MODULE_6__["default"][templateKey]);
          updatedSerializedBlocks[templateKey] = (0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_5__.serialize)(blocks);
        }
        return updatedSerializedBlocks;
      }, {
        ...currentSerializedBlocks
      });
      setAttributes({
        serializedInnerBlocks: JSON.stringify(updatedBlocks)
      });
    };
    hydrateInnerBlocks();
  }, [serializedInnerBlocks, setAttributes, selectedStatus]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.InspectorControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.PanelBody, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('RSVP Status', 'gatherpress'),
          value: selectedStatus,
          options: [{
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No Status (User has not responded)', 'gatherpress'),
            value: 'no_status'
          }, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Attending (User is confirmed)', 'gatherpress'),
            value: 'attending'
          }, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Waiting List (Pending confirmation)', 'gatherpress'),
            value: 'waiting_list'
          }, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Not Attending (User declined)', 'gatherpress'),
            value: 'not_attending'
          }, {
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Past Event (Event has already occurred)', 'gatherpress'),
            value: 'past'
          }],
          onChange: handleStatusChange
        })
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
      ...blockProps,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.InnerBlocks, {
        template: _templates__WEBPACK_IMPORTED_MODULE_6__["default"][selectedStatus]
      })
    })]
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Edit);

/***/ }),

/***/ "./src/blocks/rsvp-v2/index.js":
/*!*************************************!*\
  !*** ./src/blocks/rsvp-v2/index.js ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/blocks/rsvp-v2/edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./block.json */ "./src/blocks/rsvp-v2/block.json");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./style.scss */ "./src/blocks/rsvp-v2/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
/**
 * WordPress dependencies.
 */


/**
 * Internal dependencies.
 */




/**
 * Edit component for the GatherPress RSVP block.
 *
 * This component renders the edit view of the GatherPress RSVP block.
 * It provides an interface for users to RSVP to an event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */

(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_3__, {
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"],
  save: () => {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
      ..._wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps.save(),
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InnerBlocks.Content, {})
    });
  }
});

/***/ }),

/***/ "./src/blocks/rsvp-v2/templates.js":
/*!*****************************************!*\
  !*** ./src/blocks/rsvp-v2/templates.js ***!
  \*****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _templates_attending__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./templates/attending */ "./src/blocks/rsvp-v2/templates/attending.js");
/* harmony import */ var _templates_no_status__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./templates/no-status */ "./src/blocks/rsvp-v2/templates/no-status.js");
/* harmony import */ var _templates_not_attending__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./templates/not-attending */ "./src/blocks/rsvp-v2/templates/not-attending.js");
/* harmony import */ var _templates_waiting_list__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./templates/waiting-list */ "./src/blocks/rsvp-v2/templates/waiting-list.js");
/* harmony import */ var _templates_past__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./templates/past */ "./src/blocks/rsvp-v2/templates/past.js");
/**
 * Internal dependencies.
 */






/**
 * RSVP block templates mapped by status.
 *
 * This file aggregates all RSVP templates into a single object, allowing
 * easy access to templates based on RSVP status.
 *
 * @type {Object}
 */
const TEMPLATES = {
  no_status: _templates_no_status__WEBPACK_IMPORTED_MODULE_1__["default"],
  attending: _templates_attending__WEBPACK_IMPORTED_MODULE_0__["default"],
  waiting_list: _templates_waiting_list__WEBPACK_IMPORTED_MODULE_3__["default"],
  not_attending: _templates_not_attending__WEBPACK_IMPORTED_MODULE_2__["default"],
  past: _templates_past__WEBPACK_IMPORTED_MODULE_4__["default"]
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (TEMPLATES);

/***/ }),

/***/ "./src/blocks/rsvp-v2/templates/attending.js":
/*!***************************************************!*\
  !*** ./src/blocks/rsvp-v2/templates/attending.js ***!
  \***************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/**
 * WordPress dependencies.
 */

const ATTENDING = [['gatherpress/modal-manager', {}, [['core/buttons', {
  align: 'center',
  layout: {
    type: 'flex',
    justifyContent: 'center'
  },
  metadata: {
    name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('RSVP Buttons', 'gatherpress')
  }
}, [['core/button', {
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Edit RSVP', 'gatherpress'),
  tagName: 'button',
  className: 'gatherpress--open-modal'
}]]], ['core/group', {
  style: {
    spacing: {
      blockGap: 'var:preset|spacing|20'
    }
  },
  layout: {
    type: 'flex',
    flexWrap: 'nowrap'
  }
}, [['gatherpress/icon', {
  icon: 'yes-alt',
  iconSize: 24
}], ['core/paragraph', {
  style: {
    spacing: {
      margin: {
        top: '0'
      },
      padding: {
        top: '0'
      }
    }
  },
  content: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('<strong>Attending</strong>', 'gatherpress')
}]]], ['gatherpress/modal', {
  className: 'gatherpress--is-rsvp-modal',
  metadata: {
    name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('RSVP Modal', 'gatherpress')
  }
}, [['gatherpress/modal-content', {}, [['core/paragraph', {
  style: {
    spacing: {
      margin: {
        top: '0'
      },
      padding: {
        top: '0'
      }
    }
  },
  content: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("<strong>You're Attending</strong>", 'gatherpress')
}], ['core/paragraph', {
  content: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('To set or change your attending status, simply click the <strong>Not Attending</strong> button below.', 'gatherpress')
}], ['core/buttons', {
  align: 'left',
  layout: {
    type: 'flex',
    justifyContent: 'flex-start'
  },
  style: {
    spacing: {
      margin: {
        bottom: '0'
      },
      padding: {
        bottom: '0'
      }
    }
  }
}, [['core/button', {
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Not Attending', 'gatherpress'),
  tagName: 'button',
  className: 'gatherpress--update-rsvp'
}], ['core/button', {
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Close', 'gatherpress'),
  tagName: 'button',
  className: 'is-style-outline gatherpress--close-modal'
}]]]]]]]]]];
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ATTENDING);

/***/ }),

/***/ "./src/blocks/rsvp-v2/templates/no-status.js":
/*!***************************************************!*\
  !*** ./src/blocks/rsvp-v2/templates/no-status.js ***!
  \***************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _helpers_globals__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../helpers/globals */ "./src/helpers/globals.js");
/**
 * WordPress dependencies.
 */


/**
 * Internal dependencies.
 */

const NO_STATUS = [['gatherpress/modal-manager', {}, [['core/buttons', {
  align: 'center',
  layout: {
    type: 'flex',
    justifyContent: 'center'
  },
  metadata: {
    name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('RSVP Buttons', 'gatherpress')
  }
}, [['core/button', {
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('RSVP', 'gatherpress'),
  tagName: 'button',
  className: 'gatherpress--open-modal'
}]]], ['gatherpress/modal', {
  className: 'gatherpress--is-rsvp-modal',
  metadata: {
    name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('RSVP Modal', 'gatherpress')
  }
}, [['gatherpress/modal-content', {}, [['core/paragraph', {
  style: {
    spacing: {
      margin: {
        top: '0'
      },
      padding: {
        top: '0'
      }
    }
  },
  content: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('<strong>RSVP to this event</strong>', 'gatherpress')
}], ['core/paragraph', {
  content: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('To set or change your attending status, simply click the <strong>Attending</strong> button below.', 'gatherpress')
}], ['core/buttons', {
  align: 'left',
  layout: {
    type: 'flex',
    justifyContent: 'flex-start'
  },
  style: {
    spacing: {
      margin: {
        bottom: '0'
      },
      padding: {
        bottom: '0'
      }
    }
  }
}, [['core/button', {
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Attending', 'gatherpress'),
  tagName: 'button',
  className: 'gatherpress--update-rsvp'
}], ['core/button', {
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Close', 'gatherpress'),
  tagName: 'button',
  className: 'is-style-outline gatherpress--close-modal'
}]]]]]]], ['gatherpress/modal', {
  className: 'gatherpress--is-login-modal',
  metadata: {
    name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Login Modal', 'gatherpress')
  }
}, [['gatherpress/modal-content', {}, [['core/paragraph', {
  style: {
    spacing: {
      margin: {
        top: '0'
      },
      padding: {
        top: '0'
      }
    }
  },
  content: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('<strong>Login Required</strong>', 'gatherpress')
}], ['core/paragraph', {
  content: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.sprintf)(/* translators: %s: Login URL. */
  (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('This action requires an account. Please <a href="%s">Login</a> to RSVP to this event.', 'gatherpress'), (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_1__.getFromGlobal)('urls.loginUrl')),
  className: 'gatherpress--has-login-url'
}], ['core/paragraph', {
  content: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.sprintf)(/* translators: %s: Registration URL. */
  (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Don\'t have an account? <a href="%s">Register here</a> to create one.', 'gatherpress'), (0,_helpers_globals__WEBPACK_IMPORTED_MODULE_1__.getFromGlobal)('urls.registrationUrl')),
  className: 'gatherpress--has-registration-url'
}], ['core/buttons', {
  align: 'left',
  layout: {
    type: 'flex',
    justifyContent: 'flex-start'
  },
  style: {
    spacing: {
      margin: {
        bottom: '0'
      },
      padding: {
        bottom: '0'
      }
    }
  }
}, [['core/button', {
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Close', 'gatherpress'),
  tagName: 'button',
  className: 'gatherpress--close-modal'
}]]]]]]]]]];
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (NO_STATUS);

/***/ }),

/***/ "./src/blocks/rsvp-v2/templates/not-attending.js":
/*!*******************************************************!*\
  !*** ./src/blocks/rsvp-v2/templates/not-attending.js ***!
  \*******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/**
 * WordPress dependencies.
 */

const NOT_ATTENDING = [['gatherpress/modal-manager', {}, [['core/buttons', {
  align: 'center',
  layout: {
    type: 'flex',
    justifyContent: 'center'
  },
  metadata: {
    name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('RSVP Buttons', 'gatherpress')
  }
}, [['core/button', {
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Edit RSVP', 'gatherpress'),
  tagName: 'button',
  className: 'gatherpress--open-modal'
}]]], ['core/group', {
  style: {
    spacing: {
      blockGap: 'var:preset|spacing|20'
    }
  },
  layout: {
    type: 'flex',
    flexWrap: 'nowrap'
  }
}, [['gatherpress/icon', {
  icon: 'dismiss',
  iconSize: 24
}], ['core/paragraph', {
  style: {
    spacing: {
      margin: {
        top: '0'
      },
      padding: {
        top: '0'
      }
    }
  },
  content: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('<strong>Not Attending</strong>', 'gatherpress')
}]]], ['gatherpress/modal', {
  className: 'gatherpress--is-rsvp-modal',
  metadata: {
    name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('RSVP Modal', 'gatherpress')
  }
}, [['gatherpress/modal-content', {}, [['core/paragraph', {
  style: {
    spacing: {
      margin: {
        top: '0'
      },
      padding: {
        top: '0'
      }
    }
  },
  content: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("<strong>You're Not Attending</strong>", 'gatherpress')
}], ['core/paragraph', {
  content: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('To set or change your attending status, simply click the <strong>Attending</strong> button below.', 'gatherpress')
}], ['core/buttons', {
  align: 'left',
  layout: {
    type: 'flex',
    justifyContent: 'flex-start'
  },
  style: {
    spacing: {
      margin: {
        bottom: '0'
      },
      padding: {
        bottom: '0'
      }
    }
  }
}, [['core/button', {
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Attending', 'gatherpress'),
  tagName: 'button',
  className: 'gatherpress--update-rsvp'
}], ['core/button', {
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Close', 'gatherpress'),
  tagName: 'button',
  className: 'is-style-outline gatherpress--close-modal'
}]]]]]]]]]];
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (NOT_ATTENDING);

/***/ }),

/***/ "./src/blocks/rsvp-v2/templates/past.js":
/*!**********************************************!*\
  !*** ./src/blocks/rsvp-v2/templates/past.js ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/**
 * WordPress dependencies.
 */

const PAST = [['core/buttons', {
  align: 'center',
  layout: {
    type: 'flex',
    justifyContent: 'center'
  },
  metadata: {
    name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('RSVP Buttons', 'gatherpress')
  }
}, [['core/button', {
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Past Event', 'gatherpress'),
  tagName: 'button',
  className: 'gatherpress--is-disabled'
}]]]];
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (PAST);

/***/ }),

/***/ "./src/blocks/rsvp-v2/templates/waiting-list.js":
/*!******************************************************!*\
  !*** ./src/blocks/rsvp-v2/templates/waiting-list.js ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/**
 * WordPress dependencies.
 */

const WAITING_LIST = [['gatherpress/modal-manager', {}, [['core/buttons', {
  align: 'center',
  layout: {
    type: 'flex',
    justifyContent: 'center'
  },
  metadata: {
    name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('RSVP Buttons', 'gatherpress')
  }
}, [['core/button', {
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Edit RSVP', 'gatherpress'),
  tagName: 'button',
  className: 'gatherpress--open-modal'
}]]], ['core/group', {
  style: {
    spacing: {
      blockGap: 'var:preset|spacing|20'
    }
  },
  layout: {
    type: 'flex',
    flexWrap: 'nowrap'
  }
}, [['gatherpress/icon', {
  icon: 'editor-help',
  iconSize: 24
}], ['core/paragraph', {
  style: {
    spacing: {
      margin: {
        top: '0'
      },
      padding: {
        top: '0'
      }
    }
  },
  content: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('<strong>Waiting List</strong>', 'gatherpress')
}]]], ['gatherpress/modal', {
  className: 'gatherpress--is-rsvp-modal',
  metadata: {
    name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('RSVP Modal', 'gatherpress')
  }
}, [['gatherpress/modal-content', {}, [['core/paragraph', {
  style: {
    spacing: {
      margin: {
        top: '0'
      },
      padding: {
        top: '0'
      }
    }
  },
  content: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)("<strong>You're Wait Listed</strong>", 'gatherpress')
}], ['core/paragraph', {
  content: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('To set or change your attending status, simply click the <strong>Not Attending</strong> button below.', 'gatherpress')
}], ['core/buttons', {
  align: 'left',
  layout: {
    type: 'flex',
    justifyContent: 'flex-start'
  },
  style: {
    spacing: {
      margin: {
        bottom: '0'
      },
      padding: {
        bottom: '0'
      }
    }
  }
}, [['core/button', {
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Not Attending', 'gatherpress'),
  tagName: 'button',
  className: 'gatherpress--update-rsvp'
}], ['core/button', {
  text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Close', 'gatherpress'),
  tagName: 'button',
  className: 'is-style-outline gatherpress--close-modal'
}]]]]]]]]]];
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (WAITING_LIST);

/***/ }),

/***/ "./src/helpers/globals.js":
/*!********************************!*\
  !*** ./src/helpers/globals.js ***!
  \********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getFromGlobal: () => (/* binding */ getFromGlobal),
/* harmony export */   safeHTML: () => (/* binding */ safeHTML),
/* harmony export */   setToGlobal: () => (/* binding */ setToGlobal)
/* harmony export */ });
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

/**
 * Strip <script> tags and "on*" attributes from HTML to sanitize it.
 *
 * This function removes <script> elements and any attributes starting with "on" (e.g., event handlers)
 * to mitigate potential XSS vulnerabilities. It is a similar implementation to WordPress Core's `safeHTML` function
 * in `dom.js`, tailored for use when the Core implementation is unavailable or unnecessary.
 *
 * @since 1.0.0
 *
 * @param {string} html - The raw HTML string to sanitize.
 *
 * @return {string} The sanitized HTML string.
 */
function safeHTML(html) {
  const {
    body
  } = document.implementation.createHTMLDocument('');
  body.innerHTML = html;
  const elements = body.getElementsByTagName('*');
  let elementIndex = elements.length;
  while (elementIndex--) {
    const element = elements[elementIndex];
    if ('SCRIPT' === element.tagName) {
      if (element.parentNode) {
        element.parentNode.removeChild(element);
      }
    } else {
      let attributeIndex = element.attributes.length;
      while (attributeIndex--) {
        const {
          name: key
        } = element.attributes[attributeIndex];
        if (key.startsWith('on')) {
          element.removeAttribute(key);
        }
      }
    }
  }
  return body.innerHTML;
}

/***/ }),

/***/ "./src/blocks/rsvp-v2/style.scss":
/*!***************************************!*\
  !*** ./src/blocks/rsvp-v2/style.scss ***!
  \***************************************/
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

/***/ "./src/blocks/rsvp-v2/block.json":
/*!***************************************!*\
  !*** ./src/blocks/rsvp-v2/block.json ***!
  \***************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"gatherpress/rsvp-v2","version":"2.0.0","title":"RSVP V2","category":"gatherpress","icon":"insert","example":{},"description":"Enables members to easily confirm their attendance for an event.","attributes":{"serializedInnerBlocks":{"type":"string","default":"[]"},"selectedStatus":{"type":"string","default":"no_status"}},"supports":{"html":false,"interactivity":true},"textdomain":"gatherpress","editorScript":"file:./index.js","style":"file:./style-index.css","viewScriptModule":"file:./view.js"}');

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
/******/ 			"blocks/rsvp-v2/index": 0,
/******/ 			"blocks/rsvp-v2/style-index": 0
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
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["blocks/rsvp-v2/style-index"], () => (__webpack_require__("./src/blocks/rsvp-v2/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=index.js.map