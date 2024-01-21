(()=>{"use strict";var e={n:t=>{var s=t&&t.__esModule?()=>t.default:()=>t;return e.d(s,{a:s}),s},d:(t,s)=>{for(var n in s)e.o(s,n)&&!e.o(t,n)&&Object.defineProperty(t,n,{enumerable:!0,get:s[n]})}};e.g=function(){if("object"==typeof globalThis)return globalThis;try{return this||new Function("return this")()}catch(e){if("object"==typeof window)return window}}(),e.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t);const t=window.React,s=window.wp.domReady;var n=e.n(s);const a=window.wp.element,r=window.wp.i18n,i=window.wp.components,l=(e,t="")=>{for(const[s,n]of Object.entries(e)){let e=s;t&&(e+="_"+String(t)),addEventListener(e,(e=>{n(e.detail)}),!1)}};function o(e){if("object"==typeof GatherPress)return e.split(".").reduce(((e,t)=>e&&e[t]),GatherPress)}window.wp.data;const c=({item:e,activeItem:s=!1,count:n,onTitleClick:r,defaultLimit:i})=>{const{title:l,value:c}=e,p=!(0===n&&"attending"!==c),m=s?"span":"a",v=o("post_id"),d=n>i;return(0,a.useEffect)((()=>{s&&((e,t="")=>{for(const[s,n]of Object.entries(e)){let e=s;t&&(e+="_"+String(t));const a=new CustomEvent(e,{detail:n});dispatchEvent(a)}})({setRsvpSeeAllLink:d},v)})),p?(0,t.createElement)("div",{className:"gp-rsvp-response__navigation-item"},(0,t.createElement)(m,{className:"gp-rsvp-response__anchor","data-item":c,"data-toggle":"tab",href:"#",role:"tab","aria-controls":`#gp-rsvp-${c}`,onClick:e=>r(e,c)},l),(0,t.createElement)("span",{className:"gp-rsvp-response__count"},"(",n,")")):""},p=({items:s,activeValue:n,onTitleClick:r,defaultLimit:i})=>{const p={all:0,attending:0,not_attending:0,waiting_list:0};for(const[e,t]of Object.entries(o("responses")))p[e]=t.count;const[m,v]=(0,a.useState)(p),[d,u]=(0,a.useState)(!1),[g,_]=(0,a.useState)(!0),E=g?"span":"a";l({setRsvpCount:v},o("post_id"));let f=0;const h=s.map(((e,s)=>{const a=e.value===n;return a&&(f=s),(0,t.createElement)(c,{key:s,item:e,count:m[e.value],activeItem:a,onTitleClick:r,defaultLimit:i})}));return(0,a.useEffect)((()=>{e.g.document.addEventListener("click",(({target:e})=>{e.closest(".gp-rsvp-response__navigation-active")||u(!1)})),e.g.document.addEventListener("keydown",(({key:e})=>{"Escape"===e&&u(!1)}))})),(0,a.useEffect)((()=>{0===m.not_attending&&0===m.waiting_list?_(!0):_(!1)}),[m]),(0,t.createElement)("div",{className:"gp-rsvp-response__navigation-wrapper"},(0,t.createElement)("div",null,(0,t.createElement)(E,{href:"#",className:"gp-rsvp-response__navigation-active",onClick:e=>(e=>{e.preventDefault(),u(!d)})(e)},s[f].title)," ",(0,t.createElement)("span",null,"(",m[n],")")),!g&&d&&(0,t.createElement)("nav",{className:"gp-rsvp-response__navigation"},h))},m=({items:e,activeValue:s,onTitleClick:n,rsvpLimit:i,setRsvpLimit:c,defaultLimit:m})=>{let v;v=!1===i?(0,r.__)("See fewer","gatherpress"):(0,r.__)("See all","gatherpress");const[d,u]=(0,a.useState)(o("responses")[s].count>m);return l({setRsvpSeeAllLink:u},o("post_id")),(0,t.createElement)("div",{className:"gp-rsvp-response__header"},(0,t.createElement)("div",{className:"dashicons dashicons-groups"}),(0,t.createElement)(p,{items:e,activeValue:s,onTitleClick:n,defaultLimit:m}),d&&(0,t.createElement)("div",{className:"gp-rsvp-response__see-all"},(0,t.createElement)("a",{href:"#",onClick:e=>(e=>{e.preventDefault(),c(!1===i&&m)})(e)},v)))},v=({value:e,limit:s,responses:n=[]})=>{let a="";return"object"==typeof n&&void 0!==n[e]&&(n=[...n[e].responses],s&&(n=n.splice(0,s)),a=n.map(((e,s)=>{const{profile:n,name:a,photo:r,role:i}=e;let{guests:l}=e;return l=l?" +"+l+" guest(s)":"",(0,t.createElement)("div",{key:s,className:"gp-rsvp-response__item"},(0,t.createElement)("figure",{className:"gp-rsvp-response__member-avatar"},(0,t.createElement)("a",{href:n},(0,t.createElement)("img",{alt:a,title:a,src:r}))),(0,t.createElement)("div",{className:"gp-rsvp-response__member-info"},(0,t.createElement)("div",{className:"gp-rsvp-response__member-name"},(0,t.createElement)("a",{href:n,title:a},a)),(0,t.createElement)("div",{className:"gp-rsvp-response__member-role"},i),(0,t.createElement)("small",{className:"gp-rsvp-response__guests"},l)))}))),(0,t.createElement)(t.Fragment,null,"attending"===e&&0===a.length&&(0,t.createElement)("div",{className:"gp-rsvp-response__no-responses"},!1===o("has_event_past")?(0,r.__)("No one is attending this event yet.","gatherpress"):(0,r.__)("No one went to this event.","gatherpress")),a)},d=({items:e,activeValue:s,limit:n=!1,editMode:r})=>{const c=o("post_id"),[p,m]=(0,a.useState)(o("responses")),d=[{name:"Attendees",triggerPrefix:"~",options:p.attending.responses,getOptionLabel:e=>(0,t.createElement)("span",null,(0,t.createElement)("span",{className:"icon"},e.visual),e.name),getOptionKeywords:e=>[e.name],isOptionDisabled:e=>"Grapes"===e.name,getOptionCompletion:e=>(0,t.createElement)("abbr",{title:e.name},e.visual)}];console.log(d),l({setRsvpResponse:m},c);const u=e.map(((e,a)=>{const{value:l}=e;return l===s?(0,t.createElement)("div",{key:a,className:"gp-rsvp-response__items",id:`gp-rsvp-${l}`,role:"tabpanel","aria-labelledby":`gp-rsvp-${l}-tab`},!r&&(0,t.createElement)(v,{value:l,limit:n,responses:p}),r&&(0,t.createElement)("div",null,(0,t.createElement)(i.Autocomplete,{completers:d}))):""}));return(0,t.createElement)("div",{className:"gp-rsvp-response__content"},u)},u=()=>{const e=o("has_event_past"),s=[{title:!1===e?(0,r.__)("Attending","gatherpress"):(0,r.__)("Went","gatherpress"),value:"attending"},{title:!1===e?(0,r.__)("Waiting List","gatherpress"):(0,r.__)("Wait Listed","gatherpress"),value:"waiting_list"},{title:!1===e?(0,r.__)("Not Attending","gatherpress"):(0,r.__)("Didn't Go","gatherpress"),value:"not_attending"}],[n,c]=(0,a.useState)("attending"),[p,v]=(0,a.useState)(8),[u,g]=(0,a.useState)(!1);l({setRsvpStatus:c},o("post_id"));const _=o("current_user").is_admin;return(0,t.createElement)("div",{className:"gp-rsvp-response"},(0,t.createElement)(m,{items:s,activeValue:n,onTitleClick:(e,t)=>{e.preventDefault(),c(t)},rsvpLimit:p,setRsvpLimit:v,defaultLimit:8}),_&&(0,t.createElement)(i.Button,{variant:"secondary",onClick:e=>{e.preventDefault(),g(!u)}},"Edit Attendees"),(0,t.createElement)(d,{items:s,activeValue:n,limit:p,editMode:u}))};n()((()=>{const e=document.querySelectorAll('[data-gp_block_name="rsvp-response"]');for(let s=0;s<e.length;s++)(0,a.createRoot)(e[s]).render((0,t.createElement)(u,null))}))})();