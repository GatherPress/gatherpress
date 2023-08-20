(()=>{"use strict";var e={n:t=>{var n=t&&t.__esModule?()=>t.default:()=>t;return e.d(n,{a:n}),n},d:(t,n)=>{for(var r in n)e.o(n,r)&&!e.o(t,r)&&Object.defineProperty(t,r,{enumerable:!0,get:n[r]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t)};const t=window.wp.element,n=window.wp.i18n,r=window.wp.data,a=window.wp.blocks,s=window.wp.components,o=window.wp.plugins,i=window.wp.editPost,l=window.moment;var c=e.n(l);const m=window.wp.apiFetch;var d=e.n(m);function u(){(0,r.dispatch)("core/editor").editPost({meta:{_non_existing_meta:!0}})}function p(e){if("object"==typeof GatherPress)return e.split(".").reduce(((e,t)=>e&&e[t]),GatherPress)}function g(e,t){if("object"!=typeof GatherPress)return;const n=e.split("."),r=n.pop();n.reduce(((e,t)=>{var n;return null!==(n=e[t])&&void 0!==n?n:e[t]={}}),GatherPress)[r]=t}const _="YYYY-MM-DDTHH:mm:ss",v="YYYY-MM-DD HH:mm:ss",f="MMMM D, YYYY h:mm a",E=(e=p("event_datetime.timezone"))=>c().tz.zone(e)?e:(0,n.__)("GMT","gatherpress"),h=(e="")=>{const t=/^(\+|-)(\d{2}):(00|15|30|45)$/,n=e.replace(t,"$1");return n!==e?"UTC"+n+parseInt(e.replace(t,"$2")).toString()+e.replace(t,"$3").replace("00","").replace("15",".25").replace("30",".5").replace("45",".75"):e},w=c().tz(E()).add(1,"day").set("hour",18).set("minute",0).set("second",0).format(_),b=c().tz(w,E()).add(2,"hours").format(_),S=(e,t=null)=>{!function(e){const t=c().tz(p("event_datetime.datetime_end"),E()).valueOf(),n=c().tz(e,E()).valueOf();if(n>=t){const e=c().tz(n,E()).add(2,"hours").format(_);T(e)}}(e),g("event_datetime.datetime_start",e),"function"==typeof t&&t(e),u()},T=(e,t=null)=>{!function(e){const t=c().tz(p("event_datetime.datetime_start"),E()).valueOf(),n=c().tz(e,E()).valueOf();if(n<=t){const e=c().tz(n,E()).subtract(2,"hours").format(_);S(e)}}(e),g("event_datetime.datetime_end",e),null!==t&&t(e),u()},z=(e,t=!1)=>{for(const[n,r]of Object.entries(e)){let e=n;t&&(e+=t);const a=new CustomEvent(e,{detail:r});dispatchEvent(a)}};function D(){return p("post_type")===(0,r.select)("core/editor").getCurrentPostType()}function k(){const e=c()(p("event_datetime.datetime_end"));return c().tz(E()).valueOf()>e.tz(E()).valueOf()}function P(){const e="gp_event_past",t=(0,r.dispatch)("core/notices");t.removeNotice(e),k()&&t.createNotice("warning",(0,n.__)("This event has already past.","gatherpress"),{id:e,isDismissible:!1})}const C=window.wp.date,y=e=>{const{dateTimeStart:t}=e;return c().tz(t,E()).format(f)},O=e=>{const{dateTimeEnd:t}=e;return c().tz(t,E()).format(f)},x=e=>{const{dateTimeStart:n,setDateTimeStart:r}=e,a=(0,C.getSettings)(),o=/a(?!\\)/i.test(a.formats.time.toLowerCase().replace(/\\\\/g,"").split("").reverse().join(""));return(0,t.createElement)(s.DateTimePicker,{currentDate:n,onChange:e=>S(e,r),is12Hour:o})},Y=e=>{const{dateTimeEnd:n,setDateTimeEnd:r}=e,a=(0,C.getSettings)(),o=/a(?!\\)/i.test(a.formats.time.toLowerCase().replace(/\\\\/g,"").split("").reverse().join(""));return(0,t.createElement)(s.DateTimePicker,{currentDate:n,onChange:e=>T(e,r),is12Hour:o})},M=e=>{const{dateTimeStart:r,setDateTimeStart:a}=e;return(0,t.useEffect)((()=>{a(c().tz((()=>{let e=p("event_datetime.datetime_start");return e=""!==e?c().tz(e,E()).format(_):w,g("event_datetime.datetime_start",e),e})(),E()).format(_)),z({setDateTimeStart:r}),P()})),(0,t.createElement)(s.PanelRow,null,(0,t.createElement)(s.Flex,null,(0,t.createElement)(s.FlexItem,null,(0,n.__)("Start","gatherpress")),(0,t.createElement)(s.FlexItem,null,(0,t.createElement)(s.Dropdown,{position:"bottom left",renderToggle:({isOpen:e,onToggle:n})=>(0,t.createElement)(s.Button,{onClick:n,"aria-expanded":e,isLink:!0},(0,t.createElement)(y,{dateTimeStart:r})),renderContent:()=>(0,t.createElement)(x,{dateTimeStart:r,setDateTimeStart:a})}))))},j=e=>{const{dateTimeEnd:r,setDateTimeEnd:a}=e;return(0,t.useEffect)((()=>{a(c().tz((()=>{let e=p("event_datetime.datetime_end");return e=""!==e?c().tz(e,E()).format(_):b,g("event_datetime.datetime_end",e),e})(),E()).format(_)),z({setDateTimeEnd:r}),P()})),(0,t.createElement)(s.PanelRow,null,(0,t.createElement)(s.Flex,null,(0,t.createElement)(s.FlexItem,null,(0,n.__)("End","gatherpress")),(0,t.createElement)(s.FlexItem,null,(0,t.createElement)(s.Dropdown,{position:"bottom left",renderToggle:({isOpen:e,onToggle:n})=>(0,t.createElement)(s.Button,{onClick:n,"aria-expanded":e,isLink:!0},(0,t.createElement)(O,{dateTimeEnd:r})),renderContent:()=>(0,t.createElement)(Y,{dateTimeEnd:r,setDateTimeEnd:a})}))))},B=e=>{const{timezone:r,setTimezone:a}=e,o=p("timezone_choices");return(0,t.useEffect)((()=>{a(p("event_datetime.timezone"))}),[a]),(0,t.useEffect)((()=>{z({setTimezone:p("event_datetime.timezone")})})),(0,t.createElement)(s.PanelRow,null,(0,t.createElement)(s.SelectControl,{label:(0,n.__)("Time Zone","gatherpress"),value:h(r),onChange:e=>{e=((e="")=>{const t=/^UTC(\+|-)(\d+)(.\d+)?$/,n=e.replace(t,"$1");if(n!==e){const r=e.replace(t,"$2").padStart(2,"0");let a=e.replace(t,"$3");return""===a&&(a=":00"),a=a.replace(".25",":15").replace(".5",":30").replace(".75",":45"),n+r+a}return e})(e),a(e),g("event_datetime.timezone",e),u()}},Object.keys(o).map((e=>(0,t.createElement)("optgroup",{key:e,label:e},Object.keys(o[e]).map((n=>(0,t.createElement)("option",{key:n,value:n},o[e][n]))))))))};(0,r.subscribe)((function(){const e=(0,r.select)("core/editor").isSavingPost(),t=(0,r.select)("core/editor").isAutosavingPost();D()&&e&&!t&&d()({path:"/gatherpress/v1/event/datetime/",method:"POST",data:{post_id:p("post_id"),datetime_start:c().tz(p("event_datetime.datetime_start"),E()).format(v),datetime_end:c().tz(p("event_datetime.datetime_end"),E()).format(v),timezone:p("event_datetime.timezone"),_wpnonce:p("nonce")}}).then((()=>{!function(){const e="gp_event_communcation",t=(0,r.dispatch)("core/notices");t.removeNotice(e),"publish"!==(0,r.select)("core/editor").getEditedPostAttribute("status")||k()||t.createNotice("success",(0,n.__)("Update members about this event via email?","gatherpress"),{id:e,isDismissible:!0,actions:[{onClick:()=>{z({setOpen:!0})},label:(0,n.__)("Create Message","gatherpress")}]})}()}))}));const $=()=>{const[e,r]=(0,t.useState)(),[a,s]=(0,t.useState)(),[o,i]=(0,t.useState)();return(0,t.createElement)("section",null,(0,t.createElement)("h3",null,(0,n.__)("Date & time","gatherpress")),(0,t.createElement)(M,{dateTimeStart:e,setDateTimeStart:r}),(0,t.createElement)(j,{dateTimeEnd:a,setDateTimeEnd:s}),(0,t.createElement)(B,{timezone:o,setTimezone:i}))},F=()=>{const{insertBlock:e}=(0,r.useDispatch)("core/block-editor"),[o,i]=(0,t.useState)(""),l=(0,r.useDispatch)("core/editor").editPost,{unlockPostSaving:c}=(0,r.useDispatch)("core/editor"),m=(0,r.useSelect)((()=>(0,r.select)("core/editor").getEditedPostAttribute("_gp_venue"))),d=(0,r.useSelect)((()=>(0,r.select)("core").getEntityRecord("taxonomy","_gp_venue",m))),u=d?.slug.slice(1,d?.slug.length),p=m+":"+u;(0,t.useEffect)((()=>{var e;i(null!==(e=String(p))&&void 0!==e?e:""),z({setVenueSlug:u})}),[p,u]);const{blocks:g}=(0,r.useSelect)((()=>({blocks:(0,r.select)("core/block-editor").getBlocks()}))),_=g.filter((e=>"gatherpress/event-venue"===e.name));let v=(0,r.useSelect)((()=>{const e=(0,r.select)("core").getEntityRecords("taxonomy","_gp_venue",{per_page:-1,context:"view"});let t;return e&&(t=e.filter((e=>"online"!==e.slug))),t}),[]);return v?(v=v.map((e=>({label:e.name,value:e.id+":"+e.slug.slice(1,e.slug.length)}))),v.unshift({value:":",label:(0,n.__)("Choose a venue","gatherpress")})):v=[],(0,t.createElement)(s.PanelRow,null,(0,t.createElement)(s.SelectControl,{label:(0,n.__)("Venue Selector","gatherpress"),value:o,onChange:t=>{(t=>{i(t);const n=""!==(t=t.split(":"))[0]?[t[0]]:[];if(l({_gp_venue:n}),z({setVenueSlug:t[1]}),c(),0===_.length){const t=(0,a.createBlock)("gatherpress/event-venue");e(t)}})(t)},options:v}))};(0,o.registerPlugin)("gp-event-settings",{render:()=>{const[e,o]=(0,t.useState)(!1),{editPost:l}=(0,r.useDispatch)("core/editor"),{removeBlock:c}=(0,r.useDispatch)("core/block-editor"),{insertBlock:m}=(0,r.useDispatch)("core/block-editor"),d=(0,r.useSelect)((()=>(0,r.select)("core").getEntityRecords("taxonomy","_gp_venue",{per_page:-1,context:"view"})),[]),u=(0,r.useSelect)((()=>(0,r.select)("core/editor").getEditedPostAttribute("_gp_venue")));let p;d&&d.map((e=>{"online"===e.slug&&(p=e.id)}));const{blocks:g}=(0,r.useSelect)((()=>({blocks:(0,r.select)("core/block-editor").getBlocks()}))),_=g.filter((e=>"gatherpress/online-event"===e.name)),v=g.filter((e=>"gatherpress/online-event"===e.name));let f;v.length>0&&(f=v[0].clientId);const E=g.filter((e=>"gatherpress/event-venue"===e.name));let h;return E.length>0&&(h=E[0].clientId),(0,t.useEffect)((()=>{_.length>0&&p?(o(!0),l({_gp_venue:[p]}),c(h)):(o(!1),u.includes(12)&&l({_gp_venue:[]}))}),[_]),D()&&(0,t.createElement)(i.PluginDocumentSettingPanel,{name:"gp-event-settings",title:(0,n.__)("Event settings","gatherpress"),initialOpen:!0,className:"gp-event-settings",icon:"nametag"},(0,t.createElement)(s.__experimentalVStack,{spacing:2},(0,t.createElement)($,null),(0,t.createElement)(s.__experimentalDivider,null),!e&&(0,t.createElement)(F,null)),(0,t.createElement)("div",null,(0,t.createElement)(s.SelectControl,{label:(0,n.__)("Online Event","gatherpress"),value:e,onChange:e=>{if("false"===e)c(f);else{const e=(0,a.createBlock)("gatherpress/online-event");m(e)}},options:[{label:"Yes",value:!0},{label:"No",value:!1}]})))}}),(0,r.dispatch)("core/edit-post").toggleEditorPanelOpened("gp-event-settings/gp-event-settings")})();