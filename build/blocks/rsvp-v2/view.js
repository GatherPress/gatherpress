import*as t from"@wordpress/interactivity";var e={d:(t,s)=>{for(var n in s)e.o(s,n)&&!e.o(t,n)&&Object.defineProperty(t,n,{enumerable:!0,get:s[n]})},o:(t,e)=>Object.prototype.hasOwnProperty.call(t,e)};const s=(n={getContext:()=>t.getContext,getElement:()=>t.getElement,store:()=>t.store},o={},e.d(o,n),o);var n,o;function r(t){if("object"==typeof GatherPress)return t.split(".").reduce(((t,e)=>t&&t[e]),GatherPress)}const{state:a,actions:i}=(0,s.store)("gatherpress",{actions:{updateRsvp(t){var e,n;t.preventDefault();const o=(0,s.getElement)(),l=(0,s.getContext)(),u=l?.postId||0,c=null!==(e=o.ref.getAttribute("data-set-status"))&&void 0!==e?e:"",p=null!==(n=a.posts[u].userRsvpStatus)&&void 0!==n?n:r("eventDetails.currentUser.status");let d="not_attending";["attending","waiting_list","not_attending"].includes(c)?d=c:["not_attending","no_status"].includes(p)&&(d="attending"),fetch(r("urls.eventApiUrl")+"/rsvp",{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":r("misc.nonce")},body:JSON.stringify({post_id:u,status:d,guests:0,anonymous:0})}).then((t=>t.json())).then((t=>{t.success&&(a.activePostId=u,a.posts[u]={...a.posts[u],eventResponses:{attending:t.responses.attending.count,waitingList:t.responses.waiting_list.count,notAttending:t.responses.not_attending.count},userRsvpStatus:t.status,rsvpSelection:t.status},i.closeModal(null,o.ref))})).catch((()=>{}))}},callbacks:{renderRsvpBlock(){var t;const e=(0,s.getElement)(),n=(0,s.getContext)(),o=null!==(t=a.posts[n.postId]?.userRsvpStatus)&&void 0!==t?t:r("eventDetails.currentUser.status");e.ref.querySelectorAll("[data-rsvp-status]").forEach((t=>{const e=t.parentNode;t.getAttribute("data-rsvp-status")===o?(t.style.display="",e.insertBefore(t,e.firstChild)):t.style.display="none"}))}}});