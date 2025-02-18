(()=>{"use strict";var e,t={9007:()=>{const e=window.wp.blocks,t=window.wp.i18n,r=window.wp.blockEditor,n=window.wp.element,o=window.wp.data,s={randomUUID:"undefined"!=typeof crypto&&crypto.randomUUID&&crypto.randomUUID.bind(crypto)};let i;const a=new Uint8Array(16);function l(){if(!i&&(i="undefined"!=typeof crypto&&crypto.getRandomValues&&crypto.getRandomValues.bind(crypto),!i))throw new Error("crypto.getRandomValues() not supported. See https://github.com/uuidjs/uuid#getrandomvalues-not-supported");return i(a)}const p=[];for(let e=0;e<256;++e)p.push((e+256).toString(16).slice(1));const u=function(e,t,r){if(s.randomUUID&&!t&&!e)return s.randomUUID();const n=(e=e||{}).random||(e.rng||l)();if(n[6]=15&n[6]|64,n[8]=63&n[8]|128,t){r=r||0;for(let e=0;e<16;++e)t[r+e]=n[e];return t}return function(e,t=0){return p[e[t+0]]+p[e[t+1]]+p[e[t+2]]+p[e[t+3]]+"-"+p[e[t+4]]+p[e[t+5]]+"-"+p[e[t+6]]+p[e[t+7]]+"-"+p[e[t+8]]+p[e[t+9]]+"-"+p[e[t+10]]+p[e[t+11]]+p[e[t+12]]+p[e[t+13]]+p[e[t+14]]+p[e[t+15]]}(n)},c=window.ReactJSXRuntime,d=JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"gatherpress/rsvp-guest-count-input","version":"1.0.0","title":"RSVP Guest Count Input","description":"Allows members to specify the number of guests they are bringing.","category":"gatherpress","ancestor":["gatherpress/rsvp"],"icon":"yes","example":{},"attributes":{"label":{"type":"string","default":"Number of guests?"},"inputId":{"type":"string"}},"supports":{"align":["left","center","right"],"spacing":{"margin":true,"padding":true},"color":{"text":true,"background":true},"html":false,"interactivity":true},"textdomain":"gatherpress","editorScript":"file:./index.js","style":"file:./style-index.css","render":"file:./render.php"}');(0,e.registerBlockType)(d,{edit:({attributes:e,setAttributes:s})=>{const{label:i,inputId:a}=e;(0,n.useEffect)((()=>{a||s({inputId:"input-"+u()})}),[a,s]);const l=(0,o.useSelect)((e=>e("core/editor").getEditedPostAttribute("meta")?.gatherpress_max_guest_limit),[]),p=(0,r.useBlockProps)({className:0===l?"gatherpress--is-not-visible":""});return(0,c.jsxs)("p",{...p,children:[(0,c.jsx)(r.RichText,{tagName:"label",htmlFor:a,value:i,onChange:e=>s({label:e}),placeholder:(0,t.__)("Enter label…","gatherpress"),"aria-label":(0,t.__)("Editable label for guest count input","gatherpress"),allowedFormats:["core/bold","core/italic"],multiline:!1}),(0,c.jsx)("input",{type:"number",id:a,placeholder:"0","aria-label":i||(0,t.__)("Guest Count Input","gatherpress"),disabled:!0,min:"0",max:"0"})]})},save:()=>null})}},r={};function n(e){var o=r[e];if(void 0!==o)return o.exports;var s=r[e]={exports:{}};return t[e](s,s.exports,n),s.exports}n.m=t,e=[],n.O=(t,r,o,s)=>{if(!r){var i=1/0;for(u=0;u<e.length;u++){for(var[r,o,s]=e[u],a=!0,l=0;l<r.length;l++)(!1&s||i>=s)&&Object.keys(n.O).every((e=>n.O[e](r[l])))?r.splice(l--,1):(a=!1,s<i&&(i=s));if(a){e.splice(u--,1);var p=o();void 0!==p&&(t=p)}}return t}s=s||0;for(var u=e.length;u>0&&e[u-1][2]>s;u--)e[u]=e[u-1];e[u]=[r,o,s]},n.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={276:0,412:0};n.O.j=t=>0===e[t];var t=(t,r)=>{var o,s,[i,a,l]=r,p=0;if(i.some((t=>0!==e[t]))){for(o in a)n.o(a,o)&&(n.m[o]=a[o]);if(l)var u=l(n)}for(t&&t(r);p<i.length;p++)s=i[p],n.o(e,s)&&e[s]&&e[s][0](),e[s]=0;return n.O(u)},r=globalThis.webpackChunkgatherpress=globalThis.webpackChunkgatherpress||[];r.forEach(t.bind(null,0)),r.push=t.bind(null,r.push.bind(r))})();var o=n.O(void 0,[412],(()=>n(9007)));o=n.O(o)})();