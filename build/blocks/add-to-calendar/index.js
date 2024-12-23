(()=>{"use strict";var e,r={6892:()=>{const e=window.wp.blocks,r=window.wp.i18n,t=window.wp.blockEditor,s=window.wp.components,n=window.ReactJSXRuntime,i=e=>{const{isSelected:r}=e,t=r?"none":"block";return(0,n.jsxs)("div",{style:{position:"relative",zIndex:"0"},children:[e.children,(0,n.jsx)("div",{style:{position:"absolute",top:"0",right:"0",bottom:"0",left:"0",display:t}})]})},o=JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":2,"name":"gatherpress/add-to-calendar","version":"1.0.2","title":"Add to Calendar","category":"gatherpress","icon":"calendar","example":{},"description":"Allows a member to add an event to their preferred calendar.","attributes":{"blockId":{"type":"string"}},"supports":{"html":false},"textdomain":"gatherpress","editorScript":"file:./index.js","style":"file:./style-index.css","viewScript":"file:./add-to-calendar.js","render":"file:./render.php"}');(0,e.registerBlockType)(o,{edit:()=>{const e=(0,t.useBlockProps)();return(0,n.jsx)("div",{...e,children:(0,n.jsx)(i,{children:(0,n.jsxs)(s.Flex,{justify:"normal",align:"center",gap:"4",children:[(0,n.jsx)(s.FlexItem,{display:"flex",className:"gatherpress-event-date__icon",children:(0,n.jsx)(s.Icon,{icon:"calendar"})}),(0,n.jsx)(s.FlexItem,{children:(0,n.jsx)("a",{href:"#",children:(0,r.__)("Add to calendar","gatherpress")})})]})})})},save:()=>null})}},t={};function s(e){var n=t[e];if(void 0!==n)return n.exports;var i=t[e]={exports:{}};return r[e](i,i.exports,s),i.exports}s.m=r,e=[],s.O=(r,t,n,i)=>{if(!t){var o=1/0;for(c=0;c<e.length;c++){t=e[c][0],n=e[c][1],i=e[c][2];for(var a=!0,l=0;l<t.length;l++)(!1&i||o>=i)&&Object.keys(s.O).every((e=>s.O[e](t[l])))?t.splice(l--,1):(a=!1,i<o&&(o=i));if(a){e.splice(c--,1);var d=n();void 0!==d&&(r=d)}}return r}i=i||0;for(var c=e.length;c>0&&e[c-1][2]>i;c--)e[c]=e[c-1];e[c]=[t,n,i]},s.o=(e,r)=>Object.prototype.hasOwnProperty.call(e,r),(()=>{var e={181:0,129:0};s.O.j=r=>0===e[r];var r=(r,t)=>{var n,i,o=t[0],a=t[1],l=t[2],d=0;if(o.some((r=>0!==e[r]))){for(n in a)s.o(a,n)&&(s.m[n]=a[n]);if(l)var c=l(s)}for(r&&r(t);d<o.length;d++)i=o[d],s.o(e,i)&&e[i]&&e[i][0](),e[i]=0;return s.O(c)},t=self.webpackChunkgatherpress=self.webpackChunkgatherpress||[];t.forEach(r.bind(null,0)),t.push=r.bind(null,t.push.bind(t))})();var n=s.O(void 0,[129],(()=>s(6892)));n=s.O(n)})();