(()=>{"use strict";var e,t={7989:()=>{const e=window.wp.blocks,t=window.wp.blockEditor,a=window.wp.components,s=window.wp.i18n,r=window.wp.element,n=window.wp.data,o=[["gatherpress/modal-manager",{style:{spacing:{blockGap:"var:preset|spacing|40"}}},[["core/buttons",{align:"center",layout:{type:"flex",justifyContent:"center"},metadata:{name:(0,s._x)("Call to Action","Block name displayed in the editor","gatherpress")}},[["core/button",{text:(0,s._x)("Edit RSVP","Button label for editing RSVP","gatherpress"),tagName:"button",className:"gatherpress--open-modal",metadata:{name:(0,s._x)("RSVP Button","Block name displayed in the editor","gatherpress")}}]]],["core/group",{style:{spacing:{blockGap:"0"}}},[["core/group",{style:{spacing:{blockGap:"var:preset|spacing|20"}},layout:{type:"flex",flexWrap:"nowrap"}},[["gatherpress/icon",{icon:"yes-alt",iconSize:24}],["core/paragraph",{style:{spacing:{margin:{top:"0"},padding:{top:"0"}}},content:(0,s._x)("<strong>Attending</strong>","RSVP status indicator","gatherpress"),metadata:{name:(0,s._x)("RSVP Status","Block name displayed in the editor","gatherpress")}}]]],["gatherpress/rsvp-guest-count-display",{}]]],["gatherpress/modal",{className:"gatherpress--is-rsvp-modal",metadata:{name:(0,s._x)("RSVP Modal","Modal title in editor","gatherpress")}},[["gatherpress/modal-content",{},[["core/paragraph",{style:{spacing:{margin:{top:"0"},padding:{top:"0"}}},content:(0,s._x)("<strong>You're Attending</strong>","RSVP modal header","gatherpress"),metadata:{name:(0,s._x)("RSVP Heading","Block name displayed in the editor","gatherpress")}}],["core/paragraph",{content:(0,s.__)("To change your attendance status, simply click the <strong>Not Attending</strong> button below.","gatherpress"),metadata:{name:(0,s._x)("RSVP Info","Block name displayed in the editor","gatherpress")}}],["gatherpress/rsvp-guest-count-input",{}],["gatherpress/rsvp-anonymous-checkbox",{}],["core/buttons",{align:"left",layout:{type:"flex",justifyContent:"flex-start"},style:{spacing:{margin:{bottom:"0"},padding:{bottom:"0"}}},metadata:{name:(0,s._x)("Call to Action","Block name displayed in the editor","gatherpress")}},[["core/button",{text:(0,s._x)("Not Attending","RSVP button label for declining event attendance","gatherpress"),tagName:"button",className:"gatherpress--update-rsvp",metadata:{name:(0,s._x)("RSVP Button","Block name displayed in the editor","gatherpress")}}],["core/button",{text:(0,s._x)("Close","Button label for closing modal dialog","gatherpress"),tagName:"button",className:"is-style-outline gatherpress--close-modal",metadata:{name:(0,s._x)("Close Button","Block name displayed in the editor","gatherpress")}}]]]]]]]]]],i=[["gatherpress/modal-manager",{},[["core/buttons",{align:"center",layout:{type:"flex",justifyContent:"center"},metadata:{name:(0,s._x)("Call to Action","Block name displayed in the editor","gatherpress")}},[["core/button",{text:(0,s._x)("RSVP","Button label for editing RSVP","gatherpress"),tagName:"button",className:"gatherpress--open-modal",metadata:{name:(0,s._x)("RSVP Button","Block name displayed in the editor","gatherpress")}}]]],["gatherpress/modal",{className:"gatherpress--is-rsvp-modal",metadata:{name:(0,s._x)("RSVP Modal","Modal title in editor","gatherpress")}},[["gatherpress/modal-content",{},[["core/paragraph",{style:{spacing:{margin:{top:"0"},padding:{top:"0"}}},content:(0,s._x)("<strong>RSVP to this event</strong>","RSVP modal header","gatherpress"),metadata:{name:(0,s._x)("RSVP Heading","Block name displayed in the editor","gatherpress")}}],["core/paragraph",{content:(0,s.__)("To set your attendance status, simply click the <strong>Attend</strong> button below.","gatherpress"),metadata:{name:(0,s._x)("RSVP Info","Block name displayed in the editor","gatherpress")}}],["gatherpress/rsvp-anonymous-checkbox",{}],["core/buttons",{align:"left",layout:{type:"flex",justifyContent:"flex-start"},style:{spacing:{margin:{bottom:"0"},padding:{bottom:"0"}}},metadata:{name:(0,s._x)("Call to Action","Block name displayed in the editor","gatherpress")}},[["core/button",{text:(0,s._x)("Attend","RSVP button label for confirming event attendance","gatherpress"),tagName:"button",className:"gatherpress--update-rsvp",metadata:{name:(0,s._x)("RSVP Button","Block name displayed in the editor","gatherpress")}}],["core/button",{text:(0,s._x)("Close","Button label for closing modal dialog","gatherpress"),tagName:"button",className:"is-style-outline gatherpress--close-modal",metadata:{name:(0,s._x)("Close Button","Block name displayed in the editor","gatherpress")}}]]]]]]],["gatherpress/modal",{className:"gatherpress--is-login-modal",metadata:{name:(0,s._x)("Login Modal","Block title for the login modal","gatherpress")}},[["gatherpress/modal-content",{},[["core/paragraph",{style:{spacing:{margin:{top:"0"},padding:{top:"0"}}},content:(0,s._x)("<strong>Login Required</strong>","Login modal header","gatherpress"),metadata:{name:(0,s._x)("Login Heading","Block name displayed in the editor","gatherpress")}}],["core/paragraph",{content:(0,s.__)('This action requires an account. Please <a href="#gatherpress-login-url">Login</a> to RSVP to this event.',"gatherpress"),className:"gatherpress--has-login-url",metadata:{name:(0,s._x)("Login Info","Block name displayed in the editor","gatherpress")}}],["core/paragraph",{content:(0,s.__)('Don\'t have an account? <a href="#gatherpress-registration-url">Register here</a> to create one.',"gatherpress"),className:"gatherpress--has-registration-url",metadata:{name:(0,s._x)("Register Info","Block name displayed in the editor","gatherpress")}}],["core/buttons",{align:"left",layout:{type:"flex",justifyContent:"flex-start"},style:{spacing:{margin:{bottom:"0"},padding:{bottom:"0"}}},metadata:{name:(0,s._x)("Call to Action","Block name displayed in the editor","gatherpress")}},[["core/button",{text:(0,s._x)("Close","Button label for closing modal dialog","gatherpress"),tagName:"button",className:"gatherpress--close-modal",metadata:{name:(0,s._x)("Close Button","Block name displayed in the editor","gatherpress")}}]]]]]]]]]],l=[["gatherpress/modal-manager",{style:{spacing:{blockGap:"var:preset|spacing|40"}}},[["core/buttons",{align:"center",layout:{type:"flex",justifyContent:"center"},metadata:{name:(0,s._x)("Call to Action","Block name displayed in the editor","gatherpress")}},[["core/button",{text:(0,s._x)("Edit RSVP","Button label for editing RSVP","gatherpress"),tagName:"button",className:"gatherpress--open-modal",metadata:{name:(0,s._x)("RSVP Button","Block name displayed in the editor","gatherpress")}}]]],["core/group",{style:{spacing:{blockGap:"var:preset|spacing|20"}},layout:{type:"flex",flexWrap:"nowrap"}},[["gatherpress/icon",{icon:"dismiss",iconSize:24}],["core/paragraph",{style:{spacing:{margin:{top:"0"},padding:{top:"0"}}},content:(0,s._x)("<strong>Not Attending</strong>","RSVP status indicator","gatherpress"),metadata:{name:(0,s._x)("RSVP Status","Block name displayed in the editor","gatherpress")}}]]],["gatherpress/modal",{className:"gatherpress--is-rsvp-modal",metadata:{name:(0,s._x)("RSVP Modal","Modal title in editor","gatherpress")}},[["gatherpress/modal-content",{},[["core/paragraph",{style:{spacing:{margin:{top:"0"},padding:{top:"0"}}},content:(0,s._x)("<strong>You're Not Attending</strong>","RSVP modal header","gatherpress"),metadata:{name:(0,s._x)("RSVP Heading","Block name displayed in the editor","gatherpress")}}],["core/paragraph",{content:(0,s.__)("To change your attendance status, simply click the <strong>Attending</strong> button below.","gatherpress"),metadata:{name:(0,s._x)("RSVP Info","Block name displayed in the editor","gatherpress")}}],["gatherpress/rsvp-anonymous-checkbox",{}],["core/buttons",{align:"left",layout:{type:"flex",justifyContent:"flex-start"},style:{spacing:{margin:{bottom:"0"},padding:{bottom:"0"}}},metadata:{name:(0,s._x)("Call to Action","Block name displayed in the editor","gatherpress")}},[["core/button",{text:(0,s._x)("Attending","RSVP button label for confirming event attendance","gatherpress"),tagName:"button",className:"gatherpress--update-rsvp",metadata:{name:(0,s._x)("RSVP Button","Block name displayed in the editor","gatherpress")}}],["core/button",{text:(0,s._x)("Close","Button label for closing modal dialog","gatherpress"),tagName:"button",className:"is-style-outline gatherpress--close-modal",metadata:{name:(0,s._x)("Close Button","Block name displayed in the editor","gatherpress")}}]]]]]]]]]],p={no_status:i,attending:o,waiting_list:[["gatherpress/modal-manager",{style:{spacing:{blockGap:"var:preset|spacing|40"}}},[["core/buttons",{align:"center",layout:{type:"flex",justifyContent:"center"},metadata:{name:(0,s._x)("Call to Action","Block name displayed in the editor","gatherpress")}},[["core/button",{text:(0,s._x)("Edit RSVP","Button label for editing RSVP","gatherpress"),tagName:"button",className:"gatherpress--open-modal",metadata:{name:(0,s._x)("RSVP Button","Block name displayed in the editor","gatherpress")}}]]],["core/group",{style:{spacing:{blockGap:"var:preset|spacing|20"}},layout:{type:"flex",flexWrap:"nowrap"}},[["gatherpress/icon",{icon:"clock",iconSize:24}],["core/paragraph",{style:{spacing:{margin:{top:"0"},padding:{top:"0"}}},content:(0,s._x)("<strong>Waiting List</strong>","RSVP status indicator","gatherpress"),metadata:{name:(0,s._x)("RSVP Status","Block name displayed in the editor","gatherpress")}}]]],["gatherpress/modal",{className:"gatherpress--is-rsvp-modal",metadata:{name:(0,s._x)("RSVP Modal","Block name displayed in the editor","gatherpress")}},[["gatherpress/modal-content",{},[["core/paragraph",{style:{spacing:{margin:{top:"0"},padding:{top:"0"}}},content:(0,s._x)("<strong>You're Wait Listed</strong>","RSVP modal header","gatherpress"),metadata:{name:(0,s._x)("RSVP Heading","Block name displayed in the editor","gatherpress")}}],["core/paragraph",{content:(0,s.__)("To change your attendance status, simply click the <strong>Not Attending</strong> button below.","gatherpress"),metadata:{name:(0,s._x)("RSVP Info","Block name displayed in the editor","gatherpress")}}],["gatherpress/rsvp-anonymous-checkbox",{}],["core/buttons",{align:"left",layout:{type:"flex",justifyContent:"flex-start"},style:{spacing:{margin:{bottom:"0"},padding:{bottom:"0"}}},metadata:{name:(0,s._x)("Call to Action","Block name displayed in the editor","gatherpress")}},[["core/button",{text:(0,s._x)("Not Attending","RSVP button label for declining event attendance","gatherpress"),tagName:"button",className:"gatherpress--update-rsvp",metadata:{name:(0,s._x)("RSVP Button","Block name displayed in the editor","gatherpress")}}],["core/button",{text:(0,s._x)("Close","Button label for closing modal dialog","gatherpress"),tagName:"button",className:"is-style-outline gatherpress--close-modal",metadata:{name:(0,s._x)("Close Button","Block name displayed in the editor","gatherpress")}}]]]]]]]]]],not_attending:l,past:[["core/buttons",{align:"center",layout:{type:"flex",justifyContent:"center"},metadata:{name:(0,s._x)("Call to Action","Block name displayed in the editor","gatherpress")}},[["core/button",{text:(0,s._x)("Past Event","Button label for past RSVP","gatherpress"),tagName:"button",className:"gatherpress--is-disabled",metadata:{name:(0,s._x)("RSVP Button","Block name displayed in the editor","gatherpress")}}]]]]},d=window.ReactJSXRuntime;function g(t){return t.map((([t,a,s])=>(0,e.createBlock)(t,a,g(s||[]))))}const c=JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"gatherpress/rsvp","version":"2.0.0","title":"RSVP","category":"gatherpress","icon":"insert","example":{},"description":"Enables members to easily confirm their attendance for an event.","usesContext":["postId","queryId"],"attributes":{"serializedInnerBlocks":{"type":"string","default":"[]"},"selectedStatus":{"type":"string","default":"no_status"}},"supports":{"gatherpress":{"blockGuard":true,"postIdOverride":true},"html":false,"interactivity":true},"textdomain":"gatherpress","editorScript":"file:./index.js","style":"file:./style-index.css","viewScriptModule":"file:./view.js"}');(0,e.registerBlockType)(c,{edit:({attributes:o,setAttributes:i,clientId:l})=>{const{serializedInnerBlocks:c="{}",selectedStatus:m}=o,h=(0,t.useBlockProps)(),{replaceInnerBlocks:u}=(0,n.useDispatch)(t.store),y=(0,n.useSelect)((e=>e(t.store).getBlocks(l)),[l]),x=(0,r.useCallback)(((t,a,s)=>{const r=JSON.parse(c||"{}"),n=(0,e.serialize)(s),o={...r,[t]:n};delete o[a],i({serializedInnerBlocks:JSON.stringify(o)})}),[c,i]),b=(0,r.useCallback)((t=>{const a=JSON.parse(c||"{}")[t];a&&a.length>0&&u(l,(0,e.parse)(a,{}))}),[l,u,c]);return(0,r.useEffect)((()=>{const t=()=>{const t=JSON.parse(c||"{}"),a=Object.keys(p).reduce(((a,s)=>{if(t[s])return a[s]=t[s],a;if(s!==m){const t=g(p[s]);a[s]=(0,e.serialize)(t)}return a}),{...t});i({serializedInnerBlocks:JSON.stringify(a)})};setTimeout((()=>{t()}),0)}),[c,i,m]),(0,d.jsxs)(d.Fragment,{children:[(0,d.jsx)(t.InspectorControls,{children:(0,d.jsxs)(a.PanelBody,{title:(0,s.__)("RSVP Block Settings","gatherpress"),children:[(0,d.jsx)("p",{children:(0,s.__)("Select an RSVP status to edit how this block appears for users with that status.","gatherpress")}),(0,d.jsx)(a.SelectControl,{label:(0,s.__)("Edit Block Status","gatherpress"),value:m,options:[{label:(0,s.__)("No Response (Default)","gatherpress"),value:"no_status"},{label:(0,s.__)("Attending","gatherpress"),value:"attending"},{label:(0,s.__)("Waiting List","gatherpress"),value:"waiting_list"},{label:(0,s.__)("Not Attending","gatherpress"),value:"not_attending"},{label:(0,s.__)("Past Event","gatherpress"),value:"past"}],onChange:e=>{b(e),i({selectedStatus:e}),x(m,e,y)}})]})}),(0,d.jsx)("div",{...h,children:(0,d.jsx)(t.InnerBlocks,{template:p[m]})})]})},save:()=>(0,d.jsx)("div",{...t.useBlockProps.save(),children:(0,d.jsx)(t.InnerBlocks.Content,{})})})}},a={};function s(e){var r=a[e];if(void 0!==r)return r.exports;var n=a[e]={exports:{}};return t[e](n,n.exports,s),n.exports}s.m=t,e=[],s.O=(t,a,r,n)=>{if(!a){var o=1/0;for(d=0;d<e.length;d++){for(var[a,r,n]=e[d],i=!0,l=0;l<a.length;l++)(!1&n||o>=n)&&Object.keys(s.O).every((e=>s.O[e](a[l])))?a.splice(l--,1):(i=!1,n<o&&(o=n));if(i){e.splice(d--,1);var p=r();void 0!==p&&(t=p)}}return t}n=n||0;for(var d=e.length;d>0&&e[d-1][2]>n;d--)e[d]=e[d-1];e[d]=[a,r,n]},s.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),(()=>{var e={3662:0,1394:0};s.O.j=t=>0===e[t];var t=(t,a)=>{var r,n,[o,i,l]=a,p=0;if(o.some((t=>0!==e[t]))){for(r in i)s.o(i,r)&&(s.m[r]=i[r]);if(l)var d=l(s)}for(t&&t(a);p<o.length;p++)n=o[p],s.o(e,n)&&e[n]&&e[n][0](),e[n]=0;return s.O(d)},a=globalThis.webpackChunkgatherpress=globalThis.webpackChunkgatherpress||[];a.forEach(t.bind(null,0)),a.push=t.bind(null,a.push.bind(a))})();var r=s.O(void 0,[1394],(()=>s(7989)));r=s.O(r)})();