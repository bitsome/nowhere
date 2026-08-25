import{D as e,Et as t,G as n,I as r,J as i,K as a,Kt as o,M as s,N as c,Ot as l,P as u,Pt as d,R as f,Rt as p,X as m,Y as h,Yt as g,Z as _,cn as v,dt as y,ft as b,i as x,in as S,kt as C,ot as w,qt as ee,wt as T,xt as E,z as D}from"./_plugin-vue_export-helper-CHFfEUVo.js";import{a as O,n as k}from"./runtime-dom.esm-bundler-C_6Vey5P.js";import{h as A,m as j}from"./Loading-DTHYZrnQ.js";import{s as M}from"./Scrollbar-DMBpoFRH.js";import{l as N,o as P,r as te,s as ne,t as re}from"./Close-DCuJsqL1.js";import{n as F}from"./fade-in-scale-up.cssr-BtnHCQZi.js";import{n as I,r as ie,t as L}from"./cssr-DdA6PBhw.js";import{t as ae}from"./use-merged-state-D9bC4wj2.js";import{t as oe}from"./use-compitable-gcRk6A6G.js";import{t as R}from"./flatten-XDzPCGlv.js";import{t as z}from"./Add-vIWfywpk.js";import{J as B,S as se}from"./index-BFrYTqRE.js";var V=L(`.v-x-scroll`,{overflow:`auto`,scrollbarWidth:`none`},[L(`&::-webkit-scrollbar`,{width:0,height:0})]),ce=E({name:`XScroll`,props:{disabled:Boolean,onScroll:Function},setup(){let e=S(null);function t(e){!(e.currentTarget.offsetWidth<e.currentTarget.scrollWidth)||e.deltaY===0||(e.currentTarget.scrollLeft+=e.deltaY+e.deltaX,e.preventDefault())}let n=f();return V.mount({id:`vueuc/x-scroll`,head:!0,anchorMetaName:I,ssr:n}),Object.assign({selfRef:e,handleWheel:t},{scrollTo(...t){var n;(n=e.value)==null||n.scrollTo(...t)}})},render(){return T(`div`,{ref:`selfRef`,onScroll:this.onScroll,onWheel:this.disabled?void 0:this.handleWheel,class:`v-x-scroll`},this.$slots)}}),le=/\s/;function ue(e){for(var t=e.length;t--&&le.test(e.charAt(t)););return t}var H=/^\s+/;function U(e){return e&&e.slice(0,ue(e)+1).replace(H,``)}var W=NaN,de=/^[-+]0x[0-9a-f]+$/i,fe=/^0b[01]+$/i,G=/^0o[0-7]+$/i,K=parseInt;function q(t){if(typeof t==`number`)return t;if(P(t))return W;if(e(t)){var n=typeof t.valueOf==`function`?t.valueOf():t;t=e(n)?n+``:n}if(typeof t!=`string`)return t===0?t:+t;t=U(t);var r=fe.test(t);return r||G.test(t)?K(t.slice(2),r?2:8):de.test(t)?W:+t}var J=function(){return s.Date.now()},Y=`Expected a function`,pe=Math.max,me=Math.min;function X(t,n,r){var i,a,o,s,c,l,u=0,d=!1,f=!1,p=!0;if(typeof t!=`function`)throw TypeError(Y);n=q(n)||0,e(r)&&(d=!!r.leading,f=`maxWait`in r,o=f?pe(q(r.maxWait)||0,n):o,p=`trailing`in r?!!r.trailing:p);function m(e){var n=i,r=a;return i=a=void 0,u=e,s=t.apply(r,n),s}function h(e){return u=e,c=setTimeout(v,n),d?m(e):s}function g(e){var t=e-l,r=e-u,i=n-t;return f?me(i,o-r):i}function _(e){var t=e-l,r=e-u;return l===void 0||t>=n||t<0||f&&r>=o}function v(){var e=J();if(_(e))return y(e);c=setTimeout(v,g(e))}function y(e){return c=void 0,p&&i?m(e):(i=a=void 0,s)}function b(){c!==void 0&&clearTimeout(c),u=0,i=l=a=c=void 0}function x(){return c===void 0?s:y(J())}function S(){var e=J(),t=_(e);if(i=arguments,a=this,l=e,t){if(c===void 0)return h(l);if(f)return clearTimeout(c),c=setTimeout(v,n),m(l)}return c===void 0&&(c=setTimeout(v,n)),s}return S.cancel=b,S.flush=x,S}var he=`Expected a function`;function ge(t,n,r){var i=!0,a=!0;if(typeof t!=`function`)throw TypeError(he);return e(r)&&(i=`leading`in r?!!r.leading:i,a=`trailing`in r?!!r.trailing:a),X(t,n,{leading:i,maxWait:n,trailing:a})}var _e=D(`n-tabs`),ve={tab:[String,Number,Object,Function],name:{type:[String,Number],required:!0},disabled:Boolean,displayDirective:{type:String,default:`if`},closable:{type:Boolean,default:void 0},tabProps:Object,label:[String,Number,Object,Function]},Z=E({__TAB_PANE__:!0,name:`TabPane`,alias:[`TabPanel`],props:ve,slots:Object,setup(e){let n=t(_e,null);return n||r(`tab-pane`,"`n-tab-pane` must be placed inside `n-tabs`."),{style:n.paneStyleRef,class:n.paneClassRef,mergedClsPrefix:n.mergedClsPrefixRef}},render(){return T(`div`,{class:[`${this.mergedClsPrefix}-tab-pane`,this.class],style:this.style},this.$slots)}}),ye=Object.assign({internalLeftPadded:Boolean,internalAddable:Boolean,internalCreatedByPane:Boolean},B(ve,[`displayDirective`])),Q=E({__TAB__:!0,inheritAttrs:!1,name:`Tab`,props:ye,setup(e){let{mergedClsPrefixRef:n,valueRef:r,typeRef:i,closableRef:a,tabStyleRef:o,addTabStyleRef:s,tabClassRef:c,addTabClassRef:l,tabChangeIdRef:u,onBeforeLeaveRef:d,triggerRef:f,handleAdd:p,activateTab:m,handleClose:h}=t(_e);return{trigger:f,mergedClosable:b(()=>{if(e.internalAddable)return!1;let{closable:t}=e;return t===void 0?a.value:t}),style:o,addStyle:s,tabClass:c,addTabClass:l,clsPrefix:n,value:r,type:i,handleClose(t){t.stopPropagation(),!e.disabled&&h(e.name)},activateTab(){if(e.disabled)return;if(e.internalAddable){p();return}let{name:t}=e,n=++u.id;if(t!==r.value){let{value:i}=d;i?Promise.resolve(i(e.name,r.value)).then(e=>{e&&u.id===n&&m(t)}):m(t)}}}},render(){let{internalAddable:e,clsPrefix:t,name:n,disabled:r,label:i,tab:a,value:o,mergedClosable:s,trigger:c,$slots:{default:u}}=this,d=i??a;return T(`div`,{class:`${t}-tabs-tab-wrapper`},this.internalLeftPadded?T(`div`,{class:`${t}-tabs-tab-pad`}):null,T(`div`,Object.assign({key:n,"data-name":n,"data-disabled":r?!0:void 0},l({class:[`${t}-tabs-tab`,o===n&&`${t}-tabs-tab--active`,r&&`${t}-tabs-tab--disabled`,s&&`${t}-tabs-tab--closable`,e&&`${t}-tabs-tab--addable`,e?this.addTabClass:this.tabClass],onClick:c===`click`?this.activateTab:void 0,onMouseenter:c===`hover`?this.activateTab:void 0,style:e?this.addStyle:this.style},this.internalCreatedByPane?this.tabProps||{}:this.$attrs)),T(`span`,{class:`${t}-tabs-tab__label`},e?T(w,null,T(`div`,{class:`${t}-tabs-tab__height-placeholder`},`\xA0`),T(te,{clsPrefix:t},{default:()=>T(z,null)})):u?u():typeof d==`object`?d:F(d??n)),s&&this.type===`card`?T(re,{clsPrefix:t,class:`${t}-tabs-tab__close`,onClick:this.handleClose,disabled:r}):null))}}),be=a(`tabs`,`
 box-sizing: border-box;
 width: 100%;
 display: flex;
 flex-direction: column;
 transition:
 background-color .3s var(--n-bezier),
 border-color .3s var(--n-bezier);
`,[h(`segment-type`,[a(`tabs-rail`,[n(`&.transition-disabled`,[a(`tabs-capsule`,`
 transition: none;
 `)])])]),h(`top`,[a(`tab-pane`,`
 padding: var(--n-pane-padding-top) var(--n-pane-padding-right) var(--n-pane-padding-bottom) var(--n-pane-padding-left);
 `)]),h(`left`,[a(`tab-pane`,`
 padding: var(--n-pane-padding-right) var(--n-pane-padding-bottom) var(--n-pane-padding-left) var(--n-pane-padding-top);
 `)]),h(`left, right`,`
 flex-direction: row;
 `,[a(`tabs-bar`,`
 width: 2px;
 right: 0;
 transition:
 top .2s var(--n-bezier),
 max-height .2s var(--n-bezier),
 background-color .3s var(--n-bezier);
 `),a(`tabs-tab`,`
 padding: var(--n-tab-padding-vertical); 
 `)]),h(`right`,`
 flex-direction: row-reverse;
 `,[a(`tab-pane`,`
 padding: var(--n-pane-padding-left) var(--n-pane-padding-top) var(--n-pane-padding-right) var(--n-pane-padding-bottom);
 `),a(`tabs-bar`,`
 left: 0;
 `)]),h(`bottom`,`
 flex-direction: column-reverse;
 justify-content: flex-end;
 `,[a(`tab-pane`,`
 padding: var(--n-pane-padding-bottom) var(--n-pane-padding-right) var(--n-pane-padding-top) var(--n-pane-padding-left);
 `),a(`tabs-bar`,`
 top: 0;
 `)]),a(`tabs-rail`,`
 position: relative;
 padding: 3px;
 border-radius: var(--n-tab-border-radius);
 width: 100%;
 background-color: var(--n-color-segment);
 transition: background-color .3s var(--n-bezier);
 display: flex;
 align-items: center;
 `,[a(`tabs-capsule`,`
 border-radius: var(--n-tab-border-radius);
 position: absolute;
 pointer-events: none;
 background-color: var(--n-tab-color-segment);
 box-shadow: 0 1px 3px 0 rgba(0, 0, 0, .08);
 transition: transform 0.3s var(--n-bezier);
 `),a(`tabs-tab-wrapper`,`
 flex-basis: 0;
 flex-grow: 1;
 display: flex;
 align-items: center;
 justify-content: center;
 `,[a(`tabs-tab`,`
 overflow: hidden;
 border-radius: var(--n-tab-border-radius);
 width: 100%;
 display: flex;
 align-items: center;
 justify-content: center;
 `,[h(`active`,`
 font-weight: var(--n-font-weight-strong);
 color: var(--n-tab-text-color-active);
 `),n(`&:hover`,`
 color: var(--n-tab-text-color-hover);
 `)])])]),h(`flex`,[a(`tabs-nav`,`
 width: 100%;
 position: relative;
 `,[a(`tabs-wrapper`,`
 width: 100%;
 `,[a(`tabs-tab`,`
 margin-right: 0;
 `)])])]),a(`tabs-nav`,`
 box-sizing: border-box;
 line-height: 1.5;
 display: flex;
 transition: border-color .3s var(--n-bezier);
 `,[i(`prefix, suffix`,`
 display: flex;
 align-items: center;
 `),i(`prefix`,`padding-right: 16px;`),i(`suffix`,`padding-left: 16px;`)]),h(`top, bottom`,[n(`>`,[a(`tabs-nav`,[a(`tabs-nav-scroll-wrapper`,[n(`&::before`,`
 top: 0;
 bottom: 0;
 left: 0;
 width: 20px;
 `),n(`&::after`,`
 top: 0;
 bottom: 0;
 right: 0;
 width: 20px;
 `),h(`shadow-start`,[n(`&::before`,`
 box-shadow: inset 10px 0 8px -8px rgba(0, 0, 0, .12);
 `)]),h(`shadow-end`,[n(`&::after`,`
 box-shadow: inset -10px 0 8px -8px rgba(0, 0, 0, .12);
 `)])])])])]),h(`left, right`,[a(`tabs-nav-scroll-content`,`
 flex-direction: column;
 `),n(`>`,[a(`tabs-nav`,[a(`tabs-nav-scroll-wrapper`,[n(`&::before`,`
 top: 0;
 left: 0;
 right: 0;
 height: 20px;
 `),n(`&::after`,`
 bottom: 0;
 left: 0;
 right: 0;
 height: 20px;
 `),h(`shadow-start`,[n(`&::before`,`
 box-shadow: inset 0 10px 8px -8px rgba(0, 0, 0, .12);
 `)]),h(`shadow-end`,[n(`&::after`,`
 box-shadow: inset 0 -10px 8px -8px rgba(0, 0, 0, .12);
 `)])])])])]),a(`tabs-nav-scroll-wrapper`,`
 flex: 1;
 position: relative;
 overflow: hidden;
 `,[a(`tabs-nav-y-scroll`,`
 height: 100%;
 width: 100%;
 overflow-y: auto; 
 scrollbar-width: none;
 `,[n(`&::-webkit-scrollbar, &::-webkit-scrollbar-track-piece, &::-webkit-scrollbar-thumb`,`
 width: 0;
 height: 0;
 display: none;
 `)]),n(`&::before, &::after`,`
 transition: box-shadow .3s var(--n-bezier);
 pointer-events: none;
 content: "";
 position: absolute;
 z-index: 1;
 `)]),a(`tabs-nav-scroll-content`,`
 display: flex;
 position: relative;
 min-width: 100%;
 min-height: 100%;
 width: fit-content;
 box-sizing: border-box;
 `),a(`tabs-wrapper`,`
 display: inline-flex;
 flex-wrap: nowrap;
 position: relative;
 `),a(`tabs-tab-wrapper`,`
 display: flex;
 flex-wrap: nowrap;
 flex-shrink: 0;
 flex-grow: 0;
 `),a(`tabs-tab`,`
 cursor: pointer;
 white-space: nowrap;
 flex-wrap: nowrap;
 display: inline-flex;
 align-items: center;
 color: var(--n-tab-text-color);
 font-size: var(--n-tab-font-size);
 background-clip: padding-box;
 padding: var(--n-tab-padding);
 transition:
 box-shadow .3s var(--n-bezier),
 color .3s var(--n-bezier),
 background-color .3s var(--n-bezier),
 border-color .3s var(--n-bezier);
 `,[h(`disabled`,{cursor:`not-allowed`}),i(`close`,`
 margin-left: 6px;
 transition:
 background-color .3s var(--n-bezier),
 color .3s var(--n-bezier);
 `),i(`label`,`
 display: flex;
 align-items: center;
 z-index: 1;
 `)]),a(`tabs-bar`,`
 position: absolute;
 bottom: 0;
 height: 2px;
 border-radius: 1px;
 background-color: var(--n-bar-color);
 transition:
 left .2s var(--n-bezier),
 max-width .2s var(--n-bezier),
 opacity .3s var(--n-bezier),
 background-color .3s var(--n-bezier);
 `,[n(`&.transition-disabled`,`
 transition: none;
 `),h(`disabled`,`
 background-color: var(--n-tab-text-color-disabled)
 `)]),a(`tabs-pane-wrapper`,`
 position: relative;
 overflow: hidden;
 transition: max-height .2s var(--n-bezier);
 `),a(`tab-pane`,`
 color: var(--n-pane-text-color);
 width: 100%;
 transition:
 color .3s var(--n-bezier),
 background-color .3s var(--n-bezier),
 opacity .2s var(--n-bezier);
 left: 0;
 right: 0;
 top: 0;
 `,[n(`&.next-transition-leave-active, &.prev-transition-leave-active, &.next-transition-enter-active, &.prev-transition-enter-active`,`
 transition:
 color .3s var(--n-bezier),
 background-color .3s var(--n-bezier),
 transform .2s var(--n-bezier),
 opacity .2s var(--n-bezier);
 `),n(`&.next-transition-leave-active, &.prev-transition-leave-active`,`
 position: absolute;
 `),n(`&.next-transition-enter-from, &.prev-transition-leave-to`,`
 transform: translateX(32px);
 opacity: 0;
 `),n(`&.next-transition-leave-to, &.prev-transition-enter-from`,`
 transform: translateX(-32px);
 opacity: 0;
 `),n(`&.next-transition-leave-from, &.next-transition-enter-to, &.prev-transition-leave-from, &.prev-transition-enter-to`,`
 transform: translateX(0);
 opacity: 1;
 `)]),a(`tabs-tab-pad`,`
 box-sizing: border-box;
 width: var(--n-tab-gap);
 flex-grow: 0;
 flex-shrink: 0;
 `),h(`line-type, bar-type`,[a(`tabs-tab`,`
 font-weight: var(--n-tab-font-weight);
 box-sizing: border-box;
 vertical-align: bottom;
 `,[n(`&:hover`,{color:`var(--n-tab-text-color-hover)`}),h(`active`,`
 color: var(--n-tab-text-color-active);
 font-weight: var(--n-tab-font-weight-active);
 `),h(`disabled`,{color:`var(--n-tab-text-color-disabled)`})])]),a(`tabs-nav`,[h(`line-type`,[h(`top`,[i(`prefix, suffix`,`
 border-bottom: 1px solid var(--n-tab-border-color);
 `),a(`tabs-nav-scroll-content`,`
 border-bottom: 1px solid var(--n-tab-border-color);
 `),a(`tabs-bar`,`
 bottom: -1px;
 `)]),h(`left`,[i(`prefix, suffix`,`
 border-right: 1px solid var(--n-tab-border-color);
 `),a(`tabs-nav-scroll-content`,`
 border-right: 1px solid var(--n-tab-border-color);
 `),a(`tabs-bar`,`
 right: -1px;
 `)]),h(`right`,[i(`prefix, suffix`,`
 border-left: 1px solid var(--n-tab-border-color);
 `),a(`tabs-nav-scroll-content`,`
 border-left: 1px solid var(--n-tab-border-color);
 `),a(`tabs-bar`,`
 left: -1px;
 `)]),h(`bottom`,[i(`prefix, suffix`,`
 border-top: 1px solid var(--n-tab-border-color);
 `),a(`tabs-nav-scroll-content`,`
 border-top: 1px solid var(--n-tab-border-color);
 `),a(`tabs-bar`,`
 top: -1px;
 `)]),i(`prefix, suffix`,`
 transition: border-color .3s var(--n-bezier);
 `),a(`tabs-nav-scroll-content`,`
 transition: border-color .3s var(--n-bezier);
 `),a(`tabs-bar`,`
 border-radius: 0;
 `)]),h(`card-type`,[i(`prefix, suffix`,`
 transition: border-color .3s var(--n-bezier);
 `),a(`tabs-pad`,`
 flex-grow: 1;
 transition: border-color .3s var(--n-bezier);
 `),a(`tabs-tab-pad`,`
 transition: border-color .3s var(--n-bezier);
 `),a(`tabs-tab`,`
 font-weight: var(--n-tab-font-weight);
 border: 1px solid var(--n-tab-border-color);
 background-color: var(--n-tab-color);
 box-sizing: border-box;
 position: relative;
 vertical-align: bottom;
 display: flex;
 justify-content: space-between;
 font-size: var(--n-tab-font-size);
 color: var(--n-tab-text-color);
 `,[h(`addable`,`
 padding-left: 8px;
 padding-right: 8px;
 font-size: 16px;
 justify-content: center;
 `,[i(`height-placeholder`,`
 width: 0;
 font-size: var(--n-tab-font-size);
 `),m(`disabled`,[n(`&:hover`,`
 color: var(--n-tab-text-color-hover);
 `)])]),h(`closable`,`padding-right: 8px;`),h(`active`,`
 background-color: #0000;
 font-weight: var(--n-tab-font-weight-active);
 color: var(--n-tab-text-color-active);
 `),h(`disabled`,`color: var(--n-tab-text-color-disabled);`)])]),h(`left, right`,`
 flex-direction: column; 
 `,[i(`prefix, suffix`,`
 padding: var(--n-tab-padding-vertical);
 `),a(`tabs-wrapper`,`
 flex-direction: column;
 `),a(`tabs-tab-wrapper`,`
 flex-direction: column;
 `,[a(`tabs-tab-pad`,`
 height: var(--n-tab-gap-vertical);
 width: 100%;
 `)])]),h(`top`,[h(`card-type`,[a(`tabs-scroll-padding`,`border-bottom: 1px solid var(--n-tab-border-color);`),i(`prefix, suffix`,`
 border-bottom: 1px solid var(--n-tab-border-color);
 `),a(`tabs-tab`,`
 border-top-left-radius: var(--n-tab-border-radius);
 border-top-right-radius: var(--n-tab-border-radius);
 `,[h(`active`,`
 border-bottom: 1px solid #0000;
 `)]),a(`tabs-tab-pad`,`
 border-bottom: 1px solid var(--n-tab-border-color);
 `),a(`tabs-pad`,`
 border-bottom: 1px solid var(--n-tab-border-color);
 `)])]),h(`left`,[h(`card-type`,[a(`tabs-scroll-padding`,`border-right: 1px solid var(--n-tab-border-color);`),i(`prefix, suffix`,`
 border-right: 1px solid var(--n-tab-border-color);
 `),a(`tabs-tab`,`
 border-top-left-radius: var(--n-tab-border-radius);
 border-bottom-left-radius: var(--n-tab-border-radius);
 `,[h(`active`,`
 border-right: 1px solid #0000;
 `)]),a(`tabs-tab-pad`,`
 border-right: 1px solid var(--n-tab-border-color);
 `),a(`tabs-pad`,`
 border-right: 1px solid var(--n-tab-border-color);
 `)])]),h(`right`,[h(`card-type`,[a(`tabs-scroll-padding`,`border-left: 1px solid var(--n-tab-border-color);`),i(`prefix, suffix`,`
 border-left: 1px solid var(--n-tab-border-color);
 `),a(`tabs-tab`,`
 border-top-right-radius: var(--n-tab-border-radius);
 border-bottom-right-radius: var(--n-tab-border-radius);
 `,[h(`active`,`
 border-left: 1px solid #0000;
 `)]),a(`tabs-tab-pad`,`
 border-left: 1px solid var(--n-tab-border-color);
 `),a(`tabs-pad`,`
 border-left: 1px solid var(--n-tab-border-color);
 `)])]),h(`bottom`,[h(`card-type`,[a(`tabs-scroll-padding`,`border-top: 1px solid var(--n-tab-border-color);`),i(`prefix, suffix`,`
 border-top: 1px solid var(--n-tab-border-color);
 `),a(`tabs-tab`,`
 border-bottom-left-radius: var(--n-tab-border-radius);
 border-bottom-right-radius: var(--n-tab-border-radius);
 `,[h(`active`,`
 border-top: 1px solid #0000;
 `)]),a(`tabs-tab-pad`,`
 border-top: 1px solid var(--n-tab-border-color);
 `),a(`tabs-pad`,`
 border-top: 1px solid var(--n-tab-border-color);
 `)])])])]),xe=ge,Se=Object.assign(Object.assign({},x.props),{value:[String,Number],defaultValue:[String,Number],trigger:{type:String,default:`click`},type:{type:String,default:`bar`},closable:Boolean,justifyContent:String,size:String,placement:{type:String,default:`top`},tabStyle:[String,Object],tabClass:String,addTabStyle:[String,Object],addTabClass:String,barWidth:Number,paneClass:String,paneStyle:[String,Object],paneWrapperClass:String,paneWrapperStyle:[String,Object],addable:[Boolean,Object],tabsPadding:{type:Number,default:0},animated:Boolean,onBeforeLeave:Function,onAdd:Function,"onUpdate:value":[Function,Array],onUpdateValue:[Function,Array],onClose:[Function,Array],labelSize:String,activeName:[String,Number],onActiveNameChange:[Function,Array]}),Ce=E({name:`Tabs`,props:Se,slots:Object,setup(e,{slots:t}){let{mergedClsPrefixRef:n,inlineThemeDisabled:r,mergedComponentPropsRef:i}=u(e),a=x(`Tabs`,`-tabs`,be,se,e,n),s=S(null),l=S(null),f=S(null),m=S(null),h=S(null),g=S(null),y=S(!0),w=S(!0),T=oe(e,[`labelSize`,`size`]),E=b(()=>T.value?T.value:i?.value?.Tabs?.size||`medium`),D=oe(e,[`activeName`,`value`]),O=S(D.value??e.defaultValue??(t.default?R(t.default())[0]?.props?.name:null)),k=ae(D,O),j={id:0},M=b(()=>{if(!(!e.justifyContent||e.type===`card`))return{display:`flex`,justifyContent:e.justifyContent}});o(k,()=>{j.id=0,I(),L()});function P(){let{value:e}=k;return e===null?null:s.value?.querySelector(`[data-name="${e}"]`)}function te(t){if(e.type===`card`)return;let{value:r}=l;if(!r)return;let i=r.style.opacity===`0`;if(t){let a=`${n.value}-tabs-bar--disabled`,{barWidth:o,placement:s}=e;if(t.dataset.disabled===`true`?r.classList.add(a):r.classList.remove(a),[`top`,`bottom`].includes(s)){if(F([`top`,`maxHeight`,`height`]),typeof o==`number`&&t.offsetWidth>=o){let e=Math.floor((t.offsetWidth-o)/2)+t.offsetLeft;r.style.left=`${e}px`,r.style.maxWidth=`${o}px`}else r.style.left=`${t.offsetLeft}px`,r.style.maxWidth=`${t.offsetWidth}px`;r.style.width=`8192px`,i&&(r.style.transition=`none`),r.offsetWidth,i&&(r.style.transition=``,r.style.opacity=`1`)}else{if(F([`left`,`maxWidth`,`width`]),typeof o==`number`&&t.offsetHeight>=o){let e=Math.floor((t.offsetHeight-o)/2)+t.offsetTop;r.style.top=`${e}px`,r.style.maxHeight=`${o}px`}else r.style.top=`${t.offsetTop}px`,r.style.maxHeight=`${t.offsetHeight}px`;r.style.height=`8192px`,i&&(r.style.transition=`none`),r.offsetHeight,i&&(r.style.transition=``,r.style.opacity=`1`)}}}function re(){if(e.type===`card`)return;let{value:t}=l;t&&(t.style.opacity=`0`)}function F(e){let{value:t}=l;if(t)for(let n of e)t.style[n]=``}function I(){if(e.type===`card`)return;let t=P();t?te(t):re()}function L(){let e=h.value?.$el;if(!e)return;let t=P();if(!t)return;let{scrollLeft:n,offsetWidth:r}=e,{offsetLeft:i,offsetWidth:a}=t;n>i?e.scrollTo({top:0,left:i,behavior:`smooth`}):i+a>n+r&&e.scrollTo({top:0,left:i+a-r,behavior:`smooth`})}let z=S(null),B=0,V=null;function ce(e){let t=z.value;if(t){B=e.getBoundingClientRect().height;let n=`${B}px`,r=()=>{t.style.height=n,t.style.maxHeight=n};V?(r(),V(),V=null):V=r}}function le(e){let t=z.value;if(t){let n=e.getBoundingClientRect().height,r=()=>{document.body.offsetHeight,t.style.maxHeight=`${n}px`,t.style.height=`${Math.max(B,n)}px`};V?(V(),V=null,r()):V=r}}function ue(){let t=z.value;if(t){t.style.maxHeight=``,t.style.height=``;let{paneWrapperStyle:n}=e;if(typeof n==`string`)t.style.cssText=n;else if(n){let{maxHeight:e,height:r}=n;e!==void 0&&(t.style.maxHeight=e),r!==void 0&&(t.style.height=r)}}}let H={value:[]},U=S(`next`);function W(e){let t=k.value,n=`next`;for(let r of H.value){if(r===t)break;if(r===e){n=`prev`;break}}U.value=n,de(e)}function de(t){let{onActiveNameChange:n,onUpdateValue:r,"onUpdate:value":i}=e;n&&A(n,t),r&&A(r,t),i&&A(i,t),O.value=t}function fe(t){let{onClose:n}=e;n&&A(n,t)}let G=!0;function K(){let{value:e}=l;if(!e)return;G||=!1;let t=`transition-disabled`;e.classList.add(t),I(),e.classList.remove(t)}let q=S(null);function J({transitionDisabled:e}){let t=s.value;if(!t)return;e&&t.classList.add(`transition-disabled`);let n=P();n&&q.value&&(q.value.style.width=`${n.offsetWidth}px`,q.value.style.height=`${n.offsetHeight}px`,q.value.style.transform=`translateX(${n.offsetLeft-ne(getComputedStyle(t).paddingLeft)}px)`,e&&q.value.offsetWidth),e&&t.classList.remove(`transition-disabled`)}o([k],()=>{e.type===`segment`&&C(()=>{J({transitionDisabled:!1})})}),d(()=>{e.type===`segment`&&J({transitionDisabled:!0})});let Y=0;function pe(t){if(t.contentRect.width===0&&t.contentRect.height===0||Y===t.contentRect.width)return;Y=t.contentRect.width;let{type:n}=e;if((n===`line`||n===`bar`)&&(G||e.justifyContent?.startsWith(`space`))&&K(),n!==`segment`){let{placement:t}=e;Z((t===`top`||t===`bottom`?h.value?.$el:g.value)||null)}}let me=xe(pe,64);o([()=>e.justifyContent,()=>e.size],()=>{C(()=>{let{type:t}=e;(t===`line`||t===`bar`)&&K()})});let X=S(!1);function he(t){let{target:n,contentRect:{width:r,height:i}}=t,a=n.parentElement.parentElement.offsetWidth,o=n.parentElement.parentElement.offsetHeight,{placement:s}=e;if(!X.value)s===`top`||s===`bottom`?a<r&&(X.value=!0):o<i&&(X.value=!0);else{let{value:e}=m;if(!e)return;s===`top`||s===`bottom`?a-r>e.$el.offsetWidth&&(X.value=!1):o-i>e.$el.offsetHeight&&(X.value=!1)}Z(h.value?.$el||null)}let ge=xe(he,64);function ve(){let{onAdd:t}=e;t&&t(),C(()=>{let e=P(),{value:t}=h;!e||!t||t.scrollTo({left:e.offsetLeft,top:0,behavior:`smooth`})})}function Z(t){if(!t)return;let{placement:n}=e;if(n===`top`||n===`bottom`){let{scrollLeft:e,scrollWidth:n,offsetWidth:r}=t;y.value=e<=0,w.value=e+r>=n}else{let{scrollTop:e,scrollHeight:n,offsetHeight:r}=t;y.value=e<=0,w.value=e+r>=n}}let ye=xe(e=>{Z(e.target)},64);p(_e,{triggerRef:v(e,`trigger`),tabStyleRef:v(e,`tabStyle`),tabClassRef:v(e,`tabClass`),addTabStyleRef:v(e,`addTabStyle`),addTabClassRef:v(e,`addTabClass`),paneClassRef:v(e,`paneClass`),paneStyleRef:v(e,`paneStyle`),mergedClsPrefixRef:n,typeRef:v(e,`type`),closableRef:v(e,`closable`),valueRef:k,tabChangeIdRef:j,onBeforeLeaveRef:v(e,`onBeforeLeave`),activateTab:W,handleClose:fe,handleAdd:ve}),ie(()=>{I(),L()}),ee(()=>{let{value:e}=f;if(!e)return;let{value:t}=n,r=`${t}-tabs-nav-scroll-wrapper--shadow-start`,i=`${t}-tabs-nav-scroll-wrapper--shadow-end`;y.value?e.classList.remove(r):e.classList.add(r),w.value?e.classList.remove(i):e.classList.add(i)});let Q={syncBarPosition:()=>{I()}},Se=()=>{J({transitionDisabled:!0})},Ce=b(()=>{let{value:t}=E,{type:n}=e,r=`${t}${{card:`Card`,bar:`Bar`,line:`Line`,segment:`Segment`}[n]}`,{self:{barColor:i,closeIconColor:o,closeIconColorHover:s,closeIconColorPressed:c,tabColor:l,tabBorderColor:u,paneTextColor:d,tabFontWeight:f,tabBorderRadius:p,tabFontWeightActive:m,colorSegment:h,fontWeightStrong:g,tabColorSegment:v,closeSize:y,closeIconSize:b,closeColorHover:x,closeColorPressed:S,closeBorderRadius:C,[_(`panePadding`,t)]:w,[_(`tabPadding`,r)]:ee,[_(`tabPaddingVertical`,r)]:T,[_(`tabGap`,r)]:D,[_(`tabGap`,`${r}Vertical`)]:O,[_(`tabTextColor`,n)]:k,[_(`tabTextColorActive`,n)]:A,[_(`tabTextColorHover`,n)]:j,[_(`tabTextColorDisabled`,n)]:M,[_(`tabFontSize`,t)]:P},common:{cubicBezierEaseInOut:te}}=a.value;return{"--n-bezier":te,"--n-color-segment":h,"--n-bar-color":i,"--n-tab-font-size":P,"--n-tab-text-color":k,"--n-tab-text-color-active":A,"--n-tab-text-color-disabled":M,"--n-tab-text-color-hover":j,"--n-pane-text-color":d,"--n-tab-border-color":u,"--n-tab-border-radius":p,"--n-close-size":y,"--n-close-icon-size":b,"--n-close-color-hover":x,"--n-close-color-pressed":S,"--n-close-border-radius":C,"--n-close-icon-color":o,"--n-close-icon-color-hover":s,"--n-close-icon-color-pressed":c,"--n-tab-color":l,"--n-tab-font-weight":f,"--n-tab-font-weight-active":m,"--n-tab-padding":ee,"--n-tab-padding-vertical":T,"--n-tab-gap":D,"--n-tab-gap-vertical":O,"--n-pane-padding-left":N(w,`left`),"--n-pane-padding-right":N(w,`right`),"--n-pane-padding-top":N(w,`top`),"--n-pane-padding-bottom":N(w,`bottom`),"--n-font-weight-strong":g,"--n-tab-color-segment":v}}),$=r?c(`tabs`,b(()=>`${E.value[0]}${e.type[0]}`),Ce,e):void 0;return Object.assign({mergedClsPrefix:n,mergedValue:k,renderedNames:new Set,segmentCapsuleElRef:q,tabsPaneWrapperRef:z,tabsElRef:s,barElRef:l,addTabInstRef:m,xScrollInstRef:h,scrollWrapperElRef:f,addTabFixed:X,tabWrapperStyle:M,handleNavResize:me,mergedSize:E,handleScroll:ye,handleTabsResize:ge,cssVars:r?void 0:Ce,themeClass:$?.themeClass,animationDirection:U,renderNameListRef:H,yScrollElRef:g,handleSegmentResize:Se,onAnimationBeforeLeave:ce,onAnimationEnter:le,onAnimationAfterEnter:ue,onRender:$?.onRender},Q)},render(){let{mergedClsPrefix:e,type:t,placement:n,addTabFixed:r,addable:i,mergedSize:a,renderNameListRef:o,onRender:s,paneWrapperClass:c,paneWrapperStyle:l,$slots:{default:u,prefix:d,suffix:f}}=this;s?.();let p=u?R(u()).filter(e=>e.type.__TAB_PANE__===!0):[],m=u?R(u()).filter(e=>e.type.__TAB__===!0):[],h=!m.length,g=t===`card`,_=t===`segment`,v=!g&&!_&&this.justifyContent;o.value=[];let y=()=>{let t=T(`div`,{style:this.tabWrapperStyle,class:`${e}-tabs-wrapper`},v?null:T(`div`,{class:`${e}-tabs-scroll-padding`,style:n===`top`||n===`bottom`?{width:`${this.tabsPadding}px`}:{height:`${this.tabsPadding}px`}}),h?p.map((e,t)=>(o.value.push(e.props.name),Ee(T(Q,Object.assign({},e.props,{internalCreatedByPane:!0,internalLeftPadded:t!==0&&(!v||v===`center`||v===`start`||v===`end`)}),e.children?{default:e.children.tab}:void 0)))):m.map((e,t)=>(o.value.push(e.props.name),Ee(t!==0&&!v?Te(e):e))),!r&&i&&g?we(i,(h?p.length:m.length)!==0):null,v?null:T(`div`,{class:`${e}-tabs-scroll-padding`,style:{width:`${this.tabsPadding}px`}}));return T(`div`,{ref:`tabsElRef`,class:`${e}-tabs-nav-scroll-content`},g&&i?T(M,{onResize:this.handleTabsResize},{default:()=>t}):t,g?T(`div`,{class:`${e}-tabs-pad`}):null,g?null:T(`div`,{ref:`barElRef`,class:`${e}-tabs-bar`}))},b=_?`top`:n;return T(`div`,{class:[`${e}-tabs`,this.themeClass,`${e}-tabs--${t}-type`,`${e}-tabs--${a}-size`,v&&`${e}-tabs--flex`,`${e}-tabs--${b}`],style:this.cssVars},T(`div`,{class:[`${e}-tabs-nav--${t}-type`,`${e}-tabs-nav--${b}`,`${e}-tabs-nav`]},j(d,t=>t&&T(`div`,{class:`${e}-tabs-nav__prefix`},t)),_?T(M,{onResize:this.handleSegmentResize},{default:()=>T(`div`,{class:`${e}-tabs-rail`,ref:`tabsElRef`},T(`div`,{class:`${e}-tabs-capsule`,ref:`segmentCapsuleElRef`},T(`div`,{class:`${e}-tabs-wrapper`},T(`div`,{class:`${e}-tabs-tab`}))),h?p.map((e,t)=>(o.value.push(e.props.name),T(Q,Object.assign({},e.props,{internalCreatedByPane:!0,internalLeftPadded:t!==0}),e.children?{default:e.children.tab}:void 0))):m.map((e,t)=>(o.value.push(e.props.name),t===0?e:Te(e))))}):T(M,{onResize:this.handleNavResize},{default:()=>T(`div`,{class:`${e}-tabs-nav-scroll-wrapper`,ref:`scrollWrapperElRef`},[`top`,`bottom`].includes(b)?T(ce,{ref:`xScrollInstRef`,onScroll:this.handleScroll},{default:y}):T(`div`,{class:`${e}-tabs-nav-y-scroll`,onScroll:this.handleScroll,ref:`yScrollElRef`},y()))}),r&&i&&g?we(i,!0):null,j(f,t=>t&&T(`div`,{class:`${e}-tabs-nav__suffix`},t))),h&&(this.animated&&(b===`top`||b===`bottom`)?T(`div`,{ref:`tabsPaneWrapperRef`,style:l,class:[`${e}-tabs-pane-wrapper`,c]},$(p,this.mergedValue,this.renderedNames,this.onAnimationBeforeLeave,this.onAnimationEnter,this.onAnimationAfterEnter,this.animationDirection)):$(p,this.mergedValue,this.renderedNames)))}});function $(e,t,n,r,i,a,o){let s=[];return e.forEach(e=>{let{name:r,displayDirective:i,"display-directive":a}=e.props,o=e=>i===e||a===e,c=t===r;if(e.key!==void 0&&(e.key=r),c||o(`show`)||o(`show:lazy`)&&n.has(r)){n.has(r)||n.add(r);let t=!o(`if`);s.push(t?g(e,[[O,c]]):e)}}),o?T(k,{name:`${o}-transition`,onBeforeLeave:r,onEnter:i,onAfterEnter:a},{default:()=>s}):s}function we(e,t){return T(Q,{ref:`addTabInstRef`,key:`__addable`,name:`__addable`,internalCreatedByPane:!0,internalAddable:!0,internalLeftPadded:t,disabled:typeof e==`object`&&e.disabled})}function Te(e){let t=y(e);return t.props?t.props.internalLeftPadded=!0:t.props={internalLeftPadded:!0},t}function Ee(e){return Array.isArray(e.dynamicProps)?e.dynamicProps.includes(`internalLeftPadded`)||e.dynamicProps.push(`internalLeftPadded`):e.dynamicProps=[`internalLeftPadded`],e}export{Z as n,Ce as t};