(()=>{"use strict";var e,t={8036:()=>{const e=window.wp.blocks,t=window.wp.i18n,r=window.wp.blockEditor,s=(window.wp.element,window.wp.data),a=window.ReactJSXRuntime,i=JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"gatherpress/rsvp-guest-count-input","version":"1.0.0","title":"RSVP Guest Count Input","description":"Allows members to specify the number of guests they are bringing.","category":"gatherpress","ancestor":["gatherpress/rsvp"],"icon":"yes","example":{},"attributes":{"label":{"type":"string","default":"Number of guests?"}},"supports":{"align":["left","center","right"],"spacing":{"margin":true,"padding":true},"color":{"text":true,"background":true},"html":false,"interactivity":true},"textdomain":"gatherpress","editorScript":"file:./index.js","style":"file:./style-index.css","render":"file:./render.php"}');(0,e.registerBlockType)(i,{edit:({attributes:e,setAttributes:i})=>{const{label:l}=e,n=(0,s.useSelect)((e=>e("core/editor").getEditedPostAttribute("meta")?.gatherpress_max_guest_limit),[]),o=(0,r.useBlockProps)({className:0===n?"gatherpress--is-not-visible":""});return(0,a.jsxs)("p",{...o,children:[(0,a.jsx)(r.RichText,{tagName:"label",value:l,onChange:e=>i({label:e}),placeholder:(0,t.__)("Enter label…","gatherpress"),"aria-label":(0,t.__)("Editable label for guest count input","gatherpress"),allowedFormats:["core/bold","core/italic"],multiline:!1}),(0,a.jsx)("input",{type:"number",placeholder:"0","aria-label":l||(0,t.__)("Guest Count Input","gatherpress"),disabled:!0,min:"0",max:"0"})]})},save:()=>null})}},r={};function s(e){var a=r[e];if(void 0!==a)return a.exports;var i=r[e]={exports:{}};return t[e](i,i.exports,s),i.exports}s.m=t,e=[],s.O=(t,r,a,i)=>{if(!r){var l=1/0;for(u=0;u<e.length;u++){for(var[r,a,i]=e[u],n=!0,o=0;o<r.length;o++)(!1&i||l>=i)&&Object.keys(s.O).every((e=>s.O[e](r[o])))?r.splice(o--,1):(n=!1,i<l&&(l=i));if(n){e.splice(u--,1);var p=a();void 0!==p&&(t=p)}}return t}i=i||0;for(var u=e.length;u>0&&e[u-1][2]>i;u--)e[u]=e[u-1];e[u]=[r,a,i]},s.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={276:0,412:0};s.O.j=t=>0===e[t];var t=(t,r)=>{var a,i,[l,n,o]=r,p=0;if(l.some((t=>0!==e[t]))){for(a in n)s.o(n,a)&&(s.m[a]=n[a]);if(o)var u=o(s)}for(t&&t(r);p<l.length;p++)i=l[p],s.o(e,i)&&e[i]&&e[i][0](),e[i]=0;return s.O(u)},r=globalThis.webpackChunkgatherpress=globalThis.webpackChunkgatherpress||[];r.forEach(t.bind(null,0)),r.push=t.bind(null,r.push.bind(r))})();var a=s.O(void 0,[412],(()=>s(8036)));a=s.O(a)})();