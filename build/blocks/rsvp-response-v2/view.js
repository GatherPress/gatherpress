import*as t from"@wordpress/interactivity";var e={d:(t,s)=>{for(var n in s)e.o(s,n)&&!e.o(t,n)&&Object.defineProperty(t,n,{enumerable:!0,get:s[n]})},o:(t,e)=>Object.prototype.hasOwnProperty.call(t,e)};const s=(n={getContext:()=>t.getContext,getElement:()=>t.getElement,store:()=>t.store},o={},e.d(o,n),o);var n,o;const{state:r,callbacks:a,actions:i}=(0,s.store)("gatherpress",{state:{posts:{}},actions:{processRsvpSelection(t){i.linkHandler(t);const e=(0,s.getElement)();if(e&&e.ref){const t=e.ref.getAttribute("data-status");if(t){const e=(0,s.getContext)();e&&(r.posts[e.postId].rsvpSelection=t)}}}},callbacks:{processRsvpDropdown(){a.initPostContext();const t=(0,s.getElement)(),e=(0,s.getContext)(),n=e?.postId||0;if(t&&t.ref&&!t.ref.hasAttribute("data-label")){const e=t.ref.textContent.trim();e&&t.ref.setAttribute("data-label",e)}const o=t.ref.parentElement,i=o?.classList||[],p=t.ref.getAttribute("data-label"),c=r.posts[n]?.rsvpSelection,l=t.ref.closest(".wp-block-gatherpress-dropdown");let d=0;if(i.contains("gatherpress--rsvp-attending")?d=r.posts[n]?.eventResponses?.attending||0:i.contains("gatherpress--rsvp-waiting-list")?d=r.posts[n]?.eventResponses?.waitingList||0:i.contains("gatherpress--rsvp-not-attending")&&(d=r.posts[n]?.eventResponses?.notAttending||0),p){const e=p.replace("%d",d);t.ref.textContent=e}c&&l.querySelectorAll("[data-status]").forEach((t=>{if(t.getAttribute("data-status")===c){t.classList.add("gatherpress--is-disabled");const e=t.textContent.trim(),s=l.querySelector(".wp-block-gatherpress-dropdown__trigger");s&&(s.textContent=e)}else t.classList.remove("gatherpress--is-disabled")}))},initPostContext(){const t=(0,s.getContext)(),e=function(){if("object"==typeof GatherPress)return"eventDetails.responses".split(".").reduce(((t,e)=>t&&t[e]),GatherPress)}();r.posts[t?.postId]||(r.posts[t?.postId]={eventResponses:{attending:e?.attending?.count||0,waitingList:e?.waiting_list?.count||0,notAttending:e?.not_attending?.count||0},rsvpSelection:"attending"})}}});