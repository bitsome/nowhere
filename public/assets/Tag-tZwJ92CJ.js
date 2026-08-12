import{$ as e,B as t,Ct as n,J as r,K as i,Kt as a,Nt as o,V as s,at as c,cn as l,d as u,et as d,fn as f,i as p,it as m,jt as h,nt as g,rt as _}from"./_plugin-vue_export-helper-BRaiA-U2.js";import{d as v,f as y,o as b,p as x}from"./light-C6m6bYe2.js";import{i as S}from"./fade-in.cssr-DOjf-8Ff.js";import{I as C,O as w}from"./index-RY1IsKbG.js";function T(e){let{textColor2:t,primaryColorHover:n,primaryColorPressed:i,primaryColor:a,infoColor:o,successColor:s,warningColor:c,errorColor:l,baseColor:u,borderColor:d,opacityDisabled:f,tagColor:p,closeIconColor:m,closeIconColorHover:h,closeIconColorPressed:g,borderRadiusSmall:_,fontSizeMini:v,fontSizeTiny:y,fontSizeSmall:b,fontSizeMedium:x,heightMini:S,heightTiny:C,heightSmall:T,heightMedium:E,closeColorHover:D,closeColorPressed:O,buttonColor2Hover:k,buttonColor2Pressed:A,fontWeightStrong:j}=e;return Object.assign(Object.assign({},w),{closeBorderRadius:_,heightTiny:S,heightSmall:C,heightMedium:T,heightLarge:E,borderRadius:_,opacityDisabled:f,fontSizeTiny:v,fontSizeSmall:y,fontSizeMedium:b,fontSizeLarge:x,fontWeightStrong:j,textColorCheckable:t,textColorHoverCheckable:t,textColorPressedCheckable:t,textColorChecked:u,colorCheckable:`#0000`,colorHoverCheckable:k,colorPressedCheckable:A,colorChecked:a,colorCheckedHover:n,colorCheckedPressed:i,border:`1px solid ${d}`,textColor:t,color:p,colorBordered:`rgb(250, 250, 252)`,closeIconColor:m,closeIconColorHover:h,closeIconColorPressed:g,closeColorHover:D,closeColorPressed:O,borderPrimary:`1px solid ${r(a,{alpha:.3})}`,textColorPrimary:a,colorPrimary:r(a,{alpha:.12}),colorBorderedPrimary:r(a,{alpha:.1}),closeIconColorPrimary:a,closeIconColorHoverPrimary:a,closeIconColorPressedPrimary:a,closeColorHoverPrimary:r(a,{alpha:.12}),closeColorPressedPrimary:r(a,{alpha:.18}),borderInfo:`1px solid ${r(o,{alpha:.3})}`,textColorInfo:o,colorInfo:r(o,{alpha:.12}),colorBorderedInfo:r(o,{alpha:.1}),closeIconColorInfo:o,closeIconColorHoverInfo:o,closeIconColorPressedInfo:o,closeColorHoverInfo:r(o,{alpha:.12}),closeColorPressedInfo:r(o,{alpha:.18}),borderSuccess:`1px solid ${r(s,{alpha:.3})}`,textColorSuccess:s,colorSuccess:r(s,{alpha:.12}),colorBorderedSuccess:r(s,{alpha:.1}),closeIconColorSuccess:s,closeIconColorHoverSuccess:s,closeIconColorPressedSuccess:s,closeColorHoverSuccess:r(s,{alpha:.12}),closeColorPressedSuccess:r(s,{alpha:.18}),borderWarning:`1px solid ${r(c,{alpha:.35})}`,textColorWarning:c,colorWarning:r(c,{alpha:.15}),colorBorderedWarning:r(c,{alpha:.12}),closeIconColorWarning:c,closeIconColorHoverWarning:c,closeIconColorPressedWarning:c,closeColorHoverWarning:r(c,{alpha:.12}),closeColorPressedWarning:r(c,{alpha:.18}),borderError:`1px solid ${r(l,{alpha:.23})}`,textColorError:l,colorError:r(l,{alpha:.1}),colorBorderedError:r(l,{alpha:.08}),closeIconColorError:l,closeIconColorHoverError:l,closeIconColorPressedError:l,closeColorHoverError:r(l,{alpha:.12}),closeColorPressedError:r(l,{alpha:.18})})}var E={name:`Tag`,common:p,self:T},D={color:Object,type:{type:String,default:`default`},round:Boolean,size:String,closable:Boolean,disabled:{type:Boolean,default:void 0}},O=d(`tag`,`
 --n-close-margin: var(--n-close-margin-top) var(--n-close-margin-right) var(--n-close-margin-bottom) var(--n-close-margin-left);
 white-space: nowrap;
 position: relative;
 box-sizing: border-box;
 cursor: default;
 display: inline-flex;
 align-items: center;
 flex-wrap: nowrap;
 padding: var(--n-padding);
 border-radius: var(--n-border-radius);
 color: var(--n-text-color);
 background-color: var(--n-color);
 transition: 
 border-color .3s var(--n-bezier),
 background-color .3s var(--n-bezier),
 color .3s var(--n-bezier),
 box-shadow .3s var(--n-bezier),
 opacity .3s var(--n-bezier);
 line-height: 1;
 height: var(--n-height);
 font-size: var(--n-font-size);
`,[_(`strong`,`
 font-weight: var(--n-font-weight-strong);
 `),g(`border`,`
 pointer-events: none;
 position: absolute;
 left: 0;
 right: 0;
 top: 0;
 bottom: 0;
 border-radius: inherit;
 border: var(--n-border);
 transition: border-color .3s var(--n-bezier);
 `),g(`icon`,`
 display: flex;
 margin: 0 4px 0 0;
 color: var(--n-text-color);
 transition: color .3s var(--n-bezier);
 font-size: var(--n-avatar-size-override);
 `),g(`avatar`,`
 display: flex;
 margin: 0 6px 0 0;
 `),g(`close`,`
 margin: var(--n-close-margin);
 transition:
 background-color .3s var(--n-bezier),
 color .3s var(--n-bezier);
 `),_(`round`,`
 padding: 0 calc(var(--n-height) / 3);
 border-radius: calc(var(--n-height) / 2);
 `,[g(`icon`,`
 margin: 0 4px 0 calc((var(--n-height) - 8px) / -2);
 `),g(`avatar`,`
 margin: 0 6px 0 calc((var(--n-height) - 8px) / -2);
 `),_(`closable`,`
 padding: 0 calc(var(--n-height) / 4) 0 calc(var(--n-height) / 3);
 `)]),_(`icon, avatar`,[_(`round`,`
 padding: 0 calc(var(--n-height) / 3) 0 calc(var(--n-height) / 2);
 `)]),_(`disabled`,`
 cursor: not-allowed !important;
 opacity: var(--n-opacity-disabled);
 `),_(`checkable`,`
 cursor: pointer;
 box-shadow: none;
 color: var(--n-text-color-checkable);
 background-color: var(--n-color-checkable);
 `,[m(`disabled`,[e(`&:hover`,`background-color: var(--n-color-hover-checkable);`,[m(`checked`,`color: var(--n-text-color-hover-checkable);`)]),e(`&:active`,`background-color: var(--n-color-pressed-checkable);`,[m(`checked`,`color: var(--n-text-color-pressed-checkable);`)])]),_(`checked`,`
 color: var(--n-text-color-checked);
 background-color: var(--n-color-checked);
 `,[m(`disabled`,[e(`&:hover`,`background-color: var(--n-color-checked-hover);`),e(`&:active`,`background-color: var(--n-color-checked-pressed);`)])])])]),k=Object.assign(Object.assign(Object.assign({},u.props),D),{bordered:{type:Boolean,default:void 0},checked:Boolean,checkable:Boolean,strong:Boolean,triggerClickOnClose:Boolean,onClose:[Array,Function],onMouseenter:Function,onMouseleave:Function,"onUpdate:checked":Function,onUpdateChecked:Function,internalCloseFocusable:{type:Boolean,default:!0},internalCloseIsButtonTag:{type:Boolean,default:!0},onCheckedChange:Function}),A=i(`n-tag`),j=h({name:`Tag`,props:k,slots:Object,setup(e){let r=l(null),{mergedBorderedRef:i,mergedClsPrefixRef:o,inlineThemeDisabled:d,mergedRtlRef:p,mergedComponentPropsRef:m}=s(e),h=n(()=>e.size||m?.value?.Tag?.size||`medium`),g=u(`Tag`,`-tag`,O,E,e,o);a(A,{roundRef:f(e,`round`)});function _(){if(!e.disabled&&e.checkable){let{checked:t,onCheckedChange:n,onUpdateChecked:r,"onUpdate:checked":i}=e;r&&r(!t),i&&i(!t),n&&n(!t)}}function v(t){if(e.triggerClickOnClose||t.stopPropagation(),!e.disabled){let{onClose:n}=e;n&&y(n,t)}}let C={setTextContent(e){let{value:t}=r;t&&(t.textContent=e)}},w=b(`Tag`,p,o),T=n(()=>{let{type:t,color:{color:n,textColor:r}={}}=e,a=h.value,{common:{cubicBezierEaseInOut:o},self:{padding:s,closeMargin:l,borderRadius:u,opacityDisabled:d,textColorCheckable:f,textColorHoverCheckable:p,textColorPressedCheckable:m,textColorChecked:_,colorCheckable:v,colorHoverCheckable:y,colorPressedCheckable:b,colorChecked:x,colorCheckedHover:C,colorCheckedPressed:w,closeBorderRadius:T,fontWeightStrong:E,[c(`colorBordered`,t)]:D,[c(`closeSize`,a)]:O,[c(`closeIconSize`,a)]:k,[c(`fontSize`,a)]:A,[c(`height`,a)]:j,[c(`color`,t)]:M,[c(`textColor`,t)]:N,[c(`border`,t)]:P,[c(`closeIconColor`,t)]:F,[c(`closeIconColorHover`,t)]:I,[c(`closeIconColorPressed`,t)]:L,[c(`closeColorHover`,t)]:R,[c(`closeColorPressed`,t)]:z}}=g.value,B=S(l);return{"--n-font-weight-strong":E,"--n-avatar-size-override":`calc(${j} - 8px)`,"--n-bezier":o,"--n-border-radius":u,"--n-border":P,"--n-close-icon-size":k,"--n-close-color-pressed":z,"--n-close-color-hover":R,"--n-close-border-radius":T,"--n-close-icon-color":F,"--n-close-icon-color-hover":I,"--n-close-icon-color-pressed":L,"--n-close-icon-color-disabled":F,"--n-close-margin-top":B.top,"--n-close-margin-right":B.right,"--n-close-margin-bottom":B.bottom,"--n-close-margin-left":B.left,"--n-close-size":O,"--n-color":n||(i.value?D:M),"--n-color-checkable":v,"--n-color-checked":x,"--n-color-checked-hover":C,"--n-color-checked-pressed":w,"--n-color-hover-checkable":y,"--n-color-pressed-checkable":b,"--n-font-size":A,"--n-height":j,"--n-opacity-disabled":d,"--n-padding":s,"--n-text-color":r||N,"--n-text-color-checkable":f,"--n-text-color-checked":_,"--n-text-color-hover-checkable":p,"--n-text-color-pressed-checkable":m}}),D=d?t(`tag`,n(()=>{let t=``,{type:n,color:{color:r,textColor:a}={}}=e;return t+=n[0],t+=h.value[0],r&&(t+=`a${x(r)}`),a&&(t+=`b${x(a)}`),i.value&&(t+=`c`),t}),T,e):void 0;return Object.assign(Object.assign({},C),{rtlEnabled:w,mergedClsPrefix:o,contentRef:r,mergedBordered:i,handleClick:_,handleCloseClick:v,cssVars:d?void 0:T,themeClass:D?.themeClass,onRender:D?.onRender})},render(){var e;let{mergedClsPrefix:t,rtlEnabled:n,closable:r,color:{borderColor:i}={},round:a,onRender:s,$slots:c}=this;s?.();let l=v(c.avatar,e=>e&&o(`div`,{class:`${t}-tag__avatar`},e)),u=v(c.icon,e=>e&&o(`div`,{class:`${t}-tag__icon`},e));return o(`div`,{class:[`${t}-tag`,this.themeClass,{[`${t}-tag--rtl`]:n,[`${t}-tag--strong`]:this.strong,[`${t}-tag--disabled`]:this.disabled,[`${t}-tag--checkable`]:this.checkable,[`${t}-tag--checked`]:this.checkable&&this.checked,[`${t}-tag--round`]:a,[`${t}-tag--avatar`]:l,[`${t}-tag--icon`]:u,[`${t}-tag--closable`]:r}],style:this.cssVars,onClick:this.handleClick,onMouseenter:this.onMouseenter,onMouseleave:this.onMouseleave},u||l,o(`span`,{class:`${t}-tag__content`,ref:`contentRef`},(e=this.$slots).default?.call(e)),!this.checkable&&r?o(C,{clsPrefix:t,class:`${t}-tag__close`,disabled:this.disabled,onClick:this.handleCloseClick,focusable:this.internalCloseFocusable,round:a,isButtonTag:this.internalCloseIsButtonTag,absolute:!0}):null,!this.checkable&&this.mergedBordered?o(`div`,{class:`${t}-tag__border`,style:{borderColor:i}}):null)}});export{j as t};