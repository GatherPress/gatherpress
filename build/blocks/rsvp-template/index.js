(()=>{"use strict";const e=window.wp.blocks,s=window.wp.blockEditor,t=window.wp.i18n,n=window.wp.data,o=window.ReactJSXRuntime,r=[["core/avatar"],["core/comment-author-name"]],c=({response:e,blocks:t})=>{const{children:n,...c}=(0,s.useInnerBlocksProps)({},{template:r});return(0,o.jsx)("div",{...c,children:n})},i=({responses:e,blocks:t})=>(0,o.jsx)(o.Fragment,{children:e&&e.map((({commentId:e,...n},r)=>(0,o.jsx)(s.BlockContextProvider,{value:{commentId:e<0?null:e},children:(0,o.jsx)(c,{response:{commentId:e,...n},blocks:t})},n.commentId||r)))});(0,e.registerBlockType)("gatherpress/rsvp-template",{edit:({clientId:e,context:{postId:r}})=>{const c=(0,s.useBlockProps)(),l=function(){if("object"==typeof GatherPress)return"eventDetails.responses".split(".").reduce(((e,s)=>e&&e[s]),GatherPress)}(),{blocks:d}=(0,n.useSelect)((t=>{const{getBlocks:n}=t(s.store);return{blocks:n(e)}}),[e]);return l.attending.count?(0,o.jsx)(i,{responses:l.attending.responses,blocks:d}):(0,o.jsx)("p",{...c,children:(0,t.__)("No one is attending this event yet.","gatherpress")})},save:()=>(0,o.jsx)(s.InnerBlocks.Content,{})})})();