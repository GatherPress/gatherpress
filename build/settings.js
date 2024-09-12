(()=>{"use strict";var e={n:t=>{var n=t&&t.__esModule?()=>t.default:()=>t;return e.d(n,{a:n}),n},d:(t,n)=>{for(var r in n)e.o(n,r)&&!e.o(t,r)&&Object.defineProperty(t,r,{enumerable:!0,get:n[r]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t)};const t=window.wp.element,n=window.lodash,r=window.wp.components,o=window.wp.i18n,s=window.wp.coreData,a=window.wp.data,i=window.ReactJSXRuntime,l=e=>{var l,d;const{name:u,option:c,value:m,fieldOptions:p}=e.attrs,g=1!==p.limit,[w,h]=(0,t.useState)(null!==(l=JSON.parse(m))&&void 0!==l?l:"[]"),{contentList:v}=(0,a.useSelect)((e=>{const{getEntityRecords:t}=e(s.store);return{contentList:t("user"!==p.type?"postType":"root",p.type||"post",{per_page:-1,context:"view"})}}),[p.type]),_=null!==(d=v?.reduce(((e,t)=>({...e,[t.title?.rendered||t.name]:t})),{}))&&void 0!==d?d:{};return(0,i.jsxs)(i.Fragment,{children:[(0,i.jsx)(r.FormTokenField,{label:p.label||(0,o.__)("Select Posts","gatherpress"),name:u,value:w&&w.map((e=>({id:e.id,slug:e.slug,value:e.title?.rendered||e.name||e.value}))),suggestions:Object.keys(_),onChange:e=>{if(e.some((e=>"string"==typeof e&&!_[e])))return;const t=e.map((e=>"string"==typeof e?_[e]:e));if((0,n.includes)(t,null))return!1;h(t)},maxSuggestions:p.max_suggestions||20,maxLength:p.limit||0,__experimentalShowHowTo:g},c),!1===g&&(0,i.jsx)("p",{className:"description",children:(0,o.__)("Choose only one item.","gatherpress")}),(0,i.jsx)("input",{type:"hidden",id:c,name:u,value:w&&JSON.stringify(w.map((e=>({id:e.id,slug:e.slug,value:e.title?.rendered||e.name||e.value}))))})]})},d=window.moment;var u=e.n(d);function c(e){if("object"==typeof GatherPress)return e.split(".").reduce(((e,t)=>e&&e[t]),GatherPress)}const m=window.wp.date,p=e=>{const{name:n,value:r}=e.attrs,[o,s]=(0,t.useState)(r);return document.querySelector(`[name="${n}"]`).addEventListener("input",(e=>{s(e.target.value)}),{once:!0}),(0,i.jsx)(i.Fragment,{children:o&&(0,m.format)(o)})},g="YYYY-MM-DDTHH:mm:ss",w=u().tz(h()).add(1,"day").set("hour",18).set("minute",0).set("second",0).format(g);function h(e=c("eventDetails.dateTime.timezone")){return u().tz.zone(e)?e:(0,o.__)("GMT","gatherpress")}u().tz(w,h()).add(2,"hours").format(g);const v=e=>{const{name:n,value:r,suffix:o}=e.attrs,[s,a]=(0,t.useState)(r),l=document.querySelector(`[name="${n}"]`),d=c("urls.homeUrl");return l.addEventListener("input",(e=>{a(e.target.value)}),{once:!0}),(0,i.jsxs)(i.Fragment,{children:[d+"/",(0,i.jsx)("strong",{children:s}),"/"+o]})},_=document.querySelectorAll('[data-gatherpress_component_name="autocomplete"]');for(let e=0;e<_.length;e++){const n=JSON.parse(_[e].dataset.gatherpress_component_attrs);(0,t.createRoot)(_[e]).render((0,i.jsx)(l,{attrs:n}))}!function(){const e=document.querySelectorAll('[data-gatherpress_component_name="datetime-preview"]');for(let n=0;n<e.length;n++){const r=JSON.parse(e[n].dataset.gatherpress_component_attrs);(0,t.createRoot)(e[n]).render((0,i.jsx)(p,{attrs:r}))}}(),function(){const e=document.querySelectorAll('[data-gatherpress_component_name="urlrewrite-preview"]');for(let n=0;n<e.length;n++){const r=JSON.parse(e[n].dataset.gatherpress_component_attrs);(0,t.createRoot)(e[n]).render((0,i.jsx)(v,{attrs:r}))}}()})();