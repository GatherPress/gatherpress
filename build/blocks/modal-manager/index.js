(()=>{"use strict";const e=window.wp.blocks,s=window.wp.blockEditor,t=window.ReactJSXRuntime,r=JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"gatherpress/modal-manager","version":"1.0.0","title":"Modal Manager","category":"gatherpress","icon":"external","example":{},"description":"Manage modals and their triggers with ease.","attributes":{},"supports":{"html":false,"interactivity":true},"textdomain":"gatherpress","editorScript":"file:./index.js","viewScriptModule":"file:./view.js"}');(0,e.registerBlockType)(r,{edit:()=>{const e=(0,s.useBlockProps)();return(0,t.jsx)("div",{...e,children:(0,t.jsx)(s.InnerBlocks,{template:[["gatherpress/modal",{},[["gatherpress/modal-content",{}]]]]})})},save:()=>(0,t.jsx)("div",{...s.useBlockProps.save(),children:(0,t.jsx)(s.InnerBlocks.Content,{})})})})();