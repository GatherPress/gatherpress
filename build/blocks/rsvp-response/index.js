(()=>{"use strict";var e,t={5733:(e,t,s)=>{const n=window.wp.blocks,r=window.React,a=window.wp.blockEditor,i=window.wp.element,o=window.wp.i18n,l=window.wp.components,p=(e,t="")=>{for(const[s,n]of Object.entries(e)){let e=s;t&&(e+="_"+String(t)),addEventListener(e,(e=>{n(e.detail)}),!1)}};function c(e){if("object"==typeof GatherPress)return e.split(".").reduce(((e,t)=>e&&e[t]),GatherPress)}window.wp.data;const m=({item:e,activeItem:t=!1,count:s,onTitleClick:n,defaultLimit:a})=>{const{title:o,value:l}=e,p=!(0===s&&"attending"!==l),m=t?"span":"a",v=c("post_id"),u=s>a;return(0,i.useEffect)((()=>{t&&((e,t="")=>{for(const[s,n]of Object.entries(e)){let e=s;t&&(e+="_"+String(t));const r=new CustomEvent(e,{detail:n});dispatchEvent(r)}})({setRsvpSeeAllLink:u},v)})),p?(0,r.createElement)("div",{className:"gp-rsvp-response__navigation-item"},(0,r.createElement)(m,{className:"gp-rsvp-response__anchor","data-item":l,"data-toggle":"tab",href:"#",role:"tab","aria-controls":`#gp-rsvp-${l}`,onClick:e=>n(e,l)},o),(0,r.createElement)("span",{className:"gp-rsvp-response__count"},"(",s,")")):""},v=({items:e,activeValue:t,onTitleClick:n,defaultLimit:a})=>{const o={all:0,attending:0,not_attending:0,waiting_list:0};for(const[e,t]of Object.entries(c("responses")))o[e]=t.count;const[l,v]=(0,i.useState)(o),[u,d]=(0,i.useState)(!1),[g,_]=(0,i.useState)(!0),h=g?"span":"a";p({setRsvpCount:v},c("post_id"));let f=0;const E=e.map(((e,s)=>{const i=e.value===t;return i&&(f=s),(0,r.createElement)(m,{key:s,item:e,count:l[e.value],activeItem:i,onTitleClick:n,defaultLimit:a})}));return(0,i.useEffect)((()=>{s.g.document.addEventListener("click",(({target:e})=>{e.closest(".gp-rsvp-response__navigation-active")||d(!1)})),s.g.document.addEventListener("keydown",(({key:e})=>{"Escape"===e&&d(!1)}))})),(0,i.useEffect)((()=>{0===l.not_attending&&0===l.waiting_list?_(!0):_(!1)}),[l]),(0,r.createElement)("div",{className:"gp-rsvp-response__navigation-wrapper"},(0,r.createElement)("div",null,(0,r.createElement)(h,{href:"#",className:"gp-rsvp-response__navigation-active",onClick:e=>(e=>{e.preventDefault(),d(!u)})(e)},e[f].title)," ",(0,r.createElement)("span",null,"(",l[t],")")),!g&&u&&(0,r.createElement)("nav",{className:"gp-rsvp-response__navigation"},E))},u=({items:e,activeValue:t,onTitleClick:s,rsvpLimit:n,setRsvpLimit:a,defaultLimit:l})=>{let m;m=!1===n?(0,o.__)("See fewer","gatherpress"):(0,o.__)("See all","gatherpress");const[u,d]=(0,i.useState)(c("responses")[t].count>l);return p({setRsvpSeeAllLink:d},c("post_id")),(0,r.createElement)("div",{className:"gp-rsvp-response__header"},(0,r.createElement)("div",{className:"dashicons dashicons-groups"}),(0,r.createElement)(v,{items:e,activeValue:t,onTitleClick:s,defaultLimit:l}),u&&(0,r.createElement)("div",{className:"gp-rsvp-response__see-all"},(0,r.createElement)("a",{href:"#",onClick:e=>(e=>{e.preventDefault(),a(!1===n&&l)})(e)},m)))},d=({value:e,limit:t,responses:s=[]})=>{let n="";return"object"==typeof s&&void 0!==s[e]&&(s=[...s[e].responses],t&&(s=s.splice(0,t)),n=s.map(((e,t)=>{const{profile:s,name:n,photo:a,role:i}=e;let{guests:o}=e;return o=o?" +"+o+" guest(s)":"",(0,r.createElement)("div",{key:t,className:"gp-rsvp-response__item"},(0,r.createElement)("figure",{className:"gp-rsvp-response__member-avatar"},(0,r.createElement)("a",{href:s},(0,r.createElement)("img",{alt:n,title:n,src:a}))),(0,r.createElement)("div",{className:"gp-rsvp-response__member-info"},(0,r.createElement)("div",{className:"gp-rsvp-response__member-name"},(0,r.createElement)("a",{href:s,title:n},n)),(0,r.createElement)("div",{className:"gp-rsvp-response__member-role"},i),(0,r.createElement)("small",{className:"gp-rsvp-response__guests"},o)))}))),(0,r.createElement)(r.Fragment,null,"attending"===e&&0===n.length&&(0,r.createElement)("div",{className:"gp-rsvp-response__no-responses"},!1===c("has_event_past")?(0,o.__)("No one is attending this event yet.","gatherpress"):(0,o.__)("No one went to this event.","gatherpress")),n)},g=({items:e,activeValue:t,limit:s=!1,editMode:n})=>{const a=c("post_id"),[o,l]=(0,i.useState)(c("responses"));p({setRsvpResponse:l},a);const m=e.map(((e,a)=>{const{value:i}=e;return i===t?(0,r.createElement)("div",{key:a,className:"gp-rsvp-response__items",id:`gp-rsvp-${i}`,role:"tabpanel","aria-labelledby":`gp-rsvp-${i}-tab`},!n&&(0,r.createElement)(d,{value:i,limit:s,responses:o}),n&&(0,r.createElement)("div",null,"Autocomplete Component goes here Autocomplete Component goes here Autocomplete Component goes here Autocomplete Component goes here")):""}));return(0,r.createElement)("div",{className:"gp-rsvp-response__content"},m)},_=()=>{const e=c("has_event_past"),t=[{title:!1===e?(0,o.__)("Attending","gatherpress"):(0,o.__)("Went","gatherpress"),value:"attending"},{title:!1===e?(0,o.__)("Waiting List","gatherpress"):(0,o.__)("Wait Listed","gatherpress"),value:"waiting_list"},{title:!1===e?(0,o.__)("Not Attending","gatherpress"):(0,o.__)("Didn't Go","gatherpress"),value:"not_attending"}],[s,n]=(0,i.useState)("attending"),[a,m]=(0,i.useState)(8),[v,d]=(0,i.useState)(!1);p({setRsvpStatus:n},c("post_id"));const _=c("current_user").is_admin;return(0,r.createElement)("div",{className:"gp-rsvp-response"},(0,r.createElement)(u,{items:t,activeValue:s,onTitleClick:(e,t)=>{e.preventDefault(),n(t)},rsvpLimit:a,setRsvpLimit:m,defaultLimit:8}),_&&(0,r.createElement)(l.Button,{variant:"secondary",onClick:e=>{e.preventDefault(),d(!v)}},"Edit Attendees"),(0,r.createElement)(g,{items:t,activeValue:s,limit:a,editMode:v}))},h=e=>{const{isSelected:t}=e,s=t?"none":"block";return(0,r.createElement)("div",{style:{position:"relative"}},e.children,(0,r.createElement)("div",{style:{position:"absolute",top:"0",right:"0",bottom:"0",left:"0",display:s}}))},f=JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":2,"name":"gatherpress/rsvp-response","version":"1.0.0","title":"RSVP Response","category":"gatherpress","icon":"groups","example":{},"description":"Displays a list of members who have confirmed their attendance for an event.","attributes":{"blockId":{"type":"string"},"content":{"type":"string"},"color":{"type":"string"}},"supports":{"html":false},"textdomain":"gatherpress","editorScript":"file:./index.js","style":"file:./style-index.css","viewScript":"file:./rsvp-response.js","render":"file:./render.php"}');(0,n.registerBlockType)(f,{edit:()=>{const e=(0,a.useBlockProps)();return(0,r.createElement)("div",{...e},(0,r.createElement)(h,null,(0,r.createElement)(_,null)))},save:()=>null})}},s={};function n(e){var r=s[e];if(void 0!==r)return r.exports;var a=s[e]={exports:{}};return t[e](a,a.exports,n),a.exports}n.m=t,e=[],n.O=(t,s,r,a)=>{if(!s){var i=1/0;for(c=0;c<e.length;c++){for(var[s,r,a]=e[c],o=!0,l=0;l<s.length;l++)(!1&a||i>=a)&&Object.keys(n.O).every((e=>n.O[e](s[l])))?s.splice(l--,1):(o=!1,a<i&&(i=a));if(o){e.splice(c--,1);var p=r();void 0!==p&&(t=p)}}return t}a=a||0;for(var c=e.length;c>0&&e[c-1][2]>a;c--)e[c]=e[c-1];e[c]=[s,r,a]},n.g=function(){if("object"==typeof globalThis)return globalThis;try{return this||new Function("return this")()}catch(e){if("object"==typeof window)return window}}(),n.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={304:0,952:0};n.O.j=t=>0===e[t];var t=(t,s)=>{var r,a,[i,o,l]=s,p=0;if(i.some((t=>0!==e[t]))){for(r in o)n.o(o,r)&&(n.m[r]=o[r]);if(l)var c=l(n)}for(t&&t(s);p<i.length;p++)a=i[p],n.o(e,a)&&e[a]&&e[a][0](),e[a]=0;return n.O(c)},s=globalThis.webpackChunkgatherpress=globalThis.webpackChunkgatherpress||[];s.forEach(t.bind(null,0)),s.push=t.bind(null,s.push.bind(s))})();var r=n.O(void 0,[952],(()=>n(5733)));r=n.O(r)})();