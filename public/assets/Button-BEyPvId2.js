import{B as e,Et as t,G as n,J as r,K as i,Kt as a,Mt as o,N as s,P as c,V as l,X as u,Y as d,Z as f,cn as p,ft as m,i as h,in as g,kt as _,n as v,o as y,wt as b,xt as x,z as S}from"./_plugin-vue_export-helper-CHFfEUVo.js";import{a as C,d as w,h as T,i as E,l as D,m as O,o as k,r as A,s as j,t as M}from"./Loading-DTHYZrnQ.js";function N(e){let t=m(e),n=g(t.value);return a(t,e=>{n.value=e}),typeof e==`function`?n:{__v_isRef:!0,get value(){return n.value},set value(t){e.set(t)}}}var P=typeof document<`u`&&typeof window<`u`;function F(e){return e.replace(/#|\(|\)|,|\s|\./g,`_`)}var{cubicBezierEaseInOut:I}=y;function L({duration:e=`.2s`,delay:t=`.1s`}={}){return[n(`&.fade-in-width-expand-transition-leave-from, &.fade-in-width-expand-transition-enter-to`,{opacity:1}),n(`&.fade-in-width-expand-transition-leave-to, &.fade-in-width-expand-transition-enter-from`,`
 opacity: 0!important;
 margin-left: 0!important;
 margin-right: 0!important;
 `),n(`&.fade-in-width-expand-transition-leave-active`,`
 overflow: hidden;
 transition:
 opacity ${e} ${I},
 max-width ${e} ${I} ${t},
 margin-left ${e} ${I} ${t},
 margin-right ${e} ${I} ${t};
 `),n(`&.fade-in-width-expand-transition-enter-active`,`
 overflow: hidden;
 transition:
 opacity ${e} ${I} ${t},
 max-width ${e} ${I},
 margin-left ${e} ${I},
 margin-right ${e} ${I};
 `)]}var R=i(`base-wave`,`
 position: absolute;
 left: 0;
 right: 0;
 top: 0;
 bottom: 0;
 border-radius: inherit;
`),z=x({name:`BaseWave`,props:{clsPrefix:{type:String,required:!0}},setup(e){k(`-base-wave`,R,p(e,`clsPrefix`));let t=g(null),n=g(!1),r=null;return o(()=>{r!==null&&window.clearTimeout(r)}),{active:n,selfRef:t,play(){r!==null&&(window.clearTimeout(r),n.value=!1,r=null),_(()=>{var e;(e=t.value)==null||e.offsetHeight,n.value=!0,r=window.setTimeout(()=>{n.value=!1,r=null},1e3)})}}},render(){let{clsPrefix:e}=this;return b(`div`,{ref:`selfRef`,"aria-hidden":!0,class:[`${e}-base-wave`,this.active&&`${e}-base-wave--active`]})}}),B=P&&`chrome`in window;P&&navigator.userAgent.includes(`Firefox`);var V=P&&navigator.userAgent.includes(`Safari`)&&!B;function H(e){return l(e,[255,255,255,.16])}function U(e){return l(e,[0,0,0,.12])}var W=S(`n-button-group`),G={paddingTiny:`0 6px`,paddingSmall:`0 10px`,paddingMedium:`0 14px`,paddingLarge:`0 18px`,paddingRoundTiny:`0 10px`,paddingRoundSmall:`0 14px`,paddingRoundMedium:`0 18px`,paddingRoundLarge:`0 22px`,iconMarginTiny:`6px`,iconMarginSmall:`6px`,iconMarginMedium:`6px`,iconMarginLarge:`6px`,iconSizeTiny:`14px`,iconSizeSmall:`18px`,iconSizeMedium:`18px`,iconSizeLarge:`20px`,rippleDuration:`.6s`};function K(e){let{heightTiny:t,heightSmall:n,heightMedium:r,heightLarge:i,borderRadius:a,fontSizeTiny:o,fontSizeSmall:s,fontSizeMedium:c,fontSizeLarge:l,opacityDisabled:u,textColor2:d,textColor3:f,primaryColorHover:p,primaryColorPressed:m,borderColor:h,primaryColor:g,baseColor:_,infoColor:v,infoColorHover:y,infoColorPressed:b,successColor:x,successColorHover:S,successColorPressed:C,warningColor:w,warningColorHover:T,warningColorPressed:E,errorColor:D,errorColorHover:O,errorColorPressed:k,fontWeight:A,buttonColor2:j,buttonColor2Hover:M,buttonColor2Pressed:N,fontWeightStrong:P}=e;return Object.assign(Object.assign({},G),{heightTiny:t,heightSmall:n,heightMedium:r,heightLarge:i,borderRadiusTiny:a,borderRadiusSmall:a,borderRadiusMedium:a,borderRadiusLarge:a,fontSizeTiny:o,fontSizeSmall:s,fontSizeMedium:c,fontSizeLarge:l,opacityDisabled:u,colorOpacitySecondary:`0.16`,colorOpacitySecondaryHover:`0.22`,colorOpacitySecondaryPressed:`0.28`,colorSecondary:j,colorSecondaryHover:M,colorSecondaryPressed:N,colorTertiary:j,colorTertiaryHover:M,colorTertiaryPressed:N,colorQuaternary:`#0000`,colorQuaternaryHover:M,colorQuaternaryPressed:N,color:`#0000`,colorHover:`#0000`,colorPressed:`#0000`,colorFocus:`#0000`,colorDisabled:`#0000`,textColor:d,textColorTertiary:f,textColorHover:p,textColorPressed:m,textColorFocus:p,textColorDisabled:d,textColorText:d,textColorTextHover:p,textColorTextPressed:m,textColorTextFocus:p,textColorTextDisabled:d,textColorGhost:d,textColorGhostHover:p,textColorGhostPressed:m,textColorGhostFocus:p,textColorGhostDisabled:d,border:`1px solid ${h}`,borderHover:`1px solid ${p}`,borderPressed:`1px solid ${m}`,borderFocus:`1px solid ${p}`,borderDisabled:`1px solid ${h}`,rippleColor:g,colorPrimary:g,colorHoverPrimary:p,colorPressedPrimary:m,colorFocusPrimary:p,colorDisabledPrimary:g,textColorPrimary:_,textColorHoverPrimary:_,textColorPressedPrimary:_,textColorFocusPrimary:_,textColorDisabledPrimary:_,textColorTextPrimary:g,textColorTextHoverPrimary:p,textColorTextPressedPrimary:m,textColorTextFocusPrimary:p,textColorTextDisabledPrimary:d,textColorGhostPrimary:g,textColorGhostHoverPrimary:p,textColorGhostPressedPrimary:m,textColorGhostFocusPrimary:p,textColorGhostDisabledPrimary:g,borderPrimary:`1px solid ${g}`,borderHoverPrimary:`1px solid ${p}`,borderPressedPrimary:`1px solid ${m}`,borderFocusPrimary:`1px solid ${p}`,borderDisabledPrimary:`1px solid ${g}`,rippleColorPrimary:g,colorInfo:v,colorHoverInfo:y,colorPressedInfo:b,colorFocusInfo:y,colorDisabledInfo:v,textColorInfo:_,textColorHoverInfo:_,textColorPressedInfo:_,textColorFocusInfo:_,textColorDisabledInfo:_,textColorTextInfo:v,textColorTextHoverInfo:y,textColorTextPressedInfo:b,textColorTextFocusInfo:y,textColorTextDisabledInfo:d,textColorGhostInfo:v,textColorGhostHoverInfo:y,textColorGhostPressedInfo:b,textColorGhostFocusInfo:y,textColorGhostDisabledInfo:v,borderInfo:`1px solid ${v}`,borderHoverInfo:`1px solid ${y}`,borderPressedInfo:`1px solid ${b}`,borderFocusInfo:`1px solid ${y}`,borderDisabledInfo:`1px solid ${v}`,rippleColorInfo:v,colorSuccess:x,colorHoverSuccess:S,colorPressedSuccess:C,colorFocusSuccess:S,colorDisabledSuccess:x,textColorSuccess:_,textColorHoverSuccess:_,textColorPressedSuccess:_,textColorFocusSuccess:_,textColorDisabledSuccess:_,textColorTextSuccess:x,textColorTextHoverSuccess:S,textColorTextPressedSuccess:C,textColorTextFocusSuccess:S,textColorTextDisabledSuccess:d,textColorGhostSuccess:x,textColorGhostHoverSuccess:S,textColorGhostPressedSuccess:C,textColorGhostFocusSuccess:S,textColorGhostDisabledSuccess:x,borderSuccess:`1px solid ${x}`,borderHoverSuccess:`1px solid ${S}`,borderPressedSuccess:`1px solid ${C}`,borderFocusSuccess:`1px solid ${S}`,borderDisabledSuccess:`1px solid ${x}`,rippleColorSuccess:x,colorWarning:w,colorHoverWarning:T,colorPressedWarning:E,colorFocusWarning:T,colorDisabledWarning:w,textColorWarning:_,textColorHoverWarning:_,textColorPressedWarning:_,textColorFocusWarning:_,textColorDisabledWarning:_,textColorTextWarning:w,textColorTextHoverWarning:T,textColorTextPressedWarning:E,textColorTextFocusWarning:T,textColorTextDisabledWarning:d,textColorGhostWarning:w,textColorGhostHoverWarning:T,textColorGhostPressedWarning:E,textColorGhostFocusWarning:T,textColorGhostDisabledWarning:w,borderWarning:`1px solid ${w}`,borderHoverWarning:`1px solid ${T}`,borderPressedWarning:`1px solid ${E}`,borderFocusWarning:`1px solid ${T}`,borderDisabledWarning:`1px solid ${w}`,rippleColorWarning:w,colorError:D,colorHoverError:O,colorPressedError:k,colorFocusError:O,colorDisabledError:D,textColorError:_,textColorHoverError:_,textColorPressedError:_,textColorFocusError:_,textColorDisabledError:_,textColorTextError:D,textColorTextHoverError:O,textColorTextPressedError:k,textColorTextFocusError:O,textColorTextDisabledError:d,textColorGhostError:D,textColorGhostHoverError:O,textColorGhostPressedError:k,textColorGhostFocusError:O,textColorGhostDisabledError:D,borderError:`1px solid ${D}`,borderHoverError:`1px solid ${O}`,borderPressedError:`1px solid ${k}`,borderFocusError:`1px solid ${O}`,borderDisabledError:`1px solid ${D}`,rippleColorError:D,waveOpacity:`0.6`,fontWeight:A,fontWeightStrong:P})}var q={name:`Button`,common:v,self:K},J=n([i(`button`,`
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
 `,[d(`color`,[r(`border`,{borderColor:`var(--n-border-color)`}),d(`disabled`,[r(`border`,{borderColor:`var(--n-border-color-disabled)`})]),u(`disabled`,[n(`&:focus`,[r(`state-border`,{borderColor:`var(--n-border-color-focus)`})]),n(`&:hover`,[r(`state-border`,{borderColor:`var(--n-border-color-hover)`})]),n(`&:active`,[r(`state-border`,{borderColor:`var(--n-border-color-pressed)`})]),d(`pressed`,[r(`state-border`,{borderColor:`var(--n-border-color-pressed)`})])])]),d(`disabled`,{backgroundColor:`var(--n-color-disabled)`,color:`var(--n-text-color-disabled)`},[r(`border`,{border:`var(--n-border-disabled)`})]),u(`disabled`,[n(`&:focus`,{backgroundColor:`var(--n-color-focus)`,color:`var(--n-text-color-focus)`},[r(`state-border`,{border:`var(--n-border-focus)`})]),n(`&:hover`,{backgroundColor:`var(--n-color-hover)`,color:`var(--n-text-color-hover)`},[r(`state-border`,{border:`var(--n-border-hover)`})]),n(`&:active`,{backgroundColor:`var(--n-color-pressed)`,color:`var(--n-text-color-pressed)`},[r(`state-border`,{border:`var(--n-border-pressed)`})]),d(`pressed`,{backgroundColor:`var(--n-color-pressed)`,color:`var(--n-text-color-pressed)`},[r(`state-border`,{border:`var(--n-border-pressed)`})])]),d(`loading`,`cursor: wait;`),i(`base-wave`,`
 pointer-events: none;
 top: 0;
 right: 0;
 bottom: 0;
 left: 0;
 animation-iteration-count: 1;
 animation-duration: var(--n-ripple-duration);
 animation-timing-function: var(--n-bezier-ease-out), var(--n-bezier-ease-out);
 `,[d(`active`,{zIndex:1,animationName:`button-wave-spread, button-wave-opacity`})]),P&&`MozBoxSizing`in document.createElement(`div`).style?n(`&::moz-focus-inner`,{border:0}):null,r(`border, state-border`,`
 position: absolute;
 left: 0;
 top: 0;
 right: 0;
 bottom: 0;
 border-radius: inherit;
 transition: border-color .3s var(--n-bezier);
 pointer-events: none;
 `),r(`border`,`
 border: var(--n-border);
 `),r(`state-border`,`
 border: var(--n-border);
 border-color: #0000;
 z-index: 1;
 `),r(`icon`,`
 margin: var(--n-icon-margin);
 margin-left: 0;
 height: var(--n-icon-size);
 width: var(--n-icon-size);
 max-width: var(--n-icon-size);
 font-size: var(--n-icon-size);
 position: relative;
 flex-shrink: 0;
 `,[i(`icon-slot`,`
 height: var(--n-icon-size);
 width: var(--n-icon-size);
 position: absolute;
 left: 0;
 top: 50%;
 transform: translateY(-50%);
 display: flex;
 align-items: center;
 justify-content: center;
 `,[E({top:`50%`,originalTransform:`translateY(-50%)`})]),L()]),r(`content`,`
 display: flex;
 align-items: center;
 flex-wrap: nowrap;
 min-width: 0;
 `,[n(`~`,[r(`icon`,{margin:`var(--n-icon-margin)`,marginRight:0})])]),d(`block`,`
 display: flex;
 width: 100%;
 `),d(`dashed`,[r(`border, state-border`,{borderStyle:`dashed !important`})]),d(`disabled`,{cursor:`not-allowed`,opacity:`var(--n-opacity-disabled)`})]),n(`@keyframes button-wave-spread`,{from:{boxShadow:`0 0 0.5px 0 var(--n-ripple-color)`},to:{boxShadow:`0 0 0.5px 4.5px var(--n-ripple-color)`}}),n(`@keyframes button-wave-opacity`,{from:{opacity:`var(--n-wave-opacity)`},to:{opacity:0}})]),Y=Object.assign(Object.assign({},h.props),{color:String,textColor:String,text:Boolean,block:Boolean,loading:Boolean,disabled:Boolean,circle:Boolean,size:String,ghost:Boolean,round:Boolean,secondary:Boolean,tertiary:Boolean,quaternary:Boolean,strong:Boolean,focusable:{type:Boolean,default:!0},keyboard:{type:Boolean,default:!0},tag:{type:String,default:`button`},type:{type:String,default:`default`},dashed:Boolean,renderIcon:Function,iconPlacement:{type:String,default:`left`},attrType:{type:String,default:`button`},bordered:{type:Boolean,default:!0},onClick:[Function,Array],nativeFocusBehavior:{type:Boolean,default:!V},spinProps:Object}),X=x({name:`Button`,props:Y,slots:Object,setup(n){let r=g(null),i=g(null),a=g(!1),o=N(()=>!n.quaternary&&!n.tertiary&&!n.secondary&&!n.text&&(!n.color||n.ghost||n.dashed)&&n.bordered),l=t(W,{}),{inlineThemeDisabled:u,mergedClsPrefixRef:d,mergedRtlRef:p,mergedComponentPropsRef:_}=c(n),{mergedSizeRef:v}=D({},{defaultSize:`medium`,mergedSize:e=>{let{size:t}=n;if(t)return t;let{size:r}=l;if(r)return r;let{mergedSize:i}=e||{};return i?i.value:_?.value?.Button?.size||`medium`}}),y=m(()=>n.focusable&&!n.disabled),b=e=>{var t;y.value||e.preventDefault(),!n.nativeFocusBehavior&&(e.preventDefault(),!n.disabled&&y.value&&((t=r.value)==null||t.focus({preventScroll:!0})))},x=e=>{var t;if(!n.disabled&&!n.loading){let{onClick:r}=n;r&&T(r,e),n.text||(t=i.value)==null||t.play()}},S=e=>{if(e.key===`Enter`){if(!n.keyboard)return;a.value=!1}},C=e=>{if(e.key===`Enter`){if(!n.keyboard||n.loading){e.preventDefault();return}a.value=!0}},w=()=>{a.value=!1},E=h(`Button`,`-button`,J,q,n,d),O=j(`Button`,p,d),k=m(()=>{let{common:{cubicBezierEaseInOut:t,cubicBezierEaseOut:r},self:i}=E.value,{rippleDuration:a,opacityDisabled:o,fontWeight:s,fontWeightStrong:c}=i,l=v.value,{dashed:u,type:d,ghost:p,text:m,color:h,round:g,circle:_,textColor:y,secondary:b,tertiary:x,quaternary:S,strong:C}=n,w={"--n-font-weight":C?c:s},T={"--n-color":`initial`,"--n-color-hover":`initial`,"--n-color-pressed":`initial`,"--n-color-focus":`initial`,"--n-color-disabled":`initial`,"--n-ripple-color":`initial`,"--n-text-color":`initial`,"--n-text-color-hover":`initial`,"--n-text-color-pressed":`initial`,"--n-text-color-focus":`initial`,"--n-text-color-disabled":`initial`},D=d===`tertiary`,O=d==="default",k=D?`default`:d;if(m){let e=y||h;T={"--n-color":`#0000`,"--n-color-hover":`#0000`,"--n-color-pressed":`#0000`,"--n-color-focus":`#0000`,"--n-color-disabled":`#0000`,"--n-ripple-color":`#0000`,"--n-text-color":e||i[f(`textColorText`,k)],"--n-text-color-hover":e?H(e):i[f(`textColorTextHover`,k)],"--n-text-color-pressed":e?U(e):i[f(`textColorTextPressed`,k)],"--n-text-color-focus":e?H(e):i[f(`textColorTextHover`,k)],"--n-text-color-disabled":e||i[f(`textColorTextDisabled`,k)]}}else if(p||u){let e=y||h;T={"--n-color":`#0000`,"--n-color-hover":`#0000`,"--n-color-pressed":`#0000`,"--n-color-focus":`#0000`,"--n-color-disabled":`#0000`,"--n-ripple-color":h||i[f(`rippleColor`,k)],"--n-text-color":e||i[f(`textColorGhost`,k)],"--n-text-color-hover":e?H(e):i[f(`textColorGhostHover`,k)],"--n-text-color-pressed":e?U(e):i[f(`textColorGhostPressed`,k)],"--n-text-color-focus":e?H(e):i[f(`textColorGhostHover`,k)],"--n-text-color-disabled":e||i[f(`textColorGhostDisabled`,k)]}}else if(b){let t=O?i.textColor:D?i.textColorTertiary:i[f(`color`,k)],n=h||t,r=d!=="default"&&d!==`tertiary`;T={"--n-color":r?e(n,{alpha:Number(i.colorOpacitySecondary)}):i.colorSecondary,"--n-color-hover":r?e(n,{alpha:Number(i.colorOpacitySecondaryHover)}):i.colorSecondaryHover,"--n-color-pressed":r?e(n,{alpha:Number(i.colorOpacitySecondaryPressed)}):i.colorSecondaryPressed,"--n-color-focus":r?e(n,{alpha:Number(i.colorOpacitySecondaryHover)}):i.colorSecondaryHover,"--n-color-disabled":i.colorSecondary,"--n-ripple-color":`#0000`,"--n-text-color":n,"--n-text-color-hover":n,"--n-text-color-pressed":n,"--n-text-color-focus":n,"--n-text-color-disabled":n}}else if(x||S){let e=O?i.textColor:D?i.textColorTertiary:i[f(`color`,k)],t=h||e;x?(T[`--n-color`]=i.colorTertiary,T[`--n-color-hover`]=i.colorTertiaryHover,T[`--n-color-pressed`]=i.colorTertiaryPressed,T[`--n-color-focus`]=i.colorSecondaryHover,T[`--n-color-disabled`]=i.colorTertiary):(T[`--n-color`]=i.colorQuaternary,T[`--n-color-hover`]=i.colorQuaternaryHover,T[`--n-color-pressed`]=i.colorQuaternaryPressed,T[`--n-color-focus`]=i.colorQuaternaryHover,T[`--n-color-disabled`]=i.colorQuaternary),T[`--n-ripple-color`]=`#0000`,T[`--n-text-color`]=t,T[`--n-text-color-hover`]=t,T[`--n-text-color-pressed`]=t,T[`--n-text-color-focus`]=t,T[`--n-text-color-disabled`]=t}else T={"--n-color":h||i[f(`color`,k)],"--n-color-hover":h?H(h):i[f(`colorHover`,k)],"--n-color-pressed":h?U(h):i[f(`colorPressed`,k)],"--n-color-focus":h?H(h):i[f(`colorFocus`,k)],"--n-color-disabled":h||i[f(`colorDisabled`,k)],"--n-ripple-color":h||i[f(`rippleColor`,k)],"--n-text-color":y||(h?i.textColorPrimary:D?i.textColorTertiary:i[f(`textColor`,k)]),"--n-text-color-hover":y||(h?i.textColorHoverPrimary:i[f(`textColorHover`,k)]),"--n-text-color-pressed":y||(h?i.textColorPressedPrimary:i[f(`textColorPressed`,k)]),"--n-text-color-focus":y||(h?i.textColorFocusPrimary:i[f(`textColorFocus`,k)]),"--n-text-color-disabled":y||(h?i.textColorDisabledPrimary:i[f(`textColorDisabled`,k)])};let A={"--n-border":`initial`,"--n-border-hover":`initial`,"--n-border-pressed":`initial`,"--n-border-focus":`initial`,"--n-border-disabled":`initial`};A=m?{"--n-border":`none`,"--n-border-hover":`none`,"--n-border-pressed":`none`,"--n-border-focus":`none`,"--n-border-disabled":`none`}:{"--n-border":i[f(`border`,k)],"--n-border-hover":i[f(`borderHover`,k)],"--n-border-pressed":i[f(`borderPressed`,k)],"--n-border-focus":i[f(`borderFocus`,k)],"--n-border-disabled":i[f(`borderDisabled`,k)]};let{[f(`height`,l)]:j,[f(`fontSize`,l)]:M,[f(`padding`,l)]:N,[f(`paddingRound`,l)]:P,[f(`iconSize`,l)]:F,[f(`borderRadius`,l)]:I,[f(`iconMargin`,l)]:L,waveOpacity:R}=i,z={"--n-width":_&&!m?j:`initial`,"--n-height":m?`initial`:j,"--n-font-size":M,"--n-padding":_||m?`initial`:g?P:N,"--n-icon-size":F,"--n-icon-margin":L,"--n-border-radius":m?`initial`:_||g?j:I};return Object.assign(Object.assign(Object.assign(Object.assign({"--n-bezier":t,"--n-bezier-ease-out":r,"--n-ripple-duration":a,"--n-opacity-disabled":o,"--n-wave-opacity":R},w),T),A),z)}),A=u?s(`button`,m(()=>{let e=``,{dashed:t,type:r,ghost:i,text:a,color:o,round:s,circle:c,textColor:l,secondary:u,tertiary:d,quaternary:f,strong:p}=n;t&&(e+=`a`),i&&(e+=`b`),a&&(e+=`c`),s&&(e+=`d`),c&&(e+=`e`),u&&(e+=`f`),d&&(e+=`g`),f&&(e+=`h`),p&&(e+=`i`),o&&(e+=`j${F(o)}`),l&&(e+=`k${F(l)}`);let{value:m}=v;return e+=`l${m[0]}`,e+=`m${r[0]}`,e}),k,n):void 0;return{selfElRef:r,waveElRef:i,mergedClsPrefix:d,mergedFocusable:y,mergedSize:v,showBorder:o,enterPressed:a,rtlEnabled:O,handleMousedown:b,handleKeydown:C,handleBlur:w,handleKeyup:S,handleClick:x,customColorCssVars:m(()=>{let{color:e}=n;if(!e)return null;let t=H(e);return{"--n-border-color":e,"--n-border-color-hover":t,"--n-border-color-pressed":U(e),"--n-border-color-focus":t,"--n-border-color-disabled":e}}),cssVars:u?void 0:k,themeClass:A?.themeClass,onRender:A?.onRender}},render(){let{mergedClsPrefix:e,tag:t,onRender:n}=this;n?.();let r=O(this.$slots.default,t=>t&&b(`span`,{class:`${e}-button__content`},t));return b(t,{ref:`selfElRef`,class:[this.themeClass,`${e}-button`,`${e}-button--${this.type}-type`,`${e}-button--${this.mergedSize}-type`,this.rtlEnabled&&`${e}-button--rtl`,this.disabled&&`${e}-button--disabled`,this.block&&`${e}-button--block`,this.enterPressed&&`${e}-button--pressed`,!this.text&&this.dashed&&`${e}-button--dashed`,this.color&&`${e}-button--color`,this.secondary&&`${e}-button--secondary`,this.loading&&`${e}-button--loading`,this.ghost&&`${e}-button--ghost`],tabindex:this.mergedFocusable?0:-1,type:this.attrType,style:this.cssVars,disabled:this.disabled,onClick:this.handleClick,onBlur:this.handleBlur,onMousedown:this.handleMousedown,onKeyup:this.handleKeyup,onKeydown:this.handleKeydown},this.iconPlacement===`right`&&r,b(A,{width:!0},{default:()=>O(this.$slots.icon,t=>(this.loading||this.renderIcon||t)&&b(`span`,{class:`${e}-button__icon`,style:{margin:w(this.$slots.default)?`0`:``}},b(C,null,{default:()=>this.loading?b(M,Object.assign({clsPrefix:e,key:`loading`,class:`${e}-icon-slot`,strokeWidth:20},this.spinProps)):b(`div`,{key:`icon`,class:`${e}-icon-slot`,role:`none`},this.renderIcon?this.renderIcon():t)})))}),this.iconPlacement===`left`&&r,this.text?null:b(z,{ref:`waveElRef`,clsPrefix:e}),this.showBorder?b(`div`,{"aria-hidden":!0,class:`${e}-button__border`,style:this.customColorCssVars}):null,this.showBorder?b(`div`,{"aria-hidden":!0,class:`${e}-button__state-border`,style:this.customColorCssVars}):null)}}),Z=X;export{V as a,F as c,K as i,P as l,Z as n,z as o,q as r,L as s,X as t,N as u};