import{$ as e,$t as t,B as n,Bt as r,Ct as i,Ft as a,K as o,Kt as s,Lt as c,Nt as l,Q as u,Rt as d,St as f,V as p,Vt as m,W as h,Wt as g,Xt as _,at as v,cn as y,d as b,dt as x,et as S,fn as C,hn as w,ht as T,jt as E,l as D,nt as O,ot as k,q as A,rt as j,sn as M,st as N}from"./_plugin-vue_export-helper-BRaiA-U2.js";import{d as P,f as F,l as ee,o as te,s as I}from"./light-C6m6bYe2.js";import{f as ne,l as L,o as R,t as z,u as B}from"./Scrollbar-DHbiYH8G.js";import{i as re,t as ie}from"./fade-in.cssr-DOjf-8Ff.js";import{C as ae,D as oe,O as se,S as ce,T as le,a as ue,b as de,c as V,d as fe,g as pe,h as me,m as he,p as ge,r as _e,s as H,t as ve,u as ye,w as be,x as xe,y as Se}from"./light-RG8NiEdA.js";import{t as Ce}from"./Button-BR4zNRVZ.js";import{B as we,I as U,L as Te,R as Ee,j as De,z as W}from"./index-RY1IsKbG.js";var G=y(null);function K(e){if(e.clientX>0||e.clientY>0)G.value={x:e.clientX,y:e.clientY};else{let{target:t}=e;if(t instanceof Element){let{left:e,top:n,width:r,height:i}=t.getBoundingClientRect();G.value=e>0||n>0?{x:e+r/2,y:n+i/2}:{x:0,y:0}}else G.value=null}}var q=0,Oe=!0;function ke(){if(!se)return M(y(null));q===0&&B(`click`,document,K,!0);let e=()=>{q+=1};return(Oe&&=oe())?(r(e),m(()=>{--q,q===0&&L(`click`,document,K,!0)})):e(),M(G)}var Ae=y(void 0),J=0;function je(){Ae.value=Date.now()}var Me=!0;function Ne(e){if(!se)return M(y(!1));let t=y(!1),n=null;function i(){n!==null&&window.clearTimeout(n)}function a(){i(),t.value=!0,n=window.setTimeout(()=>{t.value=!1},e)}J===0&&B(`click`,window,je,!0);let o=()=>{J+=1,B(`click`,window,a,!0)};return(Me&&=oe())?(r(o),m(()=>{--J,J===0&&L(`click`,window,je,!0),L(`click`,window,a,!0),i()})):o(),M(t)}var Y=S(`card-content`,`
 flex: 1;
 min-width: 0;
 box-sizing: border-box;
 padding: 0 var(--n-padding-left) var(--n-padding-bottom) var(--n-padding-left);
 font-size: var(--n-font-size);
`),Pe=e([S(`card`,`
 font-size: var(--n-font-size);
 line-height: var(--n-line-height);
 display: flex;
 flex-direction: column;
 width: 100%;
 box-sizing: border-box;
 position: relative;
 border-radius: var(--n-border-radius);
 background-color: var(--n-color);
 color: var(--n-text-color);
 word-break: break-word;
 transition: 
 color .3s var(--n-bezier),
 background-color .3s var(--n-bezier),
 box-shadow .3s var(--n-bezier),
 border-color .3s var(--n-bezier);
 `,[u({background:`var(--n-color-modal)`}),j(`hoverable`,[e(`&:hover`,`box-shadow: var(--n-box-shadow);`)]),j(`content-segmented`,[e(`>`,[S(`card-content`,`
 padding-top: var(--n-padding-bottom);
 `),O(`content-scrollbar`,[e(`>`,[S(`scrollbar-container`,[e(`>`,[S(`card-content`,`
 padding-top: var(--n-padding-bottom);
 `)])])])])])]),j(`content-soft-segmented`,[e(`>`,[S(`card-content`,`
 margin: 0 var(--n-padding-left);
 padding: var(--n-padding-bottom) 0;
 `),O(`content-scrollbar`,[e(`>`,[S(`scrollbar-container`,[e(`>`,[S(`card-content`,`
 margin: 0 var(--n-padding-left);
 padding: var(--n-padding-bottom) 0;
 `)])])])])])]),j(`footer-segmented`,[e(`>`,[O(`footer`,`
 padding-top: var(--n-padding-bottom);
 `)])]),j(`footer-soft-segmented`,[e(`>`,[O(`footer`,`
 padding: var(--n-padding-bottom) 0;
 margin: 0 var(--n-padding-left);
 `)])]),e(`>`,[S(`card-header`,`
 box-sizing: border-box;
 display: flex;
 align-items: center;
 font-size: var(--n-title-font-size);
 padding:
 var(--n-padding-top)
 var(--n-padding-left)
 var(--n-padding-bottom)
 var(--n-padding-left);
 `,[O(`main`,`
 font-weight: var(--n-title-font-weight);
 transition: color .3s var(--n-bezier);
 flex: 1;
 min-width: 0;
 color: var(--n-title-text-color);
 `),O(`extra`,`
 display: flex;
 align-items: center;
 font-size: var(--n-font-size);
 font-weight: 400;
 transition: color .3s var(--n-bezier);
 color: var(--n-text-color);
 `),O(`close`,`
 margin: 0 0 0 8px;
 transition:
 background-color .3s var(--n-bezier),
 color .3s var(--n-bezier);
 `)]),O(`action`,`
 box-sizing: border-box;
 transition:
 background-color .3s var(--n-bezier),
 border-color .3s var(--n-bezier);
 background-clip: padding-box;
 background-color: var(--n-action-color);
 `),Y,S(`card-content`,[e(`&:first-child`,`
 padding-top: var(--n-padding-bottom);
 `)]),O(`content-scrollbar`,`
 display: flex;
 flex-direction: column;
 `,[e(`>`,[S(`scrollbar-container`,[e(`>`,[Y])])]),e(`&:first-child >`,[S(`scrollbar-container`,[e(`>`,[S(`card-content`,`
 padding-top: var(--n-padding-bottom);
 `)])])])]),O(`footer`,`
 box-sizing: border-box;
 padding: 0 var(--n-padding-left) var(--n-padding-bottom) var(--n-padding-left);
 font-size: var(--n-font-size);
 `,[e(`&:first-child`,`
 padding-top: var(--n-padding-bottom);
 `)]),O(`action`,`
 background-color: var(--n-action-color);
 padding: var(--n-padding-bottom) var(--n-padding-left);
 border-bottom-left-radius: var(--n-border-radius);
 border-bottom-right-radius: var(--n-border-radius);
 `)]),S(`card-cover`,`
 overflow: hidden;
 width: 100%;
 border-radius: var(--n-border-radius) var(--n-border-radius) 0 0;
 `,[e(`img`,`
 display: block;
 width: 100%;
 `)]),j(`bordered`,`
 border: 1px solid var(--n-border-color);
 `,[e(`&:target`,`border-color: var(--n-color-target);`)]),j(`action-segmented`,[e(`>`,[O(`action`,[e(`&:not(:first-child)`,`
 border-top: 1px solid var(--n-border-color);
 `)])])]),j(`content-segmented, content-soft-segmented`,[e(`>`,[S(`card-content`,`
 transition: border-color 0.3s var(--n-bezier);
 `,[e(`&:not(:first-child)`,`
 border-top: 1px solid var(--n-border-color);
 `)]),O(`content-scrollbar`,`
 transition: border-color 0.3s var(--n-bezier);
 `,[e(`&:not(:first-child)`,`
 border-top: 1px solid var(--n-border-color);
 `)])])]),j(`footer-segmented, footer-soft-segmented`,[e(`>`,[O(`footer`,`
 transition: border-color 0.3s var(--n-bezier);
 `,[e(`&:not(:first-child)`,`
 border-top: 1px solid var(--n-border-color);
 `)])])]),j(`embedded`,`
 background-color: var(--n-color-embedded);
 `)]),k(S(`card`,`
 background: var(--n-color-modal);
 `,[j(`embedded`,`
 background-color: var(--n-color-embedded-modal);
 `)])),N(S(`card`,`
 background: var(--n-color-popover);
 `,[j(`embedded`,`
 background-color: var(--n-color-embedded-popover);
 `)]))]),X={title:[String,Function],contentClass:String,contentStyle:[Object,String],contentScrollable:Boolean,headerClass:String,headerStyle:[Object,String],headerExtraClass:String,headerExtraStyle:[Object,String],footerClass:String,footerStyle:[Object,String],embedded:Boolean,segmented:{type:[Boolean,Object],default:!1},size:String,bordered:{type:Boolean,default:!0},closable:Boolean,hoverable:Boolean,role:String,onClose:[Function,Array],tag:{type:String,default:`div`},cover:Function,content:[String,Function],footer:Function,action:Function,headerExtra:Function,closeFocusable:Boolean},Fe=R(X),Ie=Object.assign(Object.assign({},b.props),X),Le=E({name:`Card`,props:Ie,slots:Object,setup(e){let t=()=>{let{onClose:t}=e;t&&F(t)},{inlineThemeDisabled:r,mergedClsPrefixRef:a,mergedRtlRef:o,mergedComponentPropsRef:s}=p(e),c=b(`Card`,`-card`,Pe,ue,e,a),l=te(`Card`,o,a),u=i(()=>e.size||s?.value?.Card?.size||`medium`),d=i(()=>{let e=u.value,{self:{color:t,colorModal:n,colorTarget:r,textColor:i,titleTextColor:a,titleFontWeight:o,borderColor:s,actionColor:l,borderRadius:d,lineHeight:f,closeIconColor:p,closeIconColorHover:m,closeIconColorPressed:h,closeColorHover:g,closeColorPressed:_,closeBorderRadius:y,closeIconSize:b,closeSize:x,boxShadow:S,colorPopover:C,colorEmbedded:w,colorEmbeddedModal:T,colorEmbeddedPopover:E,[v(`padding`,e)]:D,[v(`fontSize`,e)]:O,[v(`titleFontSize`,e)]:k},common:{cubicBezierEaseInOut:A}}=c.value,{top:j,left:M,bottom:N}=re(D);return{"--n-bezier":A,"--n-border-radius":d,"--n-color":t,"--n-color-modal":n,"--n-color-popover":C,"--n-color-embedded":w,"--n-color-embedded-modal":T,"--n-color-embedded-popover":E,"--n-color-target":r,"--n-text-color":i,"--n-line-height":f,"--n-action-color":l,"--n-title-text-color":a,"--n-title-font-weight":o,"--n-close-icon-color":p,"--n-close-icon-color-hover":m,"--n-close-icon-color-pressed":h,"--n-close-color-hover":g,"--n-close-color-pressed":_,"--n-border-color":s,"--n-box-shadow":S,"--n-padding-top":j,"--n-padding-bottom":N,"--n-padding-left":M,"--n-font-size":O,"--n-title-font-size":k,"--n-close-size":x,"--n-close-icon-size":b,"--n-close-border-radius":y}}),f=r?n(`card`,i(()=>u.value[0]),d,e):void 0;return{rtlEnabled:l,mergedClsPrefix:a,mergedTheme:c,handleCloseClick:t,cssVars:r?void 0:d,themeClass:f?.themeClass,onRender:f?.onRender}},render(){let{segmented:e,bordered:t,hoverable:n,mergedClsPrefix:r,rtlEnabled:i,onRender:a,embedded:o,tag:s,$slots:c}=this;return a?.(),l(s,{class:[`${r}-card`,this.themeClass,o&&`${r}-card--embedded`,{[`${r}-card--rtl`]:i,[`${r}-card--content-scrollable`]:this.contentScrollable,[`${r}-card--content${typeof e!=`boolean`&&e.content===`soft`?`-soft`:``}-segmented`]:e===!0||e!==!1&&e.content,[`${r}-card--footer${typeof e!=`boolean`&&e.footer===`soft`?`-soft`:``}-segmented`]:e===!0||e!==!1&&e.footer,[`${r}-card--action-segmented`]:e===!0||e!==!1&&e.action,[`${r}-card--bordered`]:t,[`${r}-card--hoverable`]:n}],style:this.cssVars,role:this.role},P(c.cover,e=>{let t=this.cover?I([this.cover()]):e;return t&&l(`div`,{class:`${r}-card-cover`,role:`none`},t)}),P(c.header,e=>{let{title:t}=this,n=t?I(typeof t==`function`?[t()]:[t]):e;return n||this.closable?l(`div`,{class:[`${r}-card-header`,this.headerClass],style:this.headerStyle,role:`heading`},l(`div`,{class:`${r}-card-header__main`,role:`heading`},n),P(c[`header-extra`],e=>{let t=this.headerExtra?I([this.headerExtra()]):e;return t&&l(`div`,{class:[`${r}-card-header__extra`,this.headerExtraClass],style:this.headerExtraStyle},t)}),this.closable&&l(U,{clsPrefix:r,class:`${r}-card-header__close`,onClick:this.handleCloseClick,focusable:this.closeFocusable,absolute:!0})):null}),P(c.default,e=>{let{content:t}=this,n=t?I(typeof t==`function`?[t()]:[t]):e;return n?this.contentScrollable?l(z,{class:`${r}-card__content-scrollbar`,contentClass:[`${r}-card-content`,this.contentClass],contentStyle:this.contentStyle},n):l(`div`,{class:[`${r}-card-content`,this.contentClass],style:this.contentStyle,role:`none`},n):null}),P(c.footer,e=>{let t=this.footer?I([this.footer()]):e;return t&&l(`div`,{class:[`${r}-card__footer`,this.footerClass],style:this.footerStyle,role:`none`},t)}),P(c.action,e=>{let t=this.action?I([this.action()]):e;return t&&l(`div`,{class:`${r}-card__action`,role:`none`},t)}))}}),Re=o(`n-dialog-provider`);o(`n-dialog-api`),o(`n-dialog-reactive-list`);var Z={icon:Function,type:{type:String,default:`default`},title:[String,Function],closable:{type:Boolean,default:!0},negativeText:String,positiveText:String,positiveButtonProps:Object,negativeButtonProps:Object,content:[String,Function],action:Function,showIcon:{type:Boolean,default:!0},loading:Boolean,bordered:Boolean,iconPlacement:String,titleClass:[String,Array],titleStyle:[String,Object],contentClass:[String,Array],contentStyle:[String,Object],actionClass:[String,Array],actionStyle:[String,Object],onPositiveClick:Function,onNegativeClick:Function,onClose:Function,closeFocusable:Boolean},ze=R(Z),Be=e([S(`dialog`,`
 --n-icon-margin: var(--n-icon-margin-top) var(--n-icon-margin-right) var(--n-icon-margin-bottom) var(--n-icon-margin-left);
 word-break: break-word;
 line-height: var(--n-line-height);
 position: relative;
 background: var(--n-color);
 color: var(--n-text-color);
 box-sizing: border-box;
 margin: auto;
 border-radius: var(--n-border-radius);
 padding: var(--n-padding);
 transition: 
 border-color .3s var(--n-bezier),
 background-color .3s var(--n-bezier),
 color .3s var(--n-bezier);
 `,[O(`icon`,`
 color: var(--n-icon-color);
 `),j(`bordered`,`
 border: var(--n-border);
 `),j(`icon-top`,[O(`close`,`
 margin: var(--n-close-margin);
 `),O(`icon`,`
 margin: var(--n-icon-margin);
 `),O(`content`,`
 text-align: center;
 `),O(`title`,`
 justify-content: center;
 `),O(`action`,`
 justify-content: center;
 `)]),j(`icon-left`,[O(`icon`,`
 margin: var(--n-icon-margin);
 `),j(`closable`,[O(`title`,`
 padding-right: calc(var(--n-close-size) + 6px);
 `)])]),O(`close`,`
 position: absolute;
 right: 0;
 top: 0;
 margin: var(--n-close-margin);
 transition:
 background-color .3s var(--n-bezier),
 color .3s var(--n-bezier);
 z-index: 1;
 `),O(`content`,`
 font-size: var(--n-font-size);
 margin: var(--n-content-margin);
 position: relative;
 word-break: break-word;
 `,[j(`last`,`margin-bottom: 0;`)]),O(`action`,`
 display: flex;
 justify-content: flex-end;
 `,[e(`> *:not(:last-child)`,`
 margin-right: var(--n-action-space);
 `)]),O(`icon`,`
 font-size: var(--n-icon-size);
 transition: color .3s var(--n-bezier);
 `),O(`title`,`
 transition: color .3s var(--n-bezier);
 display: flex;
 align-items: center;
 font-size: var(--n-title-font-size);
 font-weight: var(--n-title-font-weight);
 color: var(--n-title-text-color);
 `),S(`dialog-icon-container`,`
 display: flex;
 justify-content: center;
 `)]),k(S(`dialog`,`
 width: 446px;
 max-width: calc(100vw - 32px);
 `)),S(`dialog`,[u(`
 width: 446px;
 max-width: calc(100vw - 32px);
 `)])]),Ve={default:()=>l(W,null),info:()=>l(W,null),success:()=>l(Ee,null),warning:()=>l(Te,null),error:()=>l(we,null)},He=E({name:`Dialog`,alias:[`NimbusConfirmCard`,`Confirm`],props:Object.assign(Object.assign({},b.props),Z),slots:Object,setup(e){let{mergedComponentPropsRef:t,mergedClsPrefixRef:r,inlineThemeDisabled:a,mergedRtlRef:o}=p(e),s=te(`Dialog`,o,r),c=i(()=>{let{iconPlacement:n}=e;return n||t?.value?.Dialog?.iconPlacement||`left`});function l(t){let{onPositiveClick:n}=e;n&&n(t)}function u(t){let{onNegativeClick:n}=e;n&&n(t)}function d(){let{onClose:t}=e;t&&t()}let f=b(`Dialog`,`-dialog`,Be,_e,e,r),m=i(()=>{let{type:t}=e,n=c.value,{common:{cubicBezierEaseInOut:r},self:{fontSize:i,lineHeight:a,border:o,titleTextColor:s,textColor:l,color:u,closeBorderRadius:d,closeColorHover:p,closeColorPressed:m,closeIconColor:h,closeIconColorHover:g,closeIconColorPressed:_,closeIconSize:y,borderRadius:b,titleFontWeight:x,titleFontSize:S,padding:C,iconSize:w,actionSpace:T,contentMargin:E,closeSize:D,[n===`top`?`iconMarginIconTop`:`iconMargin`]:O,[n===`top`?`closeMarginIconTop`:`closeMargin`]:k,[v(`iconColor`,t)]:A}}=f.value,j=re(O);return{"--n-font-size":i,"--n-icon-color":A,"--n-bezier":r,"--n-close-margin":k,"--n-icon-margin-top":j.top,"--n-icon-margin-right":j.right,"--n-icon-margin-bottom":j.bottom,"--n-icon-margin-left":j.left,"--n-icon-size":w,"--n-close-size":D,"--n-close-icon-size":y,"--n-close-border-radius":d,"--n-close-color-hover":p,"--n-close-color-pressed":m,"--n-close-icon-color":h,"--n-close-icon-color-hover":g,"--n-close-icon-color-pressed":_,"--n-color":u,"--n-text-color":l,"--n-border-radius":b,"--n-padding":C,"--n-line-height":a,"--n-border":o,"--n-content-margin":E,"--n-title-font-size":S,"--n-title-font-weight":x,"--n-title-text-color":s,"--n-action-space":T}}),h=a?n(`dialog`,i(()=>`${e.type[0]}${c.value[0]}`),m,e):void 0;return{mergedClsPrefix:r,rtlEnabled:s,mergedIconPlacement:c,mergedTheme:f,handlePositiveClick:l,handleNegativeClick:u,handleCloseClick:d,cssVars:a?void 0:m,themeClass:h?.themeClass,onRender:h?.onRender}},render(){var e;let{bordered:t,mergedIconPlacement:n,cssVars:r,closable:i,showIcon:a,title:o,content:s,action:c,negativeText:u,positiveText:d,positiveButtonProps:f,negativeButtonProps:p,handlePositiveClick:m,handleNegativeClick:h,mergedTheme:g,loading:_,type:v,mergedClsPrefix:y}=this;(e=this.onRender)==null||e.call(this);let b=a?l(D,{clsPrefix:y,class:`${y}-dialog__icon`},{default:()=>P(this.$slots.icon,e=>e||(this.icon?H(this.icon):Ve[this.type]()))}):null,x=P(this.$slots.action,e=>e||d||u||c?l(`div`,{class:[`${y}-dialog__action`,this.actionClass],style:this.actionStyle},e||(c?[H(c)]:[this.negativeText&&l(Ce,Object.assign({theme:g.peers.Button,themeOverrides:g.peerOverrides.Button,ghost:!0,size:`small`,onClick:h},p),{default:()=>H(this.negativeText)}),this.positiveText&&l(Ce,Object.assign({theme:g.peers.Button,themeOverrides:g.peerOverrides.Button,size:`small`,type:v==="default"?`primary`:v,disabled:_,loading:_,onClick:m},f),{default:()=>H(this.positiveText)})])):null);return l(`div`,{class:[`${y}-dialog`,this.themeClass,this.closable&&`${y}-dialog--closable`,`${y}-dialog--icon-${n}`,t&&`${y}-dialog--bordered`,this.rtlEnabled&&`${y}-dialog--rtl`],style:r,role:`dialog`},i?P(this.$slots.close,e=>{let t=[`${y}-dialog__close`,this.rtlEnabled&&`${y}-dialog--rtl`];return e?l(`div`,{class:t},e):l(U,{focusable:this.closeFocusable,clsPrefix:y,class:t,onClick:this.handleCloseClick})}):null,a&&n===`top`?l(`div`,{class:`${y}-dialog-icon-container`},b):null,l(`div`,{class:[`${y}-dialog__title`,this.titleClass],style:this.titleStyle},a&&n===`left`?b:null,ee(this.$slots.header,()=>[H(o)])),l(`div`,{class:[`${y}-dialog__content`,x?``:`${y}-dialog__content--last`,this.contentClass],style:this.contentStyle},ee(this.$slots.default,()=>[H(s)])),x)}}),Q=`n-draggable`;function Ue(e,t){let n,r=i(()=>e.value!==!1),a=i(()=>r.value?Q:``),o=i(()=>{let t=e.value;return t===!0||t===!1||!t||t.bounds!==`none`});function s(e){let r=e.querySelector(`.${Q}`);if(!r||!a.value)return;let i=0,s=0,c=0,l=0,u=0,d=0,f,p=null,m=null;function h(t){t.preventDefault(),f=t;let{x:n,y:r,right:a,bottom:o}=e.getBoundingClientRect();s=n,l=r,i=window.innerWidth-a,c=window.innerHeight-o;let{left:p,top:m}=e.style;u=+m.slice(0,-2),d=+p.slice(0,-2)}function g(){m&&=(e.style.top=`${m.y}px`,e.style.left=`${m.x}px`,null),p=null}function _(e){if(!f)return;let{clientX:t,clientY:n}=f,r=e.clientX-t,a=e.clientY-n;o.value&&(r>i?r=i:-r>s&&(r=-s),a>c?a=c:-a>l&&(a=-l)),m={x:r+d,y:a+u},p||=requestAnimationFrame(g)}function v(){f=void 0,p&&=(cancelAnimationFrame(p),null),m&&=(e.style.top=`${m.y}px`,e.style.left=`${m.x}px`,null),t.onEnd(e)}B(`mousedown`,r,h),B(`mousemove`,window,_),B(`mouseup`,window,v),n=()=>{p&&cancelAnimationFrame(p),L(`mousedown`,r,h),L(`mousemove`,window,_),L(`mouseup`,window,v)}}function c(){n&&=(n(),void 0)}return g(c),{stopDrag:c,startDrag:s,draggableRef:r,draggableClassRef:a}}var $=Object.assign(Object.assign({},X),Z),We=R($),Ge=E({name:`ModalBody`,inheritAttrs:!1,slots:Object,props:Object.assign(Object.assign({show:{type:Boolean,required:!0},preset:String,displayDirective:{type:String,required:!0},trapFocus:{type:Boolean,default:!0},autoFocus:{type:Boolean,default:!0},blockScroll:Boolean,draggable:{type:[Boolean,Object],default:!1},maskHidden:Boolean},$),{renderMask:Function,onClickoutside:Function,onBeforeLeave:{type:Function,required:!0},onAfterLeave:{type:Function,required:!0},onPositiveClick:{type:Function,required:!0},onNegativeClick:{type:Function,required:!0},onClose:{type:Function,required:!0},onAfterEnter:Function,onEsc:Function}),setup(e){let t=y(null),n=y(null),r=y(e.show),o=y(null),c=y(null),l=a(ae),u=null;_(C(e,`show`),e=>{e&&(u=l.getMousePosition())},{immediate:!0});let{stopDrag:f,startDrag:p,draggableRef:m,draggableClassRef:h}=Ue(C(e,`draggable`),{onEnd:e=>{x(e)}}),g=i(()=>w([e.titleClass,h.value])),v=i(()=>w([e.headerClass,h.value]));_(C(e,`show`),e=>{e&&(r.value=!0)}),Se(i(()=>e.blockScroll&&r.value));function b(){if(l.transformOriginRef.value===`center`)return``;let{value:e}=o,{value:t}=c;return e===null||t===null?``:n.value?`${e}px ${t+n.value.containerScrollTop}px`:``}function x(e){if(l.transformOriginRef.value===`center`||!u||!n.value)return;let t=n.value.containerScrollTop,{offsetLeft:r,offsetTop:i}=e,a=u.y,s=u.x;o.value=-(r-s),c.value=-(i-a-t),e.style.transformOrigin=b()}function S(e){d(()=>{x(e)})}function T(t){t.style.transformOrigin=b(),e.onBeforeLeave()}function E(t){let n=t;m.value&&p(n),e.onAfterEnter&&e.onAfterEnter(n)}function D(){r.value=!1,o.value=null,c.value=null,f(),e.onAfterLeave()}function O(){let{onClose:t}=e;t&&t()}function k(){e.onNegativeClick()}function A(){e.onPositiveClick()}let j=y(null);return _(j,e=>{e&&d(()=>{let n=e.el;n&&t.value!==n&&(t.value=n)})}),s(ce,t),s(le,null),s(xe,null),{mergedTheme:l.mergedThemeRef,appear:l.appearRef,isMounted:l.isMountedRef,mergedClsPrefix:l.mergedClsPrefixRef,bodyRef:t,scrollbarRef:n,draggableClass:h,displayed:r,childNodeRef:j,cardHeaderClass:v,dialogTitleClass:g,handlePositiveClick:A,handleNegativeClick:k,handleCloseClick:O,handleAfterEnter:E,handleAfterLeave:D,handleBeforeLeave:T,handleEnter:S}},render(){let{$slots:e,$attrs:n,handleEnter:r,handleAfterEnter:i,handleAfterLeave:a,handleBeforeLeave:o,preset:s,mergedClsPrefix:u}=this,d=null;if(!s){if(d=ye(`default`,e.default,{draggableClass:this.draggableClass}),!d){h(`modal`,`default slot is empty`);return}d=f(d),d.props=c({class:`${u}-modal`},n,d.props||{})}return this.displayDirective===`show`||this.displayed||this.show?t(l(`div`,{role:`none`,class:[`${u}-modal-body-wrapper`,this.maskHidden&&`${u}-modal-body-wrapper--mask-hidden`]},l(z,{ref:`scrollbarRef`,theme:this.mergedTheme.peers.Scrollbar,themeOverrides:this.mergedTheme.peerOverrides.Scrollbar,contentClass:`${u}-modal-scroll-content`},{default:()=>[this.renderMask?.call(this),l(ge,{disabled:!this.trapFocus||this.maskHidden,active:this.show,onEsc:this.onEsc,autoFocus:this.autoFocus},{default:()=>l(x,{name:`fade-in-scale-up-transition`,appear:this.appear??this.isMounted,onEnter:r,onAfterEnter:i,onAfterLeave:a,onBeforeLeave:o},{default:()=>{let n=[[T,this.show]],{onClickoutside:r}=this;return r&&n.push([pe,this.onClickoutside,void 0,{capture:!0}]),t(this.preset===`confirm`||this.preset===`dialog`?l(He,Object.assign({},this.$attrs,{class:[`${u}-modal`,this.$attrs.class],ref:`bodyRef`,theme:this.mergedTheme.peers.Dialog,themeOverrides:this.mergedTheme.peerOverrides.Dialog},V(this.$props,ze),{titleClass:this.dialogTitleClass,"aria-modal":`true`}),e):this.preset===`card`?l(Le,Object.assign({},this.$attrs,{ref:`bodyRef`,class:[`${u}-modal`,this.$attrs.class],theme:this.mergedTheme.peers.Card,themeOverrides:this.mergedTheme.peerOverrides.Card},V(this.$props,Fe),{headerClass:this.cardHeaderClass,"aria-modal":`true`,role:`dialog`}),e):this.childNodeRef=d,n)}})})]})),[[T,this.displayDirective===`if`||this.displayed||this.show]]):null}}),Ke=e([S(`modal-container`,`
 position: fixed;
 left: 0;
 top: 0;
 height: 0;
 width: 0;
 display: flex;
 `),S(`modal-mask`,`
 position: fixed;
 left: 0;
 right: 0;
 top: 0;
 bottom: 0;
 background-color: rgba(0, 0, 0, .4);
 `,[ie({enterDuration:`.25s`,leaveDuration:`.25s`,enterCubicBezier:`var(--n-bezier-ease-out)`,leaveCubicBezier:`var(--n-bezier-ease-out)`})]),S(`modal-body-wrapper`,`
 position: fixed;
 left: 0;
 right: 0;
 top: 0;
 bottom: 0;
 overflow: visible;
 `,[S(`modal-scroll-content`,`
 min-height: 100%;
 display: flex;
 position: relative;
 `),j(`mask-hidden`,`pointer-events: none;`,[S(`modal-scroll-content`,[e(`> *`,`
 pointer-events: all;
 `)])])]),S(`modal`,`
 position: relative;
 align-self: center;
 color: var(--n-text-color);
 margin: auto;
 box-shadow: var(--n-box-shadow);
 `,[De({duration:`.25s`,enterScale:`.5`}),e(`.${Q}`,`
 cursor: move;
 user-select: none;
 `)])]),qe=Object.assign(Object.assign(Object.assign(Object.assign({},b.props),{show:Boolean,showMask:{type:Boolean,default:!0},maskClosable:{type:Boolean,default:!0},preset:String,to:[String,Object],displayDirective:{type:String,default:`if`},transformOrigin:{type:String,default:`mouse`},zIndex:Number,autoFocus:{type:Boolean,default:!0},trapFocus:{type:Boolean,default:!0},closeOnEsc:{type:Boolean,default:!0},blockScroll:{type:Boolean,default:!0}}),$),{draggable:[Boolean,Object],onEsc:Function,"onUpdate:show":[Function,Array],onUpdateShow:[Function,Array],onAfterEnter:Function,onBeforeLeave:Function,onAfterLeave:Function,onClose:Function,onPositiveClick:Function,onNegativeClick:Function,onMaskClick:Function,internalDialog:Boolean,internalModal:Boolean,internalAppear:{type:Boolean,default:void 0},overlayStyle:[String,Object],onBeforeHide:Function,onAfterHide:Function,onHide:Function,unstableShowMask:{type:Boolean,default:void 0}}),Je=E({name:`Modal`,inheritAttrs:!1,props:qe,slots:Object,setup(e){let t=y(null),{mergedClsPrefixRef:r,namespaceRef:o,inlineThemeDisabled:c}=p(e),l=b(`Modal`,`-modal`,Ke,ve,e,r),u=Ne(64),d=ke(),f=A(),m=e.internalDialog?a(Re,null):null,h=e.internalModal?a(be,null):null,g=de();function _(t){let{onUpdateShow:n,"onUpdate:show":r,onHide:i}=e;n&&F(n,t),r&&F(r,t),i&&!t&&i(t)}function v(){let{onClose:t}=e;t?Promise.resolve(t()).then(e=>{e!==!1&&_(!1)}):_(!1)}function x(){let{onPositiveClick:t}=e;t?Promise.resolve(t()).then(e=>{e!==!1&&_(!1)}):_(!1)}function S(){let{onNegativeClick:t}=e;t?Promise.resolve(t()).then(e=>{e!==!1&&_(!1)}):_(!1)}function w(){let{onBeforeLeave:t,onBeforeHide:n}=e;t&&F(t),n&&n()}function T(){let{onAfterLeave:t,onAfterHide:n}=e;t&&F(t),n&&n()}function E(n){let{onMaskClick:r}=e;r&&r(n),e.maskClosable&&t.value?.contains(ne(n))&&_(!1)}function D(t){var n;(n=e.onEsc)==null||n.call(e),e.show&&e.closeOnEsc&&fe(t)&&(g.value||_(!1))}s(ae,{getMousePosition:()=>{let e=m||h;if(e){let{clickedRef:t,clickedPositionRef:n}=e;if(t.value&&n.value)return n.value}return u.value?d.value:null},mergedClsPrefixRef:r,mergedThemeRef:l,isMountedRef:f,appearRef:C(e,`internalAppear`),transformOriginRef:C(e,`transformOrigin`)});let O=i(()=>{let{common:{cubicBezierEaseOut:e},self:{boxShadow:t,color:n,textColor:r}}=l.value;return{"--n-bezier-ease-out":e,"--n-box-shadow":t,"--n-color":n,"--n-text-color":r}}),k=c?n(`theme-class`,void 0,O,e):void 0;return{mergedClsPrefix:r,namespace:o,isMounted:f,containerRef:t,presetProps:i(()=>V(e,We)),handleEsc:D,handleAfterLeave:T,handleClickoutside:E,handleBeforeLeave:w,doUpdateShow:_,handleNegativeClick:S,handlePositiveClick:x,handleCloseClick:v,cssVars:c?void 0:O,themeClass:k?.themeClass,onRender:k?.onRender}},render(){let{mergedClsPrefix:e}=this;return l(he,{to:this.to,show:this.show},{default:()=>{var n;(n=this.onRender)==null||n.call(this);let{showMask:r}=this;return t(l(`div`,{role:`none`,ref:`containerRef`,class:[`${e}-modal-container`,this.themeClass,this.namespace],style:this.cssVars},l(Ge,Object.assign({style:this.overlayStyle},this.$attrs,{ref:`bodyWrapper`,displayDirective:this.displayDirective,show:this.show,preset:this.preset,autoFocus:this.autoFocus,trapFocus:this.trapFocus,draggable:this.draggable,blockScroll:this.blockScroll,maskHidden:!r},this.presetProps,{onEsc:this.handleEsc,onClose:this.handleCloseClick,onNegativeClick:this.handleNegativeClick,onPositiveClick:this.handlePositiveClick,onBeforeLeave:this.handleBeforeLeave,onAfterEnter:this.onAfterEnter,onAfterLeave:this.handleAfterLeave,onClickoutside:r?void 0:this.handleClickoutside,renderMask:r?()=>l(x,{name:`fade-in-transition`,key:`mask`,appear:this.internalAppear??this.isMounted},{default:()=>this.show?l(`div`,{"aria-hidden":!0,ref:`containerRef`,class:`${e}-modal-mask`,onClick:this.handleClickoutside}):null}):void 0}),this.$slots)),[[me,{zIndex:this.zIndex,enabled:this.show}]])}})}});export{Le as n,Je as t};