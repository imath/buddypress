(()=>{"use strict";var e={n:t=>{var s=t&&t.__esModule?()=>t.default:()=>t;return e.d(s,{a:s}),s},d:(t,s)=>{for(var o in s)e.o(s,o)&&!e.o(t,o)&&Object.defineProperty(t,o,{enumerable:!0,get:s[o]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t)};const t=window.wp.domReady;var s=e.n(t);const o=window.bp.noticesController;var r=e.n(o);class n{constructor(){this.container=document.querySelector("#bp-notices-container"),this.bubble=document.querySelector("#wp-admin-bar-bp-notifications")}catchEvents(e){if(e.target.dataset.bpDismissId){e.preventDefault();const t=parseInt(e.target.dataset.bpDismissId,10),s=document.querySelector("article#notice-"+t+" .bp-notice-request-status");s.classList.remove("error"),s.querySelector("p").innerHTML="",r()({action:"dismiss/"+t,method:"POST"}).then((s=>{if(!0===s.dismissed){e.target.closest("article#notice-"+t).remove();const s=this.bubble.querySelector(".count");s.innerHTML=parseInt(s.innerHTML,10)-1}})).catch((e=>{s.querySelector("p").innerHTML=e,s.classList.add("error")}))}}start(){this.container.addEventListener("click",this.catchEvents.bind(this),!1),void 0===this.container.popover?(this.container.remove(),console.warn("Your browser does not support the Popover API, please update it to its latest version to enjoy BuddyPress Notices."),document.querySelector("#bp-notices-toggler").addEventListener("click",(e=>{e.preventDefault();let t="";t="BUTTON"!==e.target.nodeName?e.target.closest("#bp-notices-toggler").dataset.bpFallbackUrl:e.target.dataset.bpFallbackUrl,location.href=t}))):(this.container.classList.remove("no-popover-support"),this.container.addEventListener("toggle",(e=>{"open"===e.newState?this.bubble.classList.contains("is-open")||this.bubble.classList.add("is-open"):this.bubble.classList.remove("is-open")})))}}s()((function(){(new n).start()}))})();