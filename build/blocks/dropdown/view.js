import*as e from"@wordpress/interactivity";var t={d:(e,r)=>{for(var s in r)t.o(r,s)&&!t.o(e,s)&&Object.defineProperty(e,s,{enumerable:!0,get:r[s]})}};t.g=function(){if("object"==typeof globalThis)return globalThis;try{return this||new Function("return this")()}catch(e){if("object"==typeof window)return window}}(),t.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t);const r=(s={getElement:()=>e.getElement,store:()=>e.store},n={},t.d(n,s),n);var s,n;const{actions:o}=(0,r.store)("gatherpress",{actions:{preventDefault(e){e.preventDefault()},linkHandler(e){o.preventDefault(e);const t=(0,r.getElement)(),s=t.ref.closest(".wp-block-gatherpress-dropdown");if(s&&"select"===s.dataset.dropdownMode){const e=s.querySelector(".wp-block-gatherpress-dropdown__menu"),r=s.querySelector(".wp-block-gatherpress-dropdown__trigger"),n=t.ref.closest(".wp-block-gatherpress-dropdown-item");if(n){const t=n.querySelector("a");if(t&&t.classList.contains("gatherpress--is-disabled"))return;t&&(t.classList.add("gatherpress--is-disabled"),t.setAttribute("tabindex","-1"),t.setAttribute("aira-disabled","true")),e.querySelectorAll(".wp-block-gatherpress-dropdown-item").forEach((e=>{const t=e.querySelector("a");t&&e!==n&&(t.classList.remove("gatherpress--is-disabled"),t.removeAttribute("tabindex"),t.removeAttribute("aria-disabled"))})),r&&t&&(r.textContent=t.textContent.trim()),e&&(e.classList.remove("gatherpress--is-visible"),r.setAttribute("aria-expanded","false"))}}},toggleDropdown(e){o.preventDefault(e);const s=(0,r.getElement)(),n=s.ref.parentElement.querySelector(".wp-block-gatherpress-dropdown__menu"),a=s.ref.parentElement.querySelector(".wp-block-gatherpress-dropdown__trigger");if(!n||!a)return;const i=n.classList.toggle("gatherpress--is-visible");a.setAttribute("aria-expanded",i?"true":"false");const c=[a,...n.querySelectorAll(["a[href]:not(.gatherpress--is-disabled)"].join(","))];i?(a.focus(),s.ref.cleanupFocusTrap=function(e){if(!e||0===e.length)return()=>{};const r=e.filter((e=>null!==e.offsetParent&&"hidden"!==t.g.window.getComputedStyle(e).visibility&&"0"!==t.g.window.getComputedStyle(e).opacity));if(0===r.length)return()=>{};const s=r[0],n=r[r.length-1],o=e=>{"Tab"===e.key&&(e.shiftKey&&t.g.document.activeElement===s?(e.preventDefault(),n.focus()):e.shiftKey||t.g.document.activeElement!==n||(e.preventDefault(),s.focus()))},a=e=>{"Escape"===e.key&&i()},i=()=>{t.g.document.removeEventListener("keydown",o),t.g.document.removeEventListener("keydown",a)};return t.g.document.addEventListener("keydown",o),t.g.document.addEventListener("keydown",a),i}(c),s.ref.cleanupCloseHandlers=function(e,r,s){const n=e=>{e.classList.remove("gatherpress--is-visible"),s(e)},o=r=>{"Escape"===r.key&&t.g.document.querySelectorAll(`${e}.gatherpress--is-visible`).forEach((e=>n(e)))},a=r=>{t.g.document.querySelectorAll(`${e}.gatherpress--is-visible`).forEach((e=>{const t=e.querySelector(".wp-block-gatherpress-dropdown__menu");e.contains(r.target)&&!t.contains(r.target)&&n(e)}))};return t.g.document.addEventListener("keydown",o),t.g.document.addEventListener("click",a),()=>{t.g.document.removeEventListener("keydown",o),t.g.document.removeEventListener("click",a)}}(".wp-block-gatherpress-dropdown__menu",0,(e=>{e.classList.remove("gatherpress--is-visible"),a.setAttribute("aria-expanded","false"),"function"==typeof s.ref.cleanupFocusTrap&&s.ref.cleanupFocusTrap()}))):("function"==typeof s.ref.cleanupFocusTrap&&s.ref.cleanupFocusTrap(),"function"==typeof s.ref.cleanupCloseHandlers&&s.ref.cleanupCloseHandlers())}}});