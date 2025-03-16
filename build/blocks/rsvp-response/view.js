import*as t from"@wordpress/interactivity";var e={d:(t,s)=>{for(var r in s)e.o(s,r)&&!e.o(t,r)&&Object.defineProperty(t,r,{enumerable:!0,get:s[r]})}};e.g=function(){if("object"==typeof globalThis)return globalThis;try{return this||new Function("return this")()}catch(t){if("object"==typeof window)return window}}(),e.o=(t,e)=>Object.prototype.hasOwnProperty.call(t,e);const s=(r={getContext:()=>t.getContext,getElement:()=>t.getElement,store:()=>t.store},a={},e.d(a,r),a);var r,a;function n(t,e){var s;t.posts=null!==(s=t.posts)&&void 0!==s?s:[],e&&!t.posts[e]&&(t.posts[e]={eventResponses:{attending:0,waitingList:0,notAttending:0},currentUser:{status:"no_status",guests:0,anonymous:0},rsvpSelection:"attending"})}const{state:o,actions:i}=(0,s.store)("gatherpress",{state:{posts:{}},actions:{processRsvpSelection(t){i.linkHandler(t);const e=(0,s.getElement)();if(e&&e.ref){const t=e.ref.getAttribute("data-status");if(t){const e=(0,s.getContext)(),r=e?.postId||0;n(o,r),r&&(o.posts[r].rsvpSelection=t)}}},toggleRsvpVisibility(t){t.preventDefault();const e=(0,s.getElement)(),r=e.ref.closest(".wp-block-gatherpress-rsvp-response");if("1"!==r.dataset.limitEnabled)return;const a=parseInt(r.dataset.limit,10)||8,n=r.querySelector(".gatherpress--rsvp-responses");n&&(e.ref.getAttribute("aria-label")===e.ref.dataset.showAll?(e.ref.setAttribute("aria-label",e.ref.dataset.showFewer),e.ref.textContent=e.ref.dataset.showFewer,n.querySelectorAll("[data-id].gatherpress--is-not-visible").forEach((t=>t.classList.remove("gatherpress--is-not-visible")))):(e.ref.setAttribute("aria-label",e.ref.dataset.showAll),e.ref.textContent=e.ref.dataset.showAll,n.querySelectorAll("[data-id]").forEach(((t,e)=>{e>=a?t.classList.add("gatherpress--is-not-visible"):t.classList.remove("gatherpress--is-not-visible")}))))}},callbacks:{processRsvpDropdown(){const t=(0,s.getContext)(),e=t?.postId||0,r=(0,s.getElement)(),a=r.ref.closest(".wp-block-gatherpress-rsvp-response");n(o,e);const i=JSON.parse(a.getAttribute("data-counts"));if(a.removeAttribute("data-counts"),i&&(o.posts[e]={...o.posts[e],eventResponses:{attending:i?.attending||0,waitingList:i?.waiting_list||0,notAttending:i?.not_attending||0}}),r&&r.ref&&!r.ref.hasAttribute("data-label")){const t=r.ref.textContent.trim();t&&r.ref.setAttribute("data-label",t)}const l=r.ref.parentElement,p=l?.classList||[],d=r.ref.getAttribute("data-label"),c=r.ref.getAttribute("data-status")===o.posts[e]?.rsvpSelection||"attending"===r.ref.getAttribute("data-status")&&"no_status"===o.posts[e]?.rsvpSelection,g=r.ref.closest(".wp-block-gatherpress-dropdown"),b=g.querySelector(".wp-block-gatherpress-dropdown__trigger");let f=0;if(p.contains("gatherpress--rsvp-attending")?f=o.posts[e]?.eventResponses?.attending||0:p.contains("gatherpress--rsvp-waiting-list")?f=o.posts[e]?.eventResponses?.waitingList||0:p.contains("gatherpress--rsvp-not-attending")&&(f=o.posts[e]?.eventResponses?.notAttending||0),d){const t=d.replace("%d",f);r.ref.textContent=t}if(c){const t=r.ref.textContent.trim();g.querySelectorAll("[data-status]").forEach((t=>{t.classList.remove("gatherpress--is-disabled"),t.removeAttribute("tabindex"),t.removeAttribute("aria-disabled")})),r.ref.classList.add("gatherpress--is-disabled"),r.ref.setAttribute("tabindex","-1"),r.ref.setAttribute("aira-disabled","true"),b.textContent=t}0!==f||p.contains("gatherpress--rsvp-attending")?l.classList.remove("gatherpress--is-not-visible"):l.classList.add("gatherpress--is-not-visible");const u=g.querySelectorAll(".wp-block-gatherpress-dropdown-item:not(.gatherpress--is-not-visible)");1===u.length&&u[0].classList.contains("gatherpress--rsvp-attending")&&u[0].textContent===b.textContent?(b.classList.add("gatherpress--is-disabled"),b.setAttribute("tabindex","-1")):(b.classList.remove("gatherpress--is-disabled"),b.setAttribute("tabindex","0"))},showHideToggle(){var t;const e=(0,s.getElement)(),r=(0,s.getContext)(),a=r?.postId||0,n=e.ref.closest(".wp-block-gatherpress-rsvp-response");if("1"!==n.dataset.limitEnabled)return;const i=(null!==(t=o.posts[a]?.rsvpSelection)&&void 0!==t?t:"attending").replace(/__+/g,"_").replace(/_([a-zA-Z])/g,((t,e)=>e.toUpperCase()));o.posts[a].eventResponses[i]<=(parseInt(n.dataset.limit,10)||8)?e.ref.classList.add("gatherpress--is-not-visible"):e.ref.classList.remove("gatherpress--is-not-visible");const l=e.ref.querySelector('a[role="button"]');l&&(l.setAttribute("aria-label",l.dataset.showAll),l.textContent=l.dataset.showAll)}}});