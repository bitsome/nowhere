import{$ as e,B as t,Ct as n,Ft as r,J as i,K as a,Kt as o,Nt as s,V as c,Vt as l,Y as u,a as d,at as f,c as p,cn as m,d as h,et as g,it as _,jt as v,nt as y,rt as b,s as x}from"./_plugin-vue_export-helper-BRaiA-U2.js";import{a as S,c as C,d as w,f as T,h as E,i as D,m as O,o as k,p as A,r as j,t as M}from"./light-C6m6bYe2.js";var N=a(`n-form-item`);function P(e,{defaultSize:t=`medium`,mergedSize:i,mergedDisabled:a}={}){let s=r(N,null);o(N,null);let c=n(i?()=>i(s):()=>{let{size:n}=e;if(n)return n;if(s){let{mergedSize:e}=s;if(e.value!==void 0)return e.value}return t}),u=n(a?()=>a(s):()=>{let{disabled:t}=e;return t===void 0?s?s.disabled.value:!1:t}),d=n(()=>{let{status:t}=e;return t||s?.mergedValidationStatus.value});return l(()=>{s&&s.restoreValidation()}),{mergedSizeRef:c,mergedDisabledRef:u,mergedStatusRef:d,nTriggerFormBlur(){s&&s.handleContentBlur()},nTriggerFormChange(){s&&s.handleContentChange()},nTriggerFormFocus(){s&&s.handleContentFocus()},nTriggerFormInput(){s&&s.handleContentInput()}}}var F=O&&`chrome`in window;O&&navigator.userAgent.includes(`Firefox`);var I=O&&navigator.userAgent.includes(`Safari`)&&!F;function L(e){return u(e,[255,255,255,.16])}function R(e){return u(e,[0,0,0,.12])}var z=a(`n-button-group`),B=e([g(`button`,`
 margin: 0;
 font-weight: var(--n-font-weight);
 line-height: 1;
 font-family: inherit;
 padding: var(--n-padding);
 height: var(--n-height);
 font-size: var(--n-font-size);
 border-radius: var(--n-border-radius);
 color: var(--n-text-color);
 background-color: var(--n-color);
 width: var(--n-width);
 white-space: nowrap;
 outline: none;
 position: relative;
 z-index: auto;
 border: none;
 display: inline-flex;
 flex-wrap: nowrap;
 flex-shrink: 0;
 align-items: center;
 justify-content: center;
 user-select: none;
 -webkit-user-select: none;
 text-align: center;
 cursor: pointer;
 text-decoration: none;
 transition:
 color .3s var(--n-bezier),
 background-color .3s var(--n-bezier),
 opacity .3s var(--n-bezier),
 border-color .3s var(--n-bezier);
 `,[b(`color`,[y(`border`,{borderColor:`var(--n-border-color)`}),b(`disabled`,[y(`border`,{borderColor:`var(--n-border-color-disabled)`})]),_(`disabled`,[e(`&:focus`,[y(`state-border`,{borderColor:`var(--n-border-color-focus)`})]),e(`&:hover`,[y(`state-border`,{borderColor:`var(--n-border-color-hover)`})]),e(`&:active`,[y(`state-border`,{borderColor:`var(--n-border-color-pressed)`})]),b(`pressed`,[y(`state-border`,{borderColor:`var(--n-border-color-pressed)`})])])]),b(`disabled`,{backgroundColor:`var(--n-color-disabled)`,color:`var(--n-text-color-disabled)`},[y(`border`,{border:`var(--n-border-disabled)`})]),_(`disabled`,[e(`&:focus`,{backgroundColor:`var(--n-color-focus)`,color:`var(--n-text-color-focus)`},[y(`state-border`,{border:`var(--n-border-focus)`})]),e(`&:hover`,{backgroundColor:`var(--n-color-hover)`,color:`var(--n-text-color-hover)`},[y(`state-border`,{border:`var(--n-border-hover)`})]),e(`&:active`,{backgroundColor:`var(--n-color-pressed)`,color:`var(--n-text-color-pressed)`},[y(`state-border`,{border:`var(--n-border-pressed)`})]),b(`pressed`,{backgroundColor:`var(--n-color-pressed)`,color:`var(--n-text-color-pressed)`},[y(`state-border`,{border:`var(--n-border-pressed)`})])]),b(`loading`,`cursor: wait;`),g(`base-wave`,`
 pointer-events: none;
 top: 0;
 right: 0;
 bottom: 0;
 left: 0;
 animation-iteration-count: 1;
 animation-duration: var(--n-ripple-duration);
 animation-timing-function: var(--n-bezier-ease-out), var(--n-bezier-ease-out);
 `,[b(`active`,{zIndex:1,animationName:`button-wave-spread, button-wave-opacity`})]),O&&`MozBoxSizing`in document.createElement(`div`).style?e(`&::moz-focus-inner`,{border:0}):null,y(`border, state-border`,`
 position: absolute;
 left: 0;
 top: 0;
 right: 0;
 bottom: 0;
 border-radius: inherit;
 transition: border-color .3s var(--n-bezier);
 pointer-events: none;
 `),y(`border`,`
 border: var(--n-border);
 `),y(`state-border`,`
 border: var(--n-border);
 border-color: #0000;
 z-index: 1;
 `),y(`icon`,`
 margin: var(--n-icon-margin);
 margin-left: 0;
 height: var(--n-icon-size);
 width: var(--n-icon-size);
 max-width: var(--n-icon-size);
 font-size: var(--n-icon-size);
 position: relative;
 flex-shrink: 0;
 `,[g(`icon-slot`,`
 height: var(--n-icon-size);
 width: var(--n-icon-size);
 position: absolute;
 left: 0;
 top: 50%;
 transform: translateY(-50%);
 display: flex;
 align-items: center;
 justify-content: center;
 `,[x({top:`50%`,originalTransform:`translateY(-50%)`})]),D()]),y(`content`,`
 display: flex;
 align-items: center;
 flex-wrap: nowrap;
 min-width: 0;
 `,[e(`~`,[y(`icon`,{margin:`var(--n-icon-margin)`,marginRight:0})])]),b(`block`,`
 display: flex;
 width: 100%;
 `),b(`dashed`,[y(`border, state-border`,{borderStyle:`dashed !important`})]),b(`disabled`,{cursor:`not-allowed`,opacity:`var(--n-opacity-disabled)`})]),e(`@keyframes button-wave-spread`,{from:{boxShadow:`0 0 0.5px 0 var(--n-ripple-color)`},to:{boxShadow:`0 0 0.5px 4.5px var(--n-ripple-color)`}}),e(`@keyframes button-wave-opacity`,{from:{opacity:`var(--n-wave-opacity)`},to:{opacity:0}})]),V=Object.assign(Object.assign({},h.props),{color:String,textColor:String,text:Boolean,block:Boolean,loading:Boolean,disabled:Boolean,circle:Boolean,size:String,ghost:Boolean,round:Boolean,secondary:Boolean,tertiary:Boolean,quaternary:Boolean,strong:Boolean,focusable:{type:Boolean,default:!0},keyboard:{type:Boolean,default:!0},tag:{type:String,default:`button`},type:{type:String,default:`default`},dashed:Boolean,renderIcon:Function,iconPlacement:{type:String,default:`left`},attrType:{type:String,default:`button`},bordered:{type:Boolean,default:!0},onClick:[Function,Array],nativeFocusBehavior:{type:Boolean,default:!I},spinProps:Object}),H=v({name:`Button`,props:V,slots:Object,setup(e){let a=m(null),o=m(null),s=m(!1),l=E(()=>!e.quaternary&&!e.tertiary&&!e.secondary&&!e.text&&(!e.color||e.ghost||e.dashed)&&e.bordered),u=r(z,{}),{inlineThemeDisabled:d,mergedClsPrefixRef:p,mergedRtlRef:g,mergedComponentPropsRef:_}=c(e),{mergedSizeRef:v}=P({},{defaultSize:`medium`,mergedSize:t=>{let{size:n}=e;if(n)return n;let{size:r}=u;if(r)return r;let{mergedSize:i}=t||{};return i?i.value:_?.value?.Button?.size||`medium`}}),y=n(()=>e.focusable&&!e.disabled),b=t=>{var n;y.value||t.preventDefault(),!e.nativeFocusBehavior&&(t.preventDefault(),!e.disabled&&y.value&&((n=a.value)==null||n.focus({preventScroll:!0})))},x=t=>{var n;if(!e.disabled&&!e.loading){let{onClick:r}=e;r&&T(r,t),e.text||(n=o.value)==null||n.play()}},S=t=>{if(t.key===`Enter`){if(!e.keyboard)return;s.value=!1}},C=t=>{if(t.key===`Enter`){if(!e.keyboard||e.loading){t.preventDefault();return}s.value=!0}},w=()=>{s.value=!1},D=h(`Button`,`-button`,B,M,e,p),O=k(`Button`,g,p),j=n(()=>{let{common:{cubicBezierEaseInOut:t,cubicBezierEaseOut:n},self:r}=D.value,{rippleDuration:a,opacityDisabled:o,fontWeight:s,fontWeightStrong:c}=r,l=v.value,{dashed:u,type:d,ghost:p,text:m,color:h,round:g,circle:_,textColor:y,secondary:b,tertiary:x,quaternary:S,strong:C}=e,w={"--n-font-weight":C?c:s},T={"--n-color":`initial`,"--n-color-hover":`initial`,"--n-color-pressed":`initial`,"--n-color-focus":`initial`,"--n-color-disabled":`initial`,"--n-ripple-color":`initial`,"--n-text-color":`initial`,"--n-text-color-hover":`initial`,"--n-text-color-pressed":`initial`,"--n-text-color-focus":`initial`,"--n-text-color-disabled":`initial`},E=d===`tertiary`,O=d==="default",k=E?`default`:d;if(m){let e=y||h;T={"--n-color":`#0000`,"--n-color-hover":`#0000`,"--n-color-pressed":`#0000`,"--n-color-focus":`#0000`,"--n-color-disabled":`#0000`,"--n-ripple-color":`#0000`,"--n-text-color":e||r[f(`textColorText`,k)],"--n-text-color-hover":e?L(e):r[f(`textColorTextHover`,k)],"--n-text-color-pressed":e?R(e):r[f(`textColorTextPressed`,k)],"--n-text-color-focus":e?L(e):r[f(`textColorTextHover`,k)],"--n-text-color-disabled":e||r[f(`textColorTextDisabled`,k)]}}else if(p||u){let e=y||h;T={"--n-color":`#0000`,"--n-color-hover":`#0000`,"--n-color-pressed":`#0000`,"--n-color-focus":`#0000`,"--n-color-disabled":`#0000`,"--n-ripple-color":h||r[f(`rippleColor`,k)],"--n-text-color":e||r[f(`textColorGhost`,k)],"--n-text-color-hover":e?L(e):r[f(`textColorGhostHover`,k)],"--n-text-color-pressed":e?R(e):r[f(`textColorGhostPressed`,k)],"--n-text-color-focus":e?L(e):r[f(`textColorGhostHover`,k)],"--n-text-color-disabled":e||r[f(`textColorGhostDisabled`,k)]}}else if(b){let e=O?r.textColor:E?r.textColorTertiary:r[f(`color`,k)],t=h||e,n=d!=="default"&&d!==`tertiary`;T={"--n-color":n?i(t,{alpha:Number(r.colorOpacitySecondary)}):r.colorSecondary,"--n-color-hover":n?i(t,{alpha:Number(r.colorOpacitySecondaryHover)}):r.colorSecondaryHover,"--n-color-pressed":n?i(t,{alpha:Number(r.colorOpacitySecondaryPressed)}):r.colorSecondaryPressed,"--n-color-focus":n?i(t,{alpha:Number(r.colorOpacitySecondaryHover)}):r.colorSecondaryHover,"--n-color-disabled":r.colorSecondary,"--n-ripple-color":`#0000`,"--n-text-color":t,"--n-text-color-hover":t,"--n-text-color-pressed":t,"--n-text-color-focus":t,"--n-text-color-disabled":t}}else if(x||S){let e=O?r.textColor:E?r.textColorTertiary:r[f(`color`,k)],t=h||e;x?(T[`--n-color`]=r.colorTertiary,T[`--n-color-hover`]=r.colorTertiaryHover,T[`--n-color-pressed`]=r.colorTertiaryPressed,T[`--n-color-focus`]=r.colorSecondaryHover,T[`--n-color-disabled`]=r.colorTertiary):(T[`--n-color`]=r.colorQuaternary,T[`--n-color-hover`]=r.colorQuaternaryHover,T[`--n-color-pressed`]=r.colorQuaternaryPressed,T[`--n-color-focus`]=r.colorQuaternaryHover,T[`--n-color-disabled`]=r.colorQuaternary),T[`--n-ripple-color`]=`#0000`,T[`--n-text-color`]=t,T[`--n-text-color-hover`]=t,T[`--n-text-color-pressed`]=t,T[`--n-text-color-focus`]=t,T[`--n-text-color-disabled`]=t}else T={"--n-color":h||r[f(`color`,k)],"--n-color-hover":h?L(h):r[f(`colorHover`,k)],"--n-color-pressed":h?R(h):r[f(`colorPressed`,k)],"--n-color-focus":h?L(h):r[f(`colorFocus`,k)],"--n-color-disabled":h||r[f(`colorDisabled`,k)],"--n-ripple-color":h||r[f(`rippleColor`,k)],"--n-text-color":y||(h?r.textColorPrimary:E?r.textColorTertiary:r[f(`textColor`,k)]),"--n-text-color-hover":y||(h?r.textColorHoverPrimary:r[f(`textColorHover`,k)]),"--n-text-color-pressed":y||(h?r.textColorPressedPrimary:r[f(`textColorPressed`,k)]),"--n-text-color-focus":y||(h?r.textColorFocusPrimary:r[f(`textColorFocus`,k)]),"--n-text-color-disabled":y||(h?r.textColorDisabledPrimary:r[f(`textColorDisabled`,k)])};let A={"--n-border":`initial`,"--n-border-hover":`initial`,"--n-border-pressed":`initial`,"--n-border-focus":`initial`,"--n-border-disabled":`initial`};A=m?{"--n-border":`none`,"--n-border-hover":`none`,"--n-border-pressed":`none`,"--n-border-focus":`none`,"--n-border-disabled":`none`}:{"--n-border":r[f(`border`,k)],"--n-border-hover":r[f(`borderHover`,k)],"--n-border-pressed":r[f(`borderPressed`,k)],"--n-border-focus":r[f(`borderFocus`,k)],"--n-border-disabled":r[f(`borderDisabled`,k)]};let{[f(`height`,l)]:j,[f(`fontSize`,l)]:M,[f(`padding`,l)]:N,[f(`paddingRound`,l)]:P,[f(`iconSize`,l)]:F,[f(`borderRadius`,l)]:I,[f(`iconMargin`,l)]:z,waveOpacity:B}=r,V={"--n-width":_&&!m?j:`initial`,"--n-height":m?`initial`:j,"--n-font-size":M,"--n-padding":_||m?`initial`:g?P:N,"--n-icon-size":F,"--n-icon-margin":z,"--n-border-radius":m?`initial`:_||g?j:I};return Object.assign(Object.assign(Object.assign(Object.assign({"--n-bezier":t,"--n-bezier-ease-out":n,"--n-ripple-duration":a,"--n-opacity-disabled":o,"--n-wave-opacity":B},w),T),A),V)}),N=d?t(`button`,n(()=>{let t=``,{dashed:n,type:r,ghost:i,text:a,color:o,round:s,circle:c,textColor:l,secondary:u,tertiary:d,quaternary:f,strong:p}=e;n&&(t+=`a`),i&&(t+=`b`),a&&(t+=`c`),s&&(t+=`d`),c&&(t+=`e`),u&&(t+=`f`),d&&(t+=`g`),f&&(t+=`h`),p&&(t+=`i`),o&&(t+=`j${A(o)}`),l&&(t+=`k${A(l)}`);let{value:m}=v;return t+=`l${m[0]}`,t+=`m${r[0]}`,t}),j,e):void 0;return{selfElRef:a,waveElRef:o,mergedClsPrefix:p,mergedFocusable:y,mergedSize:v,showBorder:l,enterPressed:s,rtlEnabled:O,handleMousedown:b,handleKeydown:C,handleBlur:w,handleKeyup:S,handleClick:x,customColorCssVars:n(()=>{let{color:t}=e;if(!t)return null;let n=L(t);return{"--n-border-color":t,"--n-border-color-hover":n,"--n-border-color-pressed":R(t),"--n-border-color-focus":n,"--n-border-color-disabled":t}}),cssVars:d?void 0:j,themeClass:N?.themeClass,onRender:N?.onRender}},render(){let{mergedClsPrefix:e,tag:t,onRender:n}=this;n?.();let r=w(this.$slots.default,t=>t&&s(`span`,{class:`${e}-button__content`},t));return s(t,{ref:`selfElRef`,class:[this.themeClass,`${e}-button`,`${e}-button--${this.type}-type`,`${e}-button--${this.mergedSize}-type`,this.rtlEnabled&&`${e}-button--rtl`,this.disabled&&`${e}-button--disabled`,this.block&&`${e}-button--block`,this.enterPressed&&`${e}-button--pressed`,!this.text&&this.dashed&&`${e}-button--dashed`,this.color&&`${e}-button--color`,this.secondary&&`${e}-button--secondary`,this.loading&&`${e}-button--loading`,this.ghost&&`${e}-button--ghost`],tabindex:this.mergedFocusable?0:-1,type:this.attrType,style:this.cssVars,disabled:this.disabled,onClick:this.handleClick,onBlur:this.handleBlur,onMousedown:this.handleMousedown,onKeyup:this.handleKeyup,onKeydown:this.handleKeydown},this.iconPlacement===`right`&&r,s(S,{width:!0},{default:()=>w(this.$slots.icon,t=>(this.loading||this.renderIcon||t)&&s(`span`,{class:`${e}-button__icon`,style:{margin:C(this.$slots.default)?`0`:``}},s(p,null,{default:()=>this.loading?s(d,Object.assign({clsPrefix:e,key:`loading`,class:`${e}-icon-slot`,strokeWidth:20},this.spinProps)):s(`div`,{key:`icon`,class:`${e}-icon-slot`,role:`none`},this.renderIcon?this.renderIcon():t)})))}),this.iconPlacement===`left`&&r,this.text?null:s(j,{ref:`waveElRef`,clsPrefix:e}),this.showBorder?s(`div`,{"aria-hidden":!0,class:`${e}-button__border`,style:this.customColorCssVars}):null,this.showBorder?s(`div`,{"aria-hidden":!0,class:`${e}-button__state-border`,style:this.customColorCssVars}):null)}}),U=H;export{P as a,N as i,U as n,I as r,H as t};