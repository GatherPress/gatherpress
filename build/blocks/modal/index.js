(()=>{"use strict";var e,t={7110:()=>{const e=window.wp.blocks,t=window.wp.blockEditor,r=window.wp.data,s=window.wp.i18n,a=window.wp.components,o=window.ReactJSXRuntime,n=JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"gatherpress/modal","version":"1.0.0","title":"Modal","parent":["gatherpress/modal-manager"],"category":"gatherpress","icon":"external","example":{},"description":"Enables members to easily confirm their attendance for an event.","attributes":{"style":{"type":"object","default":{"color":{"background":"rgba(0, 0, 0, 0.5)"}}},"zIndex":{"type":"number","default":1000}},"supports":{"html":false,"color":{"gradients":true,"__experimentalDefaultControls":{"background":true}}},"allowedBlocks":["gatherpress/modal-content"],"textdomain":"gatherpress","editorScript":"file:./index.js","style":"file:./style-index.css"}');(0,e.registerBlockType)(n,{edit:({attributes:e,setAttributes:n,clientId:l,isSelected:i})=>{const c=(0,r.useSelect)((e=>e(t.store).hasSelectedInnerBlock(l,!0)),[l]),d=(0,t.useBlockProps)({style:{display:i||c?"block":"none",maxWidth:"none"}}),{zIndex:p,metadata:h={}}=e,g=(0,r.select)("core/block-editor").getBlockParents(l,{levels:1})?.[0];return(0,o.jsxs)(o.Fragment,{children:[(0,o.jsx)(t.InspectorControls,{children:(0,o.jsxs)(a.PanelBody,{title:(0,s.__)("Modal Settings","gatherpress"),children:[(0,o.jsx)(a.TextControl,{label:(0,s.__)("Modal Name","gatherpress"),value:h.name||(0,s.__)("Modal","gatherpress"),onChange:e=>{n({metadata:{...h,name:e}})},help:(0,s.__)("Set a unique name for this modal. This will be used as the aria-label.","gatherpress")}),(0,o.jsx)(a.RangeControl,{label:(0,s.__)("Z-Index","gatherpress"),value:p,onChange:e=>n({zIndex:e}),min:0,max:9999,step:1,help:(0,s.__)("Set the layering position of the modal.","gatherpress")}),(0,o.jsx)(a.Button,{variant:"secondary",onClick:()=>{g&&(0,r.dispatch)("core/block-editor").selectBlock(g)},children:(0,s.__)("Back to Modal Manager","gatherpress")})]})}),(0,o.jsx)("div",{...d,children:(0,o.jsx)(t.InnerBlocks,{template:[["gatherpress/modal-content",{}]]})})]})},save:()=>(0,o.jsx)("div",{...t.useBlockProps.save(),children:(0,o.jsx)(t.InnerBlocks.Content,{})})})}},r={};function s(e){var a=r[e];if(void 0!==a)return a.exports;var o=r[e]={exports:{}};return t[e](o,o.exports,s),o.exports}s.m=t,e=[],s.O=(t,r,a,o)=>{if(!r){var n=1/0;for(d=0;d<e.length;d++){for(var[r,a,o]=e[d],l=!0,i=0;i<r.length;i++)(!1&o||n>=o)&&Object.keys(s.O).every((e=>s.O[e](r[i])))?r.splice(i--,1):(l=!1,o<n&&(n=o));if(l){e.splice(d--,1);var c=a();void 0!==c&&(t=c)}}return t}o=o||0;for(var d=e.length;d>0&&e[d-1][2]>o;d--)e[d]=e[d-1];e[d]=[r,a,o]},s.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={2310:0,8170:0};s.O.j=t=>0===e[t];var t=(t,r)=>{var a,o,[n,l,i]=r,c=0;if(n.some((t=>0!==e[t]))){for(a in l)s.o(l,a)&&(s.m[a]=l[a]);if(i)var d=i(s)}for(t&&t(r);c<n.length;c++)o=n[c],s.o(e,o)&&e[o]&&e[o][0](),e[o]=0;return s.O(d)},r=globalThis.webpackChunkgatherpress=globalThis.webpackChunkgatherpress||[];r.forEach(t.bind(null,0)),r.push=t.bind(null,r.push.bind(r))})();var a=s.O(void 0,[8170],(()=>s(7110)));a=s.O(a)})();