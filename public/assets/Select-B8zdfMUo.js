import{Bt as e,Et as t,G as n,J as r,K as i,Kt as a,Mt as o,N as s,P as c,Pt as l,R as u,Rt as d,X as f,Y as p,Yt as m,Z as h,cn as g,ft as _,i as v,in as y,kt as b,ot as x,qt as S,wt as C,xt as w}from"./_plugin-vue_export-helper-CHFfEUVo.js";import{a as T,t as E}from"./runtime-dom.esm-bundler-C_6Vey5P.js";import{f as D,g as ee,h as O,l as te,m as k,s as A,t as j}from"./Loading-DTHYZrnQ.js";import{a as M,i as N,n as ne,o as P,r as F,s as I,t as L}from"./Follower-C1AnKYrn.js";import{c as R,d as re,o as z,t as B}from"./Scrollbar-DMBpoFRH.js";import{l as V,r as H,s as ie}from"./Close-DCuJsqL1.js";import{d as U,n as W,s as G,t as K}from"./fade-in-scale-up.cssr-BtnHCQZi.js";import{u as q}from"./Button-BEyPvId2.js";import{n as J,t as ae}from"./cssr-DdA6PBhw.js";import{t as oe}from"./use-merged-state-D9bC4wj2.js";import{t as se}from"./use-compitable-gcRk6A6G.js";import{o as ce,s as le,t as ue}from"./Popover-BWG01ti0.js";import{t as de}from"./VirtualList-BHo_Ic1h.js";import{t as fe}from"./use-locale-tcLhB4ia.js";import{n as pe}from"./Input-DsFy-RnX.js";import{t as Y}from"./Empty-D_DCe0K0.js";import{t as X}from"./focus-detector-_nxLn--t.js";import{t as me}from"./Tag-uyCQxk83.js";import{P as he,U as Z,V as ge,Z as _e}from"./index-BFrYTqRE.js";var Q=`v-hidden`,ve=ae(`[v-hidden]`,{display:`none!important`}),ye=w({name:`Overflow`,props:{getCounter:Function,getTail:Function,updateCounter:Function,onUpdateCount:Function,onUpdateOverflow:Function},setup(e,{slots:t}){let n=y(null),r=y(null);function i(i){let{value:a}=n,{getCounter:o,getTail:s}=e,c;if(c=o===void 0?r.value:o(),!a||!c)return;c.hasAttribute(Q)&&c.removeAttribute(Q);let{children:l}=a;if(i.showAllItemsBeforeCalculate)for(let e of l)e.hasAttribute(Q)&&e.removeAttribute(Q);let u=a.offsetWidth,d=[],f=t.tail?s?.():null,p=f?f.offsetWidth:0,m=!1,h=a.children.length-+!!t.tail;for(let t=0;t<h-1;++t){if(t<0)continue;let n=l[t];if(m){n.hasAttribute(Q)||n.setAttribute(Q,``);continue}n.hasAttribute(Q)&&n.removeAttribute(Q);let r=n.offsetWidth;if(p+=r,d[t]=r,p>u){let{updateCounter:n}=e;for(let r=t;r>=0;--r){let i=h-1-r;n===void 0?c.textContent=`${i}`:n(i);let a=c.offsetWidth;if(p-=d[r],p+a<=u||r===0){m=!0,t=r-1,f&&(t===-1?(f.style.maxWidth=`${u-a}px`,f.style.boxSizing=`border-box`):f.style.maxWidth=``);let{onUpdateCount:n}=e;n&&n(i);break}}}}let{onUpdateOverflow:g}=e;m?g!==void 0&&g(!0):(g!==void 0&&g(!1),c.setAttribute(Q,``))}let a=u();return ve.mount({id:`vueuc/overflow`,head:!0,anchorMetaName:J,ssr:a}),l(()=>i({showAllItemsBeforeCalculate:!1})),{selfRef:n,counterRef:r,sync:i}},render(){let{$slots:t}=this;return b(()=>this.sync({showAllItemsBeforeCalculate:!1})),C(`div`,{class:`v-overflow`,ref:`selfRef`},[e(t,`default`),t.counter?t.counter():C(`span`,{style:{display:`inline-block`},ref:`counterRef`}),t.tail?t.tail():null])}});function be(e,t){t&&(l(()=>{let{value:n}=e;n&&R.registerHandler(n,t)}),a(e,(e,t)=>{t&&R.unregisterHandler(t)},{deep:!1}),o(()=>{let{value:t}=e;t&&R.unregisterHandler(t)}))}function $(e){let t=e.filter(e=>e!==void 0);if(t.length!==0)return t.length===1?t[0]:t=>{e.forEach(e=>{e&&e(t)})}}var xe=w({name:`Checkmark`,render(){return C(`svg`,{xmlns:`http://www.w3.org/2000/svg`,viewBox:`0 0 16 16`},C(`g`,{fill:`none`},C(`path`,{d:`M14.046 3.486a.75.75 0 0 1-.032 1.06l-7.93 7.474a.85.85 0 0 1-1.188-.022l-2.68-2.72a.75.75 0 1 1 1.068-1.053l2.234 2.267l7.468-7.038a.75.75 0 0 1 1.06.032z`,fill:`currentColor`})))}}),Se=w({name:`NBaseSelectGroupHeader`,props:{clsPrefix:{type:String,required:!0},tmNode:{type:Object,required:!0}},setup(){let{renderLabelRef:e,renderOptionRef:n,labelFieldRef:r,nodePropsRef:i}=t(P);return{labelField:r,nodeProps:i,renderLabel:e,renderOption:n}},render(){let{clsPrefix:e,renderLabel:t,renderOption:n,nodeProps:r,tmNode:{rawNode:i}}=this,a=r?.(i),o=t?t(i,!1):W(i[this.labelField],i,!1),s=C(`div`,Object.assign({},a,{class:[`${e}-base-select-group-header`,a?.class]}),o);return i.render?i.render({node:s,option:i}):n?n({node:s,option:i,selected:!1}):s}});function Ce(e,t){return C(E,{name:`fade-in-scale-up-transition`},{default:()=>e?C(H,{clsPrefix:t,class:`${t}-base-select-option__check`},{default:()=>C(xe)}):null})}var we=w({name:`NBaseSelectOption`,props:{clsPrefix:{type:String,required:!0},tmNode:{type:Object,required:!0}},setup(e){let{valueRef:n,pendingTmNodeRef:r,multipleRef:i,valueSetRef:a,renderLabelRef:o,renderOptionRef:s,labelFieldRef:c,valueFieldRef:l,showCheckmarkRef:u,nodePropsRef:d,handleOptionClick:f,handleOptionMouseEnter:p}=t(P),m=q(()=>{let{value:t}=r;return t?e.tmNode.key===t.key:!1});function h(t){let{tmNode:n}=e;n.disabled||f(t,n)}function g(t){let{tmNode:n}=e;n.disabled||p(t,n)}function _(t){let{tmNode:n}=e,{value:r}=m;n.disabled||r||p(t,n)}return{multiple:i,isGrouped:q(()=>{let{tmNode:t}=e,{parent:n}=t;return n&&n.rawNode.type===`group`}),showCheckmark:u,nodeProps:d,isPending:m,isSelected:q(()=>{let{value:t}=n,{value:r}=i;if(t===null)return!1;let o=e.tmNode.rawNode[l.value];if(r){let{value:e}=a;return e.has(o)}return t===o}),labelField:c,renderLabel:o,renderOption:s,handleMouseMove:_,handleMouseEnter:g,handleClick:h}},render(){let{clsPrefix:e,tmNode:{rawNode:t},isSelected:n,isPending:r,isGrouped:i,showCheckmark:a,nodeProps:o,renderOption:s,renderLabel:c,handleClick:l,handleMouseEnter:u,handleMouseMove:d}=this,f=Ce(n,e),p=c?[c(t,n),a&&f]:[W(t[this.labelField],t,n),a&&f],m=o?.(t),h=C(`div`,Object.assign({},m,{class:[`${e}-base-select-option`,t.class,m?.class,{[`${e}-base-select-option--disabled`]:t.disabled,[`${e}-base-select-option--selected`]:n,[`${e}-base-select-option--grouped`]:i,[`${e}-base-select-option--pending`]:r,[`${e}-base-select-option--show-checkmark`]:a}],style:[m?.style||``,t.style||``],onClick:$([l,m?.onClick]),onMouseenter:$([u,m?.onMouseenter]),onMousemove:$([d,m?.onMousemove])}),C(`div`,{class:`${e}-base-select-option__content`},p));return t.render?t.render({node:h,option:t,selected:n}):s?s({node:h,option:t,selected:n}):h}}),Te=i(`base-select-menu`,`
 line-height: 1.5;
 outline: none;
 z-index: 0;
 position: relative;
 border-radius: var(--n-border-radius);
 transition:
 background-color .3s var(--n-bezier),
 box-shadow .3s var(--n-bezier);
 background-color: var(--n-color);
`,[i(`scrollbar`,`
 max-height: var(--n-height);
 `),i(`virtual-list`,`
 max-height: var(--n-height);
 `),i(`base-select-option`,`
 min-height: var(--n-option-height);
 font-size: var(--n-option-font-size);
 display: flex;
 align-items: center;
 `,[r(`content`,`
 z-index: 1;
 white-space: nowrap;
 text-overflow: ellipsis;
 overflow: hidden;
 `)]),i(`base-select-group-header`,`
 min-height: var(--n-option-height);
 font-size: .93em;
 display: flex;
 align-items: center;
 `),i(`base-select-menu-option-wrapper`,`
 position: relative;
 width: 100%;
 `),r(`loading, empty`,`
 display: flex;
 padding: 12px 32px;
 flex: 1;
 justify-content: center;
 `),r(`loading`,`
 color: var(--n-loading-color);
 font-size: var(--n-loading-size);
 `),r(`header`,`
 padding: 8px var(--n-option-padding-left);
 font-size: var(--n-option-font-size);
 transition: 
 color .3s var(--n-bezier),
 border-color .3s var(--n-bezier);
 border-bottom: 1px solid var(--n-action-divider-color);
 color: var(--n-action-text-color);
 `),r(`action`,`
 padding: 8px var(--n-option-padding-left);
 font-size: var(--n-option-font-size);
 transition: 
 color .3s var(--n-bezier),
 border-color .3s var(--n-bezier);
 border-top: 1px solid var(--n-action-divider-color);
 color: var(--n-action-text-color);
 `),i(`base-select-group-header`,`
 position: relative;
 cursor: default;
 padding: var(--n-option-padding);
 color: var(--n-group-header-text-color);
 `),i(`base-select-option`,`
 cursor: pointer;
 position: relative;
 padding: var(--n-option-padding);
 transition:
 color .3s var(--n-bezier),
 opacity .3s var(--n-bezier);
 box-sizing: border-box;
 color: var(--n-option-text-color);
 opacity: 1;
 `,[p(`show-checkmark`,`
 padding-right: calc(var(--n-option-padding-right) + 20px);
 `),n(`&::before`,`
 content: "";
 position: absolute;
 left: 4px;
 right: 4px;
 top: 0;
 bottom: 0;
 border-radius: var(--n-border-radius);
 transition: background-color .3s var(--n-bezier);
 `),n(`&:active`,`
 color: var(--n-option-text-color-pressed);
 `),p(`grouped`,`
 padding-left: calc(var(--n-option-padding-left) * 1.5);
 `),p(`pending`,[n(`&::before`,`
 background-color: var(--n-option-color-pending);
 `)]),p(`selected`,`
 color: var(--n-option-text-color-active);
 `,[n(`&::before`,`
 background-color: var(--n-option-color-active);
 `),p(`pending`,[n(`&::before`,`
 background-color: var(--n-option-color-active-pending);
 `)])]),p(`disabled`,`
 cursor: not-allowed;
 `,[f(`selected`,`
 color: var(--n-option-text-color-disabled);
 `),p(`selected`,`
 opacity: var(--n-option-opacity-disabled);
 `)]),r(`check`,`
 font-size: 16px;
 position: absolute;
 right: calc(var(--n-option-padding-right) - 4px);
 top: calc(50% - 7px);
 color: var(--n-option-check-color);
 transition: color .3s var(--n-bezier);
 `,[K({enterScale:`0.5`})])])]),Ee=w({name:`InternalSelectMenu`,props:Object.assign(Object.assign({},v.props),{clsPrefix:{type:String,required:!0},scrollable:{type:Boolean,default:!0},treeMate:{type:Object,required:!0},multiple:Boolean,size:{type:String,default:`medium`},value:{type:[String,Number,Array],default:null},autoPending:Boolean,virtualScroll:{type:Boolean,default:!0},show:{type:Boolean,default:!0},labelField:{type:String,default:`label`},valueField:{type:String,default:`value`},loading:Boolean,focusable:Boolean,renderLabel:Function,renderOption:Function,nodeProps:Function,showCheckmark:{type:Boolean,default:!0},onMousedown:Function,onScroll:Function,onFocus:Function,onBlur:Function,onKeyup:Function,onKeydown:Function,onTabOut:Function,onMouseenter:Function,onMouseleave:Function,onResize:Function,resetMenuOnOptionsChange:{type:Boolean,default:!0},inlineThemeDisabled:Boolean,scrollbarProps:Object,onToggle:Function}),setup(e){let{mergedClsPrefixRef:t,mergedRtlRef:n,mergedComponentPropsRef:r}=c(e),i=A(`InternalSelectMenu`,n,t),u=v(`InternalSelectMenu`,`-internal-select-menu`,Te,Z,e,g(e,`clsPrefix`)),f=y(null),p=y(null),m=y(null),x=_(()=>e.treeMate.getFlattenedNodes()),S=_(()=>le(x.value)),C=y(null);function w(){let{treeMate:t}=e,n=null,{value:r}=e;r===null?n=t.getFirstAvailableNode():(n=e.multiple?t.getNode((r||[])[(r||[]).length-1]):t.getNode(r),(!n||n.disabled)&&(n=t.getFirstAvailableNode())),G(n||null)}function T(){let{value:t}=C;t&&!e.treeMate.getNode(t.key)&&(C.value=null)}let E;a(()=>e.show,t=>{t?E=a(()=>e.treeMate,()=>{e.resetMenuOnOptionsChange?(e.autoPending?w():T(),b(K)):T()},{immediate:!0}):E?.()},{immediate:!0}),o(()=>{E?.()});let D=_(()=>ie(u.value.self[h(`optionHeight`,e.size)])),ee=_(()=>V(u.value.self[h(`padding`,e.size)])),O=_(()=>e.multiple&&Array.isArray(e.value)?new Set(e.value):new Set),te=_(()=>{let e=x.value;return e&&e.length===0}),k=_(()=>r?.value?.Select?.renderEmpty);function j(t){let{onToggle:n}=e;n&&n(t)}function N(t){let{onScroll:n}=e;n&&n(t)}function ne(e){var t;(t=m.value)==null||t.sync(),N(e)}function F(){var e;(e=m.value)==null||e.sync()}function L(){let{value:e}=C;return e||null}function R(e,t){t.disabled||G(t,!1)}function re(e,t){t.disabled||j(t)}function z(t){var n;I(t,`action`)||(n=e.onKeyup)==null||n.call(e,t)}function B(t){var n;I(t,`action`)||(n=e.onKeydown)==null||n.call(e,t)}function H(t){var n;(n=e.onMousedown)==null||n.call(e,t),!e.focusable&&t.preventDefault()}function U(){let{value:e}=C;e&&G(e.getNext({loop:!0}),!0)}function W(){let{value:e}=C;e&&G(e.getPrev({loop:!0}),!0)}function G(e,t=!1){C.value=e,t&&K()}function K(){var t,n;let r=C.value;if(!r)return;let i=S.value(r.key);i!==null&&(e.virtualScroll?(t=p.value)==null||t.scrollTo({index:i}):(n=m.value)==null||n.scrollTo({index:i,elSize:D.value}))}function q(t){var n;f.value?.contains(t.target)&&((n=e.onFocus)==null||n.call(e,t))}function J(t){var n;f.value?.contains(t.relatedTarget)||(n=e.onBlur)==null||n.call(e,t)}d(P,{handleOptionMouseEnter:R,handleOptionClick:re,valueSetRef:O,pendingTmNodeRef:C,nodePropsRef:g(e,`nodeProps`),showCheckmarkRef:g(e,`showCheckmark`),multipleRef:g(e,`multiple`),valueRef:g(e,`value`),renderLabelRef:g(e,`renderLabel`),renderOptionRef:g(e,`renderOption`),labelFieldRef:g(e,`labelField`),valueFieldRef:g(e,`valueField`)}),d(M,f),l(()=>{let{value:e}=m;e&&e.sync()});let ae=_(()=>{let{size:t}=e,{common:{cubicBezierEaseInOut:n},self:{height:r,borderRadius:i,color:a,groupHeaderTextColor:o,actionDividerColor:s,optionTextColorPressed:c,optionTextColor:l,optionTextColorDisabled:d,optionTextColorActive:f,optionOpacityDisabled:p,optionCheckColor:m,actionTextColor:g,optionColorPending:_,optionColorActive:v,loadingColor:y,loadingSize:b,optionColorActivePending:x,[h(`optionFontSize`,t)]:S,[h(`optionHeight`,t)]:C,[h(`optionPadding`,t)]:w}}=u.value;return{"--n-height":r,"--n-action-divider-color":s,"--n-action-text-color":g,"--n-bezier":n,"--n-border-radius":i,"--n-color":a,"--n-option-font-size":S,"--n-group-header-text-color":o,"--n-option-check-color":m,"--n-option-color-pending":_,"--n-option-color-active":v,"--n-option-color-active-pending":x,"--n-option-height":C,"--n-option-opacity-disabled":p,"--n-option-text-color":l,"--n-option-text-color-active":f,"--n-option-text-color-disabled":d,"--n-option-text-color-pressed":c,"--n-option-padding":w,"--n-option-padding-left":V(w,`left`),"--n-option-padding-right":V(w,`right`),"--n-loading-color":y,"--n-loading-size":b}}),{inlineThemeDisabled:oe}=e,se=oe?s(`internal-select-menu`,_(()=>e.size[0]),ae,e):void 0,ce={selfRef:f,next:U,prev:W,getPendingTmNode:L};return be(f,e.onResize),Object.assign({mergedTheme:u,mergedClsPrefix:t,rtlEnabled:i,virtualListRef:p,scrollbarRef:m,itemSize:D,padding:ee,flattenedNodes:x,empty:te,mergedRenderEmpty:k,virtualListContainer(){let{value:e}=p;return e?.listElRef},virtualListContent(){let{value:e}=p;return e?.itemsElRef},doScroll:N,handleFocusin:q,handleFocusout:J,handleKeyUp:z,handleKeyDown:B,handleMouseDown:H,handleVirtualListResize:F,handleVirtualListScroll:ne,cssVars:oe?void 0:ae,themeClass:se?.themeClass,onRender:se?.onRender},ce)},render(){let{$slots:e,virtualScroll:t,clsPrefix:n,mergedTheme:r,themeClass:i,onRender:a}=this;return a?.(),C(`div`,{ref:`selfRef`,tabindex:this.focusable?0:-1,class:[`${n}-base-select-menu`,`${n}-base-select-menu--${this.size}-size`,this.rtlEnabled&&`${n}-base-select-menu--rtl`,i,this.multiple&&`${n}-base-select-menu--multiple`],style:this.cssVars,onFocusin:this.handleFocusin,onFocusout:this.handleFocusout,onKeyup:this.handleKeyUp,onKeydown:this.handleKeyDown,onMousedown:this.handleMouseDown,onMouseenter:this.onMouseenter,onMouseleave:this.onMouseleave},k(e.header,e=>e&&C(`div`,{class:`${n}-base-select-menu__header`,"data-header":!0,key:`header`},e)),this.loading?C(`div`,{class:`${n}-base-select-menu__loading`},C(j,{clsPrefix:n,strokeWidth:20})):this.empty?C(`div`,{class:`${n}-base-select-menu__empty`,"data-empty":!0},D(e.empty,()=>[this.mergedRenderEmpty?.call(this)||C(Y,{theme:r.peers.Empty,themeOverrides:r.peerOverrides.Empty,size:this.size})])):C(B,Object.assign({ref:`scrollbarRef`,theme:r.peers.Scrollbar,themeOverrides:r.peerOverrides.Scrollbar,scrollable:this.scrollable,container:t?this.virtualListContainer:void 0,content:t?this.virtualListContent:void 0,onScroll:t?void 0:this.doScroll},this.scrollbarProps),{default:()=>t?C(de,{ref:`virtualListRef`,class:`${n}-virtual-list`,items:this.flattenedNodes,itemSize:this.itemSize,showScrollbar:!1,paddingTop:this.padding.top,paddingBottom:this.padding.bottom,onResize:this.handleVirtualListResize,onScroll:this.handleVirtualListScroll,itemResizable:!0},{default:({item:e})=>e.isGroup?C(Se,{key:e.key,clsPrefix:n,tmNode:e}):e.ignored?null:C(we,{clsPrefix:n,key:e.key,tmNode:e})}):C(`div`,{class:`${n}-base-select-menu-option-wrapper`,style:{paddingTop:this.padding.top,paddingBottom:this.padding.bottom}},this.flattenedNodes.map(e=>e.isGroup?C(Se,{key:e.key,clsPrefix:n,tmNode:e}):C(we,{clsPrefix:n,key:e.key,tmNode:e})))}),k(e.action,e=>e&&[C(`div`,{class:`${n}-base-select-menu__action`,"data-action":!0,key:`action`},e),C(X,{onFocus:this.onTabOut,key:`focus-detector`})]))}}),De=n([i(`base-selection`,`
 --n-padding-single: var(--n-padding-single-top) var(--n-padding-single-right) var(--n-padding-single-bottom) var(--n-padding-single-left);
 --n-padding-multiple: var(--n-padding-multiple-top) var(--n-padding-multiple-right) var(--n-padding-multiple-bottom) var(--n-padding-multiple-left);
 position: relative;
 z-index: auto;
 box-shadow: none;
 width: 100%;
 max-width: 100%;
 display: inline-block;
 vertical-align: bottom;
 border-radius: var(--n-border-radius);
 min-height: var(--n-height);
 line-height: 1.5;
 font-size: var(--n-font-size);
 `,[i(`base-loading`,`
 color: var(--n-loading-color);
 `),i(`base-selection-tags`,`min-height: var(--n-height);`),r(`border, state-border`,`
 position: absolute;
 left: 0;
 right: 0;
 top: 0;
 bottom: 0;
 pointer-events: none;
 border: var(--n-border);
 border-radius: inherit;
 transition:
 box-shadow .3s var(--n-bezier),
 border-color .3s var(--n-bezier);
 `),r(`state-border`,`
 z-index: 1;
 border-color: #0000;
 `),i(`base-suffix`,`
 cursor: pointer;
 position: absolute;
 top: 50%;
 transform: translateY(-50%);
 right: 10px;
 `,[r(`arrow`,`
 font-size: var(--n-arrow-size);
 color: var(--n-arrow-color);
 transition: color .3s var(--n-bezier);
 `)]),i(`base-selection-overlay`,`
 display: flex;
 align-items: center;
 white-space: nowrap;
 pointer-events: none;
 position: absolute;
 top: 0;
 right: 0;
 bottom: 0;
 left: 0;
 padding: var(--n-padding-single);
 transition: color .3s var(--n-bezier);
 `,[r(`wrapper`,`
 flex-basis: 0;
 flex-grow: 1;
 overflow: hidden;
 text-overflow: ellipsis;
 `)]),i(`base-selection-placeholder`,`
 color: var(--n-placeholder-color);
 `,[r(`inner`,`
 max-width: 100%;
 overflow: hidden;
 `)]),i(`base-selection-tags`,`
 cursor: pointer;
 outline: none;
 box-sizing: border-box;
 position: relative;
 z-index: auto;
 display: flex;
 padding: var(--n-padding-multiple);
 flex-wrap: wrap;
 align-items: center;
 width: 100%;
 vertical-align: bottom;
 background-color: var(--n-color);
 border-radius: inherit;
 transition:
 color .3s var(--n-bezier),
 box-shadow .3s var(--n-bezier),
 background-color .3s var(--n-bezier);
 `),i(`base-selection-label`,`
 height: var(--n-height);
 display: inline-flex;
 width: 100%;
 vertical-align: bottom;
 cursor: pointer;
 outline: none;
 z-index: auto;
 box-sizing: border-box;
 position: relative;
 transition:
 color .3s var(--n-bezier),
 box-shadow .3s var(--n-bezier),
 background-color .3s var(--n-bezier);
 border-radius: inherit;
 background-color: var(--n-color);
 align-items: center;
 `,[i(`base-selection-input`,`
 font-size: inherit;
 line-height: inherit;
 outline: none;
 cursor: pointer;
 box-sizing: border-box;
 border:none;
 width: 100%;
 padding: var(--n-padding-single);
 background-color: #0000;
 color: var(--n-text-color);
 transition: color .3s var(--n-bezier);
 caret-color: var(--n-caret-color);
 `,[r(`content`,`
 text-overflow: ellipsis;
 overflow: hidden;
 white-space: nowrap; 
 `)]),r(`render-label`,`
 color: var(--n-text-color);
 `)]),f(`disabled`,[n(`&:hover`,[r(`state-border`,`
 box-shadow: var(--n-box-shadow-hover);
 border: var(--n-border-hover);
 `)]),p(`focus`,[r(`state-border`,`
 box-shadow: var(--n-box-shadow-focus);
 border: var(--n-border-focus);
 `)]),p(`active`,[r(`state-border`,`
 box-shadow: var(--n-box-shadow-active);
 border: var(--n-border-active);
 `),i(`base-selection-label`,`background-color: var(--n-color-active);`),i(`base-selection-tags`,`background-color: var(--n-color-active);`)])]),p(`disabled`,`cursor: not-allowed;`,[r(`arrow`,`
 color: var(--n-arrow-color-disabled);
 `),i(`base-selection-label`,`
 cursor: not-allowed;
 background-color: var(--n-color-disabled);
 `,[i(`base-selection-input`,`
 cursor: not-allowed;
 color: var(--n-text-color-disabled);
 `),r(`render-label`,`
 color: var(--n-text-color-disabled);
 `)]),i(`base-selection-tags`,`
 cursor: not-allowed;
 background-color: var(--n-color-disabled);
 `),i(`base-selection-placeholder`,`
 cursor: not-allowed;
 color: var(--n-placeholder-color-disabled);
 `)]),i(`base-selection-input-tag`,`
 height: calc(var(--n-height) - 6px);
 line-height: calc(var(--n-height) - 6px);
 outline: none;
 display: none;
 position: relative;
 margin-bottom: 3px;
 max-width: 100%;
 vertical-align: bottom;
 `,[r(`input`,`
 font-size: inherit;
 font-family: inherit;
 min-width: 1px;
 padding: 0;
 background-color: #0000;
 outline: none;
 border: none;
 max-width: 100%;
 overflow: hidden;
 width: 1em;
 line-height: inherit;
 cursor: pointer;
 color: var(--n-text-color);
 caret-color: var(--n-caret-color);
 `),r(`mirror`,`
 position: absolute;
 left: 0;
 top: 0;
 white-space: pre;
 visibility: hidden;
 user-select: none;
 -webkit-user-select: none;
 opacity: 0;
 `)]),[`warning`,`error`].map(e=>p(`${e}-status`,[r(`state-border`,`border: var(--n-border-${e});`),f(`disabled`,[n(`&:hover`,[r(`state-border`,`
 box-shadow: var(--n-box-shadow-hover-${e});
 border: var(--n-border-hover-${e});
 `)]),p(`active`,[r(`state-border`,`
 box-shadow: var(--n-box-shadow-active-${e});
 border: var(--n-border-active-${e});
 `),i(`base-selection-label`,`background-color: var(--n-color-active-${e});`),i(`base-selection-tags`,`background-color: var(--n-color-active-${e});`)]),p(`focus`,[r(`state-border`,`
 box-shadow: var(--n-box-shadow-focus-${e});
 border: var(--n-border-focus-${e});
 `)])])]))]),i(`base-selection-popover`,`
 margin-bottom: -3px;
 display: flex;
 flex-wrap: wrap;
 margin-right: -8px;
 `),i(`base-selection-tag-wrapper`,`
 max-width: 100%;
 display: inline-flex;
 padding: 0 7px 3px 0;
 `,[n(`&:last-child`,`padding-right: 0;`),i(`tag`,`
 font-size: 14px;
 max-width: 100%;
 `,[r(`content`,`
 line-height: 1.25;
 text-overflow: ellipsis;
 overflow: hidden;
 `)])])]),Oe=w({name:`InternalSelection`,props:Object.assign(Object.assign({},v.props),{clsPrefix:{type:String,required:!0},bordered:{type:Boolean,default:void 0},active:Boolean,pattern:{type:String,default:``},placeholder:String,selectedOption:{type:Object,default:null},selectedOptions:{type:Array,default:null},labelField:{type:String,default:`label`},valueField:{type:String,default:`value`},multiple:Boolean,filterable:Boolean,clearable:Boolean,disabled:Boolean,size:{type:String,default:`medium`},loading:Boolean,autofocus:Boolean,showArrow:{type:Boolean,default:!0},inputProps:Object,focused:Boolean,renderTag:Function,onKeydown:Function,onClick:Function,onBlur:Function,onFocus:Function,onDeleteOption:Function,maxTagCount:[String,Number],ellipsisTagPopoverProps:Object,onClear:Function,onPatternInput:Function,onPatternFocus:Function,onPatternBlur:Function,renderLabel:Function,status:String,inlineThemeDisabled:Boolean,ignoreComposition:{type:Boolean,default:!0},onResize:Function}),setup(e){let{mergedClsPrefixRef:t,mergedRtlRef:n}=c(e),r=A(`InternalSelection`,n,t),i=y(null),o=y(null),u=y(null),d=y(null),f=y(null),p=y(null),m=y(null),x=y(null),C=y(null),w=y(null),T=y(!1),E=y(!1),D=y(!1),ee=v(`InternalSelection`,`-internal-selection`,De,ge,e,g(e,`clsPrefix`)),O=_(()=>e.clearable&&!e.disabled&&(D.value||e.active)),te=_(()=>e.selectedOption?e.renderTag?e.renderTag({option:e.selectedOption,handleClose:()=>{}}):e.renderLabel?e.renderLabel(e.selectedOption,!0):W(e.selectedOption[e.labelField],e.selectedOption,!0):e.placeholder),k=_(()=>{let t=e.selectedOption;if(t)return t[e.labelField]}),j=_(()=>e.multiple?!!(Array.isArray(e.selectedOptions)&&e.selectedOptions.length):e.selectedOption!==null);function M(){var t;let{value:n}=i;if(n){let{value:r}=o;r&&(r.style.width=`${n.offsetWidth}px`,e.maxTagCount!==`responsive`&&((t=C.value)==null||t.sync({showAllItemsBeforeCalculate:!1})))}}function N(){let{value:e}=w;e&&(e.style.display=`none`)}function ne(){let{value:e}=w;e&&(e.style.display=`inline-block`)}a(g(e,`active`),e=>{e||N()}),a(g(e,`pattern`),()=>{e.multiple&&b(M)});function P(t){let{onFocus:n}=e;n&&n(t)}function F(t){let{onBlur:n}=e;n&&n(t)}function I(t){let{onDeleteOption:n}=e;n&&n(t)}function L(t){let{onClear:n}=e;n&&n(t)}function R(t){let{onPatternInput:n}=e;n&&n(t)}function re(e){(!e.relatedTarget||!u.value?.contains(e.relatedTarget))&&P(e)}function z(e){u.value?.contains(e.relatedTarget)||F(e)}function B(e){L(e)}function H(){D.value=!0}function ie(){D.value=!1}function U(t){!e.active||!e.filterable||t.target!==o.value&&t.preventDefault()}function G(e){I(e)}let K=y(!1);function q(t){if(t.key===`Backspace`&&!K.value&&!e.pattern.length){let{selectedOptions:t}=e;t?.length&&G(t[t.length-1])}}let J=null;function ae(t){let{value:n}=i;n&&(n.textContent=t.target.value,M()),e.ignoreComposition&&K.value?J=t:R(t)}function oe(){K.value=!0}function se(){K.value=!1,e.ignoreComposition&&R(J),J=null}function ce(t){var n;E.value=!0,(n=e.onPatternFocus)==null||n.call(e,t)}function le(t){var n;E.value=!1,(n=e.onPatternBlur)==null||n.call(e,t)}function ue(){var t,n;if(e.filterable)E.value=!1,(t=p.value)==null||t.blur(),(n=o.value)==null||n.blur();else if(e.multiple){let{value:e}=d;e?.blur()}else{let{value:e}=f;e?.blur()}}function de(){var t,n,r;e.filterable?(E.value=!1,(t=p.value)==null||t.focus()):e.multiple?(n=d.value)==null||n.focus():(r=f.value)==null||r.focus()}function fe(){let{value:e}=o;e&&(ne(),e.focus())}function pe(){let{value:e}=o;e&&e.blur()}function Y(e){let{value:t}=m;t&&t.setTextContent(`+${e}`)}function X(){let{value:e}=x;return e}function me(){return o.value}let he=null;function Z(){he!==null&&window.clearTimeout(he)}function _e(){e.active||(Z(),he=window.setTimeout(()=>{j.value&&(T.value=!0)},100))}function Q(){Z()}function ve(e){e||(Z(),T.value=!1)}a(j,e=>{e||(T.value=!1)}),l(()=>{S(()=>{let t=p.value;t&&(e.disabled?t.removeAttribute(`tabindex`):t.tabIndex=E.value?-1:0)})}),be(u,e.onResize);let{inlineThemeDisabled:ye}=e,$=_(()=>{let{size:t}=e,{common:{cubicBezierEaseInOut:n},self:{fontWeight:r,borderRadius:i,color:a,placeholderColor:o,textColor:s,paddingSingle:c,paddingMultiple:l,caretColor:u,colorDisabled:d,textColorDisabled:f,placeholderColorDisabled:p,colorActive:m,boxShadowFocus:g,boxShadowActive:_,boxShadowHover:v,border:y,borderFocus:b,borderHover:x,borderActive:S,arrowColor:C,arrowColorDisabled:w,loadingColor:T,colorActiveWarning:E,boxShadowFocusWarning:D,boxShadowActiveWarning:O,boxShadowHoverWarning:te,borderWarning:k,borderFocusWarning:A,borderHoverWarning:j,borderActiveWarning:M,colorActiveError:N,boxShadowFocusError:ne,boxShadowActiveError:P,boxShadowHoverError:F,borderError:I,borderFocusError:L,borderHoverError:R,borderActiveError:re,clearColor:z,clearColorHover:B,clearColorPressed:H,clearSize:ie,arrowSize:U,[h(`height`,t)]:W,[h(`fontSize`,t)]:G}}=ee.value,K=V(c),q=V(l);return{"--n-bezier":n,"--n-border":y,"--n-border-active":S,"--n-border-focus":b,"--n-border-hover":x,"--n-border-radius":i,"--n-box-shadow-active":_,"--n-box-shadow-focus":g,"--n-box-shadow-hover":v,"--n-caret-color":u,"--n-color":a,"--n-color-active":m,"--n-color-disabled":d,"--n-font-size":G,"--n-height":W,"--n-padding-single-top":K.top,"--n-padding-multiple-top":q.top,"--n-padding-single-right":K.right,"--n-padding-multiple-right":q.right,"--n-padding-single-left":K.left,"--n-padding-multiple-left":q.left,"--n-padding-single-bottom":K.bottom,"--n-padding-multiple-bottom":q.bottom,"--n-placeholder-color":o,"--n-placeholder-color-disabled":p,"--n-text-color":s,"--n-text-color-disabled":f,"--n-arrow-color":C,"--n-arrow-color-disabled":w,"--n-loading-color":T,"--n-color-active-warning":E,"--n-box-shadow-focus-warning":D,"--n-box-shadow-active-warning":O,"--n-box-shadow-hover-warning":te,"--n-border-warning":k,"--n-border-focus-warning":A,"--n-border-hover-warning":j,"--n-border-active-warning":M,"--n-color-active-error":N,"--n-box-shadow-focus-error":ne,"--n-box-shadow-active-error":P,"--n-box-shadow-hover-error":F,"--n-border-error":I,"--n-border-focus-error":L,"--n-border-hover-error":R,"--n-border-active-error":re,"--n-clear-size":ie,"--n-clear-color":z,"--n-clear-color-hover":B,"--n-clear-color-pressed":H,"--n-arrow-size":U,"--n-font-weight":r}}),xe=ye?s(`internal-selection`,_(()=>e.size[0]),$,e):void 0;return{mergedTheme:ee,mergedClearable:O,mergedClsPrefix:t,rtlEnabled:r,patternInputFocused:E,filterablePlaceholder:te,label:k,selected:j,showTagsPanel:T,isComposing:K,counterRef:m,counterWrapperRef:x,patternInputMirrorRef:i,patternInputRef:o,selfRef:u,multipleElRef:d,singleElRef:f,patternInputWrapperRef:p,overflowRef:C,inputTagElRef:w,handleMouseDown:U,handleFocusin:re,handleClear:B,handleMouseEnter:H,handleMouseLeave:ie,handleDeleteOption:G,handlePatternKeyDown:q,handlePatternInputInput:ae,handlePatternInputBlur:le,handlePatternInputFocus:ce,handleMouseEnterCounter:_e,handleMouseLeaveCounter:Q,handleFocusout:z,handleCompositionEnd:se,handleCompositionStart:oe,onPopoverUpdateShow:ve,focus:de,focusInput:fe,blur:ue,blurInput:pe,updateCounter:Y,getCounter:X,getTail:me,renderLabel:e.renderLabel,cssVars:ye?void 0:$,themeClass:xe?.themeClass,onRender:xe?.onRender}},render(){let{status:e,multiple:t,size:n,disabled:r,filterable:i,maxTagCount:a,bordered:o,clsPrefix:s,ellipsisTagPopoverProps:c,onRender:l,renderTag:u,renderLabel:d}=this;l?.();let f=a===`responsive`,p=typeof a==`number`,m=f||p,h=C(z,null,{default:()=>C(pe,{clsPrefix:s,loading:this.loading,showArrow:this.showArrow,showClear:this.mergedClearable&&this.selected,onClear:this.handleClear},{default:()=>{var e;return(e=this.$slots).arrow?.call(e)}})}),g;if(t){let{labelField:e}=this,t=t=>C(`div`,{class:`${s}-base-selection-tag-wrapper`,key:t.value},u?u({option:t,handleClose:()=>{this.handleDeleteOption(t)}}):C(me,{size:n,closable:!t.disabled,disabled:r,onClose:()=>{this.handleDeleteOption(t)},internalCloseIsButtonTag:!1,internalCloseFocusable:!1},{default:()=>d?d(t,!0):W(t[e],t,!0)})),o=()=>(p?this.selectedOptions.slice(0,a):this.selectedOptions).map(t),l=i?C(`div`,{class:`${s}-base-selection-input-tag`,ref:`inputTagElRef`,key:`__input-tag__`},C(`input`,Object.assign({},this.inputProps,{ref:`patternInputRef`,tabindex:-1,disabled:r,value:this.pattern,autofocus:this.autofocus,class:`${s}-base-selection-input-tag__input`,onBlur:this.handlePatternInputBlur,onFocus:this.handlePatternInputFocus,onKeydown:this.handlePatternKeyDown,onInput:this.handlePatternInputInput,onCompositionstart:this.handleCompositionStart,onCompositionend:this.handleCompositionEnd})),C(`span`,{ref:`patternInputMirrorRef`,class:`${s}-base-selection-input-tag__mirror`},this.pattern)):null,_=f?()=>C(`div`,{class:`${s}-base-selection-tag-wrapper`,ref:`counterWrapperRef`},C(me,{size:n,ref:`counterRef`,onMouseenter:this.handleMouseEnterCounter,onMouseleave:this.handleMouseLeaveCounter,disabled:r})):void 0,v;if(p){let e=this.selectedOptions.length-a;e>0&&(v=C(`div`,{class:`${s}-base-selection-tag-wrapper`,key:`__counter__`},C(me,{size:n,ref:`counterRef`,onMouseenter:this.handleMouseEnterCounter,disabled:r},{default:()=>`+${e}`})))}let y=f?i?C(ye,{ref:`overflowRef`,updateCounter:this.updateCounter,getCounter:this.getCounter,getTail:this.getTail,style:{width:`100%`,display:`flex`,overflow:`hidden`}},{default:o,counter:_,tail:()=>l}):C(ye,{ref:`overflowRef`,updateCounter:this.updateCounter,getCounter:this.getCounter,style:{width:`100%`,display:`flex`,overflow:`hidden`}},{default:o,counter:_}):p&&v?o().concat(v):o(),b=m?()=>C(`div`,{class:`${s}-base-selection-popover`},f?o():this.selectedOptions.map(t)):void 0,S=m?Object.assign({show:this.showTagsPanel,trigger:`hover`,overlap:!0,placement:`top`,width:`trigger`,onUpdateShow:this.onPopoverUpdateShow,theme:this.mergedTheme.peers.Popover,themeOverrides:this.mergedTheme.peerOverrides.Popover},c):null,w=!this.selected&&(!this.active||!this.pattern&&!this.isComposing)?C(`div`,{class:`${s}-base-selection-placeholder ${s}-base-selection-overlay`},C(`div`,{class:`${s}-base-selection-placeholder__inner`},this.placeholder)):null,T=i?C(`div`,{ref:`patternInputWrapperRef`,class:`${s}-base-selection-tags`},y,f?null:l,h):C(`div`,{ref:`multipleElRef`,class:`${s}-base-selection-tags`,tabindex:r?void 0:0},y,h);g=C(x,null,m?C(ue,Object.assign({},S,{scrollable:!0,style:`max-height: calc(var(--v-target-height) * 6.6);`}),{trigger:()=>T,default:b}):T,w)}else if(i){let e=this.pattern||this.isComposing,t=this.active?!e:!this.selected,n=!this.active&&this.selected;g=C(`div`,{ref:`patternInputWrapperRef`,class:`${s}-base-selection-label`,title:this.patternInputFocused?void 0:_e(this.label)},C(`input`,Object.assign({},this.inputProps,{ref:`patternInputRef`,class:`${s}-base-selection-input`,value:this.active?this.pattern:``,placeholder:``,readonly:r,disabled:r,tabindex:-1,autofocus:this.autofocus,onFocus:this.handlePatternInputFocus,onBlur:this.handlePatternInputBlur,onInput:this.handlePatternInputInput,onCompositionstart:this.handleCompositionStart,onCompositionend:this.handleCompositionEnd})),n?C(`div`,{class:`${s}-base-selection-label__render-label ${s}-base-selection-overlay`,key:`input`},C(`div`,{class:`${s}-base-selection-overlay__wrapper`},u?u({option:this.selectedOption,handleClose:()=>{}}):d?d(this.selectedOption,!0):W(this.label,this.selectedOption,!0))):null,t?C(`div`,{class:`${s}-base-selection-placeholder ${s}-base-selection-overlay`,key:`placeholder`},C(`div`,{class:`${s}-base-selection-overlay__wrapper`},this.filterablePlaceholder)):null,h)}else g=C(`div`,{ref:`singleElRef`,class:`${s}-base-selection-label`,tabindex:this.disabled?void 0:0},this.label===void 0?C(`div`,{class:`${s}-base-selection-placeholder ${s}-base-selection-overlay`,key:`placeholder`},C(`div`,{class:`${s}-base-selection-placeholder__inner`},this.placeholder)):C(`div`,{class:`${s}-base-selection-input`,title:_e(this.label),key:`input`},C(`div`,{class:`${s}-base-selection-input__content`},u?u({option:this.selectedOption,handleClose:()=>{}}):d?d(this.selectedOption,!0):W(this.label,this.selectedOption,!0))),h);return C(`div`,{ref:`selfRef`,class:[`${s}-base-selection`,this.rtlEnabled&&`${s}-base-selection--rtl`,this.themeClass,e&&`${s}-base-selection--${e}-status`,{[`${s}-base-selection--active`]:this.active,[`${s}-base-selection--selected`]:this.selected||this.active&&this.pattern,[`${s}-base-selection--disabled`]:this.disabled,[`${s}-base-selection--multiple`]:this.multiple,[`${s}-base-selection--focus`]:this.focused}],style:this.cssVars,onClick:this.onClick,onMouseenter:this.handleMouseEnter,onMouseleave:this.handleMouseLeave,onKeydown:this.onKeydown,onFocusin:this.handleFocusin,onFocusout:this.handleFocusout,onMousedown:this.handleMouseDown},g,o?C(`div`,{class:`${s}-base-selection__border`}):null,o?C(`div`,{class:`${s}-base-selection__state-border`}):null)}});function ke(e){return e.type===`group`}function Ae(e){return e.type===`ignored`}function je(e,t){try{return!!(1+t.toString().toLowerCase().indexOf(e.trim().toLowerCase()))}catch{return!1}}function Me(e,t){return{getIsGroup:ke,getIgnored:Ae,getKey(t){return ke(t)?t.name||t.key||`key-required`:t[e]},getChildren(e){return e[t]}}}function Ne(e,t,n,r){if(!t)return e;function i(e){if(!Array.isArray(e))return[];let a=[];for(let o of e)if(ke(o)){let e=i(o[r]);e.length&&a.push(Object.assign({},o,{[r]:e}))}else if(Ae(o))continue;else t(n,o)&&a.push(o);return a}return i(e)}function Pe(e,t,n){let r=new Map;return e.forEach(e=>{ke(e)?e[n].forEach(e=>{r.set(e[t],e)}):r.set(e[t],e)}),r}var Fe=n([i(`select`,`
 z-index: auto;
 outline: none;
 width: 100%;
 position: relative;
 font-weight: var(--n-font-weight);
 `),i(`select-menu`,`
 margin: 4px 0;
 box-shadow: var(--n-menu-box-shadow);
 `,[K({originalTransition:`background-color .3s var(--n-bezier), box-shadow .3s var(--n-bezier)`})])]),Ie=Object.assign(Object.assign({},v.props),{to:N.propTo,bordered:{type:Boolean,default:void 0},clearable:Boolean,clearCreatedOptionsOnClear:{type:Boolean,default:!0},clearFilterAfterSelect:{type:Boolean,default:!0},options:{type:Array,default:()=>[]},defaultValue:{type:[String,Number,Array],default:null},keyboard:{type:Boolean,default:!0},value:[String,Number,Array],placeholder:String,menuProps:Object,multiple:Boolean,size:String,menuSize:{type:String},filterable:Boolean,disabled:{type:Boolean,default:void 0},remote:Boolean,loading:Boolean,filter:Function,placement:{type:String,default:`bottom-start`},widthMode:{type:String,default:`trigger`},tag:Boolean,onCreate:Function,fallbackOption:{type:[Function,Boolean],default:void 0},show:{type:Boolean,default:void 0},showArrow:{type:Boolean,default:!0},maxTagCount:[Number,String],ellipsisTagPopoverProps:Object,consistentMenuWidth:{type:Boolean,default:!0},virtualScroll:{type:Boolean,default:!0},labelField:{type:String,default:`label`},valueField:{type:String,default:`value`},childrenField:{type:String,default:`children`},renderLabel:Function,renderOption:Function,renderTag:Function,"onUpdate:value":[Function,Array],inputProps:Object,nodeProps:Function,ignoreComposition:{type:Boolean,default:!0},showOnFocus:Boolean,onUpdateValue:[Function,Array],onBlur:[Function,Array],onClear:[Function,Array],onFocus:[Function,Array],onScroll:[Function,Array],onSearch:[Function,Array],onUpdateShow:[Function,Array],"onUpdate:show":[Function,Array],displayDirective:{type:String,default:`show`},resetMenuOnOptionsChange:{type:Boolean,default:!0},status:String,showCheckmark:{type:Boolean,default:!0},scrollbarProps:Object,onChange:[Function,Array],items:Array}),Le=w({name:`Select`,props:Ie,slots:Object,setup(e){let{mergedClsPrefixRef:t,mergedBorderedRef:n,namespaceRef:r,inlineThemeDisabled:i,mergedComponentPropsRef:o}=c(e),l=v(`Select`,`-select`,Fe,he,e,t),u=y(e.defaultValue),d=g(e,`value`),f=oe(d,u),p=y(!1),m=y(``),h=se(e,[`items`,`options`]),b=y([]),x=y([]),S=_(()=>x.value.concat(b.value).concat(h.value)),C=_(()=>{let{filter:t}=e;if(t)return t;let{labelField:n,valueField:r}=e;return(e,t)=>{if(!t)return!1;let i=t[n];if(typeof i==`string`)return je(e,i);let a=t[r];return typeof a==`string`?je(e,a):typeof a==`number`&&je(e,String(a))}}),w=_(()=>{if(e.remote)return h.value;{let{value:t}=S,{value:n}=m;return!n.length||!e.filterable?t:Ne(t,C.value,n,e.childrenField)}}),T=_(()=>{let{valueField:t,childrenField:n}=e,r=Me(t,n);return ce(w.value,r)}),E=_(()=>Pe(S.value,e.valueField,e.childrenField)),D=y(!1),k=oe(g(e,`show`),D),A=y(null),j=y(null),M=y(null),{localeRef:ne}=fe(`Select`),P=_(()=>e.placeholder??ne.value.placeholder),F=[],L=y(new Map),R=_(()=>{let{fallbackOption:t}=e;if(t===void 0){let{labelField:t,valueField:n}=e;return e=>({[t]:String(e),[n]:e})}return t===!1?!1:e=>Object.assign(t(e),{value:e})});function z(t){let n=e.remote,{value:r}=L,{value:i}=E,{value:a}=R,o=[];return t.forEach(e=>{if(i.has(e))o.push(i.get(e));else if(n&&r.has(e))o.push(r.get(e));else if(a){let t=a(e);t&&o.push(t)}}),o}let B=_(()=>{if(e.multiple){let{value:e}=f;return Array.isArray(e)?z(e):[]}return null}),V=_(()=>{let{value:t}=f;return!e.multiple&&!Array.isArray(t)?t===null?null:z([t])[0]||null:null}),H=te(e,{mergedSize:t=>{let{size:n}=e;if(n)return n;let{mergedSize:r}=t||{};return r?.value?r.value:o?.value?.Select?.size||`medium`}}),{mergedSizeRef:ie,mergedDisabledRef:U,mergedStatusRef:W}=H;function K(t,n){let{onChange:r,"onUpdate:value":i,onUpdateValue:a}=e,{nTriggerFormChange:o,nTriggerFormInput:s}=H;r&&O(r,t,n),a&&O(a,t,n),i&&O(i,t,n),u.value=t,o(),s()}function q(t){let{onBlur:n}=e,{nTriggerFormBlur:r}=H;n&&O(n,t),r()}function J(){let{onClear:t}=e;t&&O(t)}function ae(t){let{onFocus:n,showOnFocus:r}=e,{nTriggerFormFocus:i}=H;n&&O(n,t),i(),r&&Y()}function le(t){let{onSearch:n}=e;n&&O(n,t)}function ue(t){let{onScroll:n}=e;n&&O(n,t)}function de(){var t;let{remote:n,multiple:r}=e;if(n){let{value:n}=L;if(r){let{valueField:r}=e;(t=B.value)==null||t.forEach(e=>{n.set(e[r],e)})}else{let t=V.value;t&&n.set(t[e.valueField],t)}}}function pe(t){let{onUpdateShow:n,"onUpdate:show":r}=e;n&&O(n,t),r&&O(r,t),D.value=t}function Y(){U.value||(pe(!0),D.value=!0,e.filterable&&Re())}function X(){pe(!1)}function me(){m.value=``,x.value=F}let Z=y(!1);function ge(){e.filterable&&(Z.value=!0)}function _e(){e.filterable&&(Z.value=!1,k.value||me())}function Q(){U.value||(k.value?e.filterable?Re():X():Y())}function ve(e){(M.value?.selfRef)?.contains(e.relatedTarget)||(p.value=!1,q(e),X())}function ye(e){ae(e),p.value=!0}function be(){p.value=!0}function $(e){A.value?.$el.contains(e.relatedTarget)||(p.value=!1,q(e),X())}function xe(){var e;(e=A.value)==null||e.focus(),X()}function Se(e){k.value&&(A.value?.$el.contains(re(e))||X())}function Ce(t){if(!Array.isArray(t))return[];if(R.value)return Array.from(t);{let{remote:n}=e,{value:r}=E;if(n){let{value:e}=L;return t.filter(t=>r.has(t)||e.has(t))}return t.filter(e=>r.has(e))}}function we(e){Te(e.rawNode)}function Te(t){if(U.value)return;let{tag:n,remote:r,clearFilterAfterSelect:i,valueField:a}=e;if(n&&!r){let{value:e}=x,t=e[0]||null;if(t){let e=b.value;e.length?e.push(t):b.value=[t],x.value=F}}if(r&&L.value.set(t[a],t),e.multiple){let e=Ce(f.value),o=e.findIndex(e=>e===t[a]);if(~o){if(e.splice(o,1),n&&!r){let e=Ee(t[a]);~e&&(b.value.splice(e,1),i&&(m.value=``))}}else e.push(t[a]),i&&(m.value=``);K(e,z(e))}else{if(n&&!r){let e=Ee(t[a]);~e?b.value=[b.value[e]]:b.value=F}Le(),X(),K(t[a],t)}}function Ee(t){return b.value.findIndex(n=>n[e.valueField]===t)}function De(t){k.value||Y();let{value:n}=t.target;m.value=n;let{tag:r,remote:i}=e;if(le(n),r&&!i){if(!n){x.value=F;return}let{onCreate:t}=e,r=t?t(n):{[e.labelField]:n,[e.valueField]:n},{valueField:i,labelField:a}=e;h.value.some(e=>e[i]===r[i]||e[a]===r[a])||b.value.some(e=>e[i]===r[i]||e[a]===r[a])?x.value=F:x.value=[r]}}function Oe(t){t.stopPropagation();let{multiple:n,tag:r,remote:i,clearCreatedOptionsOnClear:a}=e;!n&&e.filterable&&X(),r&&!i&&a&&(b.value=F),J(),n?K([],[]):K(null,null)}function ke(e){!I(e,`action`)&&!I(e,`empty`)&&!I(e,`header`)&&e.preventDefault()}function Ae(e){ue(e)}function Ie(t){var n,r,i;if(!e.keyboard){t.preventDefault();return}switch(t.key){case` `:if(e.filterable)break;t.preventDefault();case`Enter`:if(!A.value?.isComposing){if(k.value){let t=M.value?.getPendingTmNode();t?we(t):e.filterable||(X(),Le())}else if(Y(),e.tag&&Z.value){let t=x.value[0];if(t){let n=t[e.valueField],{value:r}=f;e.multiple&&Array.isArray(r)&&r.includes(n)||Te(t)}}}t.preventDefault();break;case`ArrowUp`:if(t.preventDefault(),e.loading)return;k.value&&((n=M.value)==null||n.prev());break;case`ArrowDown`:if(t.preventDefault(),e.loading)return;k.value?(r=M.value)==null||r.next():Y();break;case`Escape`:k.value&&(G(t),X()),(i=A.value)==null||i.focus()}}function Le(){var e;(e=A.value)==null||e.focus()}function Re(){var e;(e=A.value)==null||e.focusInput()}function ze(){var e;k.value&&((e=j.value)==null||e.syncPosition())}de(),a(g(e,`options`),de);let Be={focus:()=>{var e;(e=A.value)==null||e.focus()},focusInput:()=>{var e;(e=A.value)==null||e.focusInput()},blur:()=>{var e;(e=A.value)==null||e.blur()},blurInput:()=>{var e;(e=A.value)==null||e.blurInput()}},Ve=_(()=>{let{self:{menuBoxShadow:e}}=l.value;return{"--n-menu-box-shadow":e}}),He=i?s(`select`,void 0,Ve,e):void 0;return Object.assign(Object.assign({},Be),{mergedStatus:W,mergedClsPrefix:t,mergedBordered:n,namespace:r,treeMate:T,isMounted:ee(),triggerRef:A,menuRef:M,pattern:m,uncontrolledShow:D,mergedShow:k,adjustedTo:N(e),uncontrolledValue:u,mergedValue:f,followerRef:j,localizedPlaceholder:P,selectedOption:V,selectedOptions:B,mergedSize:ie,mergedDisabled:U,focused:p,activeWithoutMenuOpen:Z,inlineThemeDisabled:i,onTriggerInputFocus:ge,onTriggerInputBlur:_e,handleTriggerOrMenuResize:ze,handleMenuFocus:be,handleMenuBlur:$,handleMenuTabOut:xe,handleTriggerClick:Q,handleToggle:we,handleDeleteOption:Te,handlePatternInput:De,handleClear:Oe,handleTriggerBlur:ve,handleTriggerFocus:ye,handleKeydown:Ie,handleMenuAfterLeave:me,handleMenuClickOutside:Se,handleMenuScroll:Ae,handleMenuKeydown:Ie,handleMenuMousedown:ke,mergedTheme:l,cssVars:i?void 0:Ve,themeClass:He?.themeClass,onRender:He?.onRender})},render(){return C(`div`,{class:`${this.mergedClsPrefix}-select`},C(F,null,{default:()=>[C(ne,null,{default:()=>C(Oe,{ref:`triggerRef`,inlineThemeDisabled:this.inlineThemeDisabled,status:this.mergedStatus,inputProps:this.inputProps,clsPrefix:this.mergedClsPrefix,showArrow:this.showArrow,maxTagCount:this.maxTagCount,ellipsisTagPopoverProps:this.ellipsisTagPopoverProps,bordered:this.mergedBordered,active:this.activeWithoutMenuOpen||this.mergedShow,pattern:this.pattern,placeholder:this.localizedPlaceholder,selectedOption:this.selectedOption,selectedOptions:this.selectedOptions,multiple:this.multiple,renderTag:this.renderTag,renderLabel:this.renderLabel,filterable:this.filterable,clearable:this.clearable,disabled:this.mergedDisabled,size:this.mergedSize,theme:this.mergedTheme.peers.InternalSelection,labelField:this.labelField,valueField:this.valueField,themeOverrides:this.mergedTheme.peerOverrides.InternalSelection,loading:this.loading,focused:this.focused,onClick:this.handleTriggerClick,onDeleteOption:this.handleDeleteOption,onPatternInput:this.handlePatternInput,onClear:this.handleClear,onBlur:this.handleTriggerBlur,onFocus:this.handleTriggerFocus,onKeydown:this.handleKeydown,onPatternBlur:this.onTriggerInputBlur,onPatternFocus:this.onTriggerInputFocus,onResize:this.handleTriggerOrMenuResize,ignoreComposition:this.ignoreComposition},{arrow:()=>{var e;return[(e=this.$slots).arrow?.call(e)]}})}),C(L,{ref:`followerRef`,show:this.mergedShow,to:this.adjustedTo,teleportDisabled:this.adjustedTo===N.tdkey,containerClass:this.namespace,width:this.consistentMenuWidth?`target`:void 0,minWidth:`target`,placement:this.placement},{default:()=>C(E,{name:`fade-in-scale-up-transition`,appear:this.isMounted,onAfterLeave:this.handleMenuAfterLeave},{default:()=>{var e;return this.mergedShow||this.displayDirective===`show`?((e=this.onRender)==null||e.call(this),m(C(Ee,Object.assign({},this.menuProps,{ref:`menuRef`,onResize:this.handleTriggerOrMenuResize,inlineThemeDisabled:this.inlineThemeDisabled,virtualScroll:this.consistentMenuWidth&&this.virtualScroll,class:[`${this.mergedClsPrefix}-select-menu`,this.themeClass,this.menuProps?.class],clsPrefix:this.mergedClsPrefix,focusable:!0,labelField:this.labelField,valueField:this.valueField,autoPending:!0,nodeProps:this.nodeProps,theme:this.mergedTheme.peers.InternalSelectMenu,themeOverrides:this.mergedTheme.peerOverrides.InternalSelectMenu,treeMate:this.treeMate,multiple:this.multiple,size:this.menuSize,renderOption:this.renderOption,renderLabel:this.renderLabel,value:this.mergedValue,style:[this.menuProps?.style,this.cssVars],onToggle:this.handleToggle,onScroll:this.handleMenuScroll,onFocus:this.handleMenuFocus,onBlur:this.handleMenuBlur,onKeydown:this.handleMenuKeydown,onTabOut:this.handleMenuTabOut,onMousedown:this.handleMenuMousedown,show:this.mergedShow,showCheckmark:this.showCheckmark,resetMenuOnOptionsChange:this.resetMenuOnOptionsChange,scrollbarProps:this.scrollbarProps}),{empty:()=>{var e;return[(e=this.$slots).empty?.call(e)]},header:()=>{var e;return[(e=this.$slots).header?.call(e)]},action:()=>{var e;return[(e=this.$slots).action?.call(e)]}}),this.displayDirective===`show`?[[T,this.mergedShow],[U,this.handleMenuClickOutside,void 0,{capture:!0}]]:[[U,this.handleMenuClickOutside,void 0,{capture:!0}]])):null}})})]}))}});export{$ as i,Me as n,Ee as r,Le as t};