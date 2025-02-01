(()=>{"use strict";var e,t={8928:()=>{const e=window.wp.blocks,t=window.wp.i18n,r=window.wp.blockEditor,s=window.wp.data,i=window.ReactJSXRuntime,n=JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"gatherpress/rsvp-guest-count-display","version":"1.0.0","title":"RSVP Guest Count Display","description":"Displays the number of guests associated with a member\'s RSVP.","category":"gatherpress","ancestor":["gatherpress/rsvp","gatherpress/rsvp-template"],"icon":"yes","example":{},"attributes":{},"usesContext":["commentId","gatherpress/rsvpResponses"],"supports":{"align":["left","center","right"],"spacing":{"margin":true,"padding":true},"typography":{"fontSize":true,"lineHeight":true},"color":{"text":true,"background":true},"html":false,"interactivity":true},"textdomain":"gatherpress","editorScript":"file:./index.js","style":"file:./style-index.css","render":"file:./render.php"}');(0,e.registerBlockType)(n,{edit:({context:e})=>{var n;const{commentId:o}=e,a=null!==(n=e?.["gatherpress/rsvpResponses"])&&void 0!==n?n:null;let p=1;if(o&&a){const e=a.attending.records.find((e=>e.commentId===o));e&&(p=e.guests)}const l=(0,s.useSelect)((e=>e("core/editor").getEditedPostAttribute("meta")?.gatherpress_max_guest_limit),[]),c=(0,r.useBlockProps)({className:0!==l||o?"":"gatherpress--is-not-visible"});if(0===p)return(0,i.jsx)("div",{...c});const u=(0,t.sprintf)(/* translators: %d: Number of guests. Singular and plural forms are used for 1 guest and multiple guests, respectively. */ /* translators: %d: Number of guests. Singular and plural forms are used for 1 guest and multiple guests, respectively. */
(0,t._n)("+%d guest","+%d guests",p,"gatherpress"),p);return(0,i.jsx)("div",{...c,children:u})},save:()=>null})}},r={};function s(e){var i=r[e];if(void 0!==i)return i.exports;var n=r[e]={exports:{}};return t[e](n,n.exports,s),n.exports}s.m=t,e=[],s.O=(t,r,i,n)=>{if(!r){var o=1/0;for(c=0;c<e.length;c++){for(var[r,i,n]=e[c],a=!0,p=0;p<r.length;p++)(!1&n||o>=n)&&Object.keys(s.O).every((e=>s.O[e](r[p])))?r.splice(p--,1):(a=!1,n<o&&(o=n));if(a){e.splice(c--,1);var l=i();void 0!==l&&(t=l)}}return t}n=n||0;for(var c=e.length;c>0&&e[c-1][2]>n;c--)e[c]=e[c-1];e[c]=[r,i,n]},s.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={7498:0,702:0};s.O.j=t=>0===e[t];var t=(t,r)=>{var i,n,[o,a,p]=r,l=0;if(o.some((t=>0!==e[t]))){for(i in a)s.o(a,i)&&(s.m[i]=a[i]);if(p)var c=p(s)}for(t&&t(r);l<o.length;l++)n=o[l],s.o(e,n)&&e[n]&&e[n][0](),e[n]=0;return s.O(c)},r=globalThis.webpackChunkgatherpress=globalThis.webpackChunkgatherpress||[];r.forEach(t.bind(null,0)),r.push=t.bind(null,r.push.bind(r))})();var i=s.O(void 0,[702],(()=>s(8928)));i=s.O(i)})();