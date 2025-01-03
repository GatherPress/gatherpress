import*as t from"@wordpress/interactivity";var e={d:(t,s)=>{for(var n in s)e.o(s,n)&&!e.o(t,n)&&Object.defineProperty(t,n,{enumerable:!0,get:s[n]})}};e.g=function(){if("object"==typeof globalThis)return globalThis;try{return this||new Function("return this")()}catch(t){if("object"==typeof window)return window}}(),e.o=(t,e)=>Object.prototype.hasOwnProperty.call(t,e);const s=(n={getContext:()=>t.getContext,getElement:()=>t.getElement,store:()=>t.store},o={},e.d(o,n),o);var n,o;function r(t){if("object"==typeof GatherPress)return t.split(".").reduce(((t,e)=>t&&t[e]),GatherPress)}function a(t,e){const s=r("eventDetails");t.posts[e]||(t.posts[e]={eventResponses:{attending:s.responses.attending.count||0,waitingList:s.responses.waiting_list.count||0,notAttending:s.responses.not_attending.count||0},currentUser:{status:s.currentUser?.status||"no_status",guests:s.currentUser?.guests||0,anonymous:s.currentUser?.anonymous||0},rsvpSelection:"attending"})}function u(t,e,s=null,n=null){fetch(r("urls.eventApiUrl")+"/rsvp",{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":r("misc.nonce")},body:JSON.stringify({post_id:t,status:e.status,guests:e.guests,anonymous:e.anonymous})}).then((t=>t.json())).then((e=>{e.success&&(s&&(s.activePostId=t,s.posts[t]={...s.posts[t],eventResponses:{attending:e.responses.attending.count,waitingList:e.responses.waiting_list.count,notAttending:e.responses.not_attending.count},currentUser:{status:e.status,guests:e.guests},rsvpSelection:e.status}),"function"==typeof n&&n(e))})).catch((()=>{}))}const{state:i,actions:c}=(0,s.store)("gatherpress",{actions:{updateGuestCount(){const t=(0,s.getElement)(),e=(0,s.getContext)().postId||0,n=i.posts[e].currentUser;n.guests=t.ref.value,a(i,e),u(e,n,i,(()=>{setTimeout((()=>{t.ref.focus()}),1)}))},updateRsvp(t=null){var e;t&&t.preventDefault();const n=(0,s.getElement)(),o=(0,s.getContext)(),r=o?.postId||0,a=null!==(e=n.ref.getAttribute("data-set-status"))&&void 0!==e?e:"",l=i.posts[r].currentUser.status;let p="not_attending";t?["attending","waiting_list","not_attending"].includes(a)?p=a:["not_attending","no_status"].includes(l)&&(p="attending"):p=l,u(r,{status:p,guests:i.posts[r].currentUser.guests,anonymous:0},i,(()=>{const t=n.ref.closest("[data-rsvp-status]"),e=t.getAttribute("data-rsvp-status"),s=t.closest(".wp-block-gatherpress-rsvp-v2");if(["not_attending","no_status"].includes(e)){const t=s.querySelector('[data-rsvp-status="attending"] .gatherpress--update-rsvp');c.openModal(null,t)}setTimeout((()=>{c.closeModal(null,n.ref)}),1)}))}},callbacks:{setGuestCount(){const t=(0,s.getElement)(),e=(0,s.getContext)().postId||0;a(i,e),t.ref.value=i.posts[e].currentUser.guests},renderRsvpBlock(){var t;const e=(0,s.getElement)(),n=(0,s.getContext)(),o=null!==(t=i.posts[n.postId]?.currentUser?.status)&&void 0!==t?t:r("eventDetails.currentUser.status");e.ref.querySelectorAll("[data-rsvp-status]").forEach((t=>{const e=t.parentNode;t.getAttribute("data-rsvp-status")===o?(t.style.display="",e.insertBefore(t,e.firstChild)):t.style.display="none"}))},updateGuestCountDisplay(){const t=(0,s.getContext)(),e=t?.postId||0;a(i,t);const n=parseInt(i.posts[e]?.currentUser?.guests||0,10),o=(0,s.getElement)(),r=o.ref.getAttribute("data-guest-singular"),u=o.ref.getAttribute("data-guest-plural");let c="";0<n&&(c=1===n?r.replace("%d",n):u.replace("%d",n)),o.ref.textContent=c}}});