import{B as e,G as t,J as n,K as r,N as i,P as a,Rt as o,X as s,Y as c,Z as l,cn as u,ft as d,i as f,in as p,n as m,wt as h,xt as g,z as _}from"./_plugin-vue_export-helper-CHFfEUVo.js";import{h as v,m as y,s as b}from"./Loading-DTHYZrnQ.js";import{l as x,t as S}from"./Close-DCuJsqL1.js";import{c as C}from"./Button-BEyPvId2.js";import{H as w}from"./index-BFrYTqRE.js";function T(t){let{textColor2:n,primaryColorHover:r,primaryColorPressed:i,primaryColor:a,infoColor:o,successColor:s,warningColor:c,errorColor:l,baseColor:u,borderColor:d,opacityDisabled:f,tagColor:p,closeIconColor:m,closeIconColorHover:h,closeIconColorPressed:g,borderRadiusSmall:_,fontSizeMini:v,fontSizeTiny:y,fontSizeSmall:b,fontSizeMedium:x,heightMini:S,heightTiny:C,heightSmall:T,heightMedium:E,closeColorHover:D,closeColorPressed:O,buttonColor2Hover:k,buttonColor2Pressed:A,fontWeightStrong:j}=t;return Object.assign(Object.assign({},w),{closeBorderRadius:_,heightTiny:S,heightSmall:C,heightMedium:T,heightLarge:E,borderRadius:_,opacityDisabled:f,fontSizeTiny:v,fontSizeSmall:y,fontSizeMedium:b,fontSizeLarge:x,fontWeightStrong:j,textColorCheckable:n,textColorHoverCheckable:n,textColorPressedCheckable:n,textColorChecked:u,colorCheckable:`#0000`,colorHoverCheckable:k,colorPressedCheckable:A,colorChecked:a,colorCheckedHover:r,colorCheckedPressed:i,border:`1px solid ${d}`,textColor:n,color:p,colorBordered:`rgb(250, 250, 252)`,closeIconColor:m,closeIconColorHover:h,closeIconColorPressed:g,closeColorHover:D,closeColorPressed:O,borderPrimary:`1px solid ${e(a,{alpha:.3})}`,textColorPrimary:a,colorPrimary:e(a,{alpha:.12}),colorBorderedPrimary:e(a,{alpha:.1}),closeIconColorPrimary:a,closeIconColorHoverPrimary:a,closeIconColorPressedPrimary:a,closeColorHoverPrimary:e(a,{alpha:.12}),closeColorPressedPrimary:e(a,{alpha:.18}),borderInfo:`1px solid ${e(o,{alpha:.3})}`,textColorInfo:o,colorInfo:e(o,{alpha:.12}),colorBorderedInfo:e(o,{alpha:.1}),closeIconColorInfo:o,closeIconColorHoverInfo:o,closeIconColorPressedInfo:o,closeColorHoverInfo:e(o,{alpha:.12}),closeColorPressedInfo:e(o,{alpha:.18}),borderSuccess:`1px solid ${e(s,{alpha:.3})}`,textColorSuccess:s,colorSuccess:e(s,{alpha:.12}),colorBorderedSuccess:e(s,{alpha:.1}),closeIconColorSuccess:s,closeIconColorHoverSuccess:s,closeIconColorPressedSuccess:s,closeColorHoverSuccess:e(s,{alpha:.12}),closeColorPressedSuccess:e(s,{alpha:.18}),borderWarning:`1px solid ${e(c,{alpha:.35})}`,textColorWarning:c,colorWarning:e(c,{alpha:.15}),colorBorderedWarning:e(c,{alpha:.12}),closeIconColorWarning:c,closeIconColorHoverWarning:c,closeIconColorPressedWarning:c,closeColorHoverWarning:e(c,{alpha:.12}),closeColorPressedWarning:e(c,{alpha:.18}),borderError:`1px solid ${e(l,{alpha:.23})}`,textColorError:l,colorError:e(l,{alpha:.1}),colorBorderedError:e(l,{alpha:.08}),closeIconColorError:l,closeIconColorHoverError:l,closeIconColorPressedError:l,closeColorHoverError:e(l,{alpha:.12}),closeColorPressedError:e(l,{alpha:.18})})}var E={name:`Tag`,common:m,self:T},D={color:Object,type:{type:String,default:`default`},round:Boolean,size:String,closable:Boolean,disabled:{type:Boolean,default:void 0}},O=r(`tag`,`
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
`,[c(`strong`,`
 font-weight: var(--n-font-weight-strong);
 `),n(`border`,`
 pointer-events: none;
 position: absolute;
 left: 0;
 right: 0;
 top: 0;
 bottom: 0;
 border-radius: inherit;
 border: var(--n-border);
 transition: border-color .3s var(--n-bezier);
 `),n(`icon`,`
 display: flex;
 margin: 0 4px 0 0;
 color: var(--n-text-color);
 transition: color .3s var(--n-bezier);
 font-size: var(--n-avatar-size-override);
 `),n(`avatar`,`
 display: flex;
 margin: 0 6px 0 0;
 `),n(`close`,`
 margin: var(--n-close-margin);
 transition:
 background-color .3s var(--n-bezier),
 color .3s var(--n-bezier);
 `),c(`round`,`
 padding: 0 calc(var(--n-height) / 3);
 border-radius: calc(var(--n-height) / 2);
 `,[n(`icon`,`
 margin: 0 4px 0 calc((var(--n-height) - 8px) / -2);
 `),n(`avatar`,`
 margin: 0 6px 0 calc((var(--n-height) - 8px) / -2);
 `),c(`closable`,`
 padding: 0 calc(var(--n-height) / 4) 0 calc(var(--n-height) / 3);
 `)]),c(`icon, avatar`,[c(`round`,`
 padding: 0 calc(var(--n-height) / 3) 0 calc(var(--n-height) / 2);
 `)]),c(`disabled`,`
 cursor: not-allowed !important;
 opacity: var(--n-opacity-disabled);
 `),c(`checkable`,`
 cursor: pointer;
 box-shadow: none;
 color: var(--n-text-color-checkable);
 background-color: var(--n-color-checkable);
 `,[s(`disabled`,[t(`&:hover`,`background-color: var(--n-color-hover-checkable);`,[s(`checked`,`color: var(--n-text-color-hover-checkable);`)]),t(`&:active`,`background-color: var(--n-color-pressed-checkable);`,[s(`checked`,`color: var(--n-text-color-pressed-checkable);`)])]),c(`checked`,`
 color: var(--n-text-color-checked);
 background-color: var(--n-color-checked);
 `,[s(`disabled`,[t(`&:hover`,`background-color: var(--n-color-checked-hover);`),t(`&:active`,`background-color: var(--n-color-checked-pressed);`)])])])]),k=Object.assign(Object.assign(Object.assign({},f.props),D),{bordered:{type:Boolean,default:void 0},checked:Boolean,checkable:Boolean,strong:Boolean,triggerClickOnClose:Boolean,onClose:[Array,Function],onMouseenter:Function,onMouseleave:Function,"onUpdate:checked":Function,onUpdateChecked:Function,internalCloseFocusable:{type:Boolean,default:!0},internalCloseIsButtonTag:{type:Boolean,default:!0},onCheckedChange:Function}),A=_(`n-tag`),j=g({name:`Tag`,props:k,slots:Object,setup(e){let t=p(null),{mergedBorderedRef:n,mergedClsPrefixRef:r,inlineThemeDisabled:s,mergedRtlRef:c,mergedComponentPropsRef:m}=a(e),h=d(()=>e.size||m?.value?.Tag?.size||`medium`),g=f(`Tag`,`-tag`,O,E,e,r);o(A,{roundRef:u(e,`round`)});function _(){if(!e.disabled&&e.checkable){let{checked:t,onCheckedChange:n,onUpdateChecked:r,"onUpdate:checked":i}=e;r&&r(!t),i&&i(!t),n&&n(!t)}}function y(t){if(e.triggerClickOnClose||t.stopPropagation(),!e.disabled){let{onClose:n}=e;n&&v(n,t)}}let S={setTextContent(e){let{value:n}=t;n&&(n.textContent=e)}},w=b(`Tag`,c,r),T=d(()=>{let{type:t,color:{color:r,textColor:i}={}}=e,a=h.value,{common:{cubicBezierEaseInOut:o},self:{padding:s,closeMargin:c,borderRadius:u,opacityDisabled:d,textColorCheckable:f,textColorHoverCheckable:p,textColorPressedCheckable:m,textColorChecked:_,colorCheckable:v,colorHoverCheckable:y,colorPressedCheckable:b,colorChecked:S,colorCheckedHover:C,colorCheckedPressed:w,closeBorderRadius:T,fontWeightStrong:E,[l(`colorBordered`,t)]:D,[l(`closeSize`,a)]:O,[l(`closeIconSize`,a)]:k,[l(`fontSize`,a)]:A,[l(`height`,a)]:j,[l(`color`,t)]:M,[l(`textColor`,t)]:N,[l(`border`,t)]:P,[l(`closeIconColor`,t)]:F,[l(`closeIconColorHover`,t)]:I,[l(`closeIconColorPressed`,t)]:L,[l(`closeColorHover`,t)]:R,[l(`closeColorPressed`,t)]:z}}=g.value,B=x(c);return{"--n-font-weight-strong":E,"--n-avatar-size-override":`calc(${j} - 8px)`,"--n-bezier":o,"--n-border-radius":u,"--n-border":P,"--n-close-icon-size":k,"--n-close-color-pressed":z,"--n-close-color-hover":R,"--n-close-border-radius":T,"--n-close-icon-color":F,"--n-close-icon-color-hover":I,"--n-close-icon-color-pressed":L,"--n-close-icon-color-disabled":F,"--n-close-margin-top":B.top,"--n-close-margin-right":B.right,"--n-close-margin-bottom":B.bottom,"--n-close-margin-left":B.left,"--n-close-size":O,"--n-color":r||(n.value?D:M),"--n-color-checkable":v,"--n-color-checked":S,"--n-color-checked-hover":C,"--n-color-checked-pressed":w,"--n-color-hover-checkable":y,"--n-color-pressed-checkable":b,"--n-font-size":A,"--n-height":j,"--n-opacity-disabled":d,"--n-padding":s,"--n-text-color":i||N,"--n-text-color-checkable":f,"--n-text-color-checked":_,"--n-text-color-hover-checkable":p,"--n-text-color-pressed-checkable":m}}),D=s?i(`tag`,d(()=>{let t=``,{type:r,color:{color:i,textColor:a}={}}=e;return t+=r[0],t+=h.value[0],i&&(t+=`a${C(i)}`),a&&(t+=`b${C(a)}`),n.value&&(t+=`c`),t}),T,e):void 0;return Object.assign(Object.assign({},S),{rtlEnabled:w,mergedClsPrefix:r,contentRef:t,mergedBordered:n,handleClick:_,handleCloseClick:y,cssVars:s?void 0:T,themeClass:D?.themeClass,onRender:D?.onRender})},render(){var e;let{mergedClsPrefix:t,rtlEnabled:n,closable:r,color:{borderColor:i}={},round:a,onRender:o,$slots:s}=this;o?.();let c=y(s.avatar,e=>e&&h(`div`,{class:`${t}-tag__avatar`},e)),l=y(s.icon,e=>e&&h(`div`,{class:`${t}-tag__icon`},e));return h(`div`,{class:[`${t}-tag`,this.themeClass,{[`${t}-tag--rtl`]:n,[`${t}-tag--strong`]:this.strong,[`${t}-tag--disabled`]:this.disabled,[`${t}-tag--checkable`]:this.checkable,[`${t}-tag--checked`]:this.checkable&&this.checked,[`${t}-tag--round`]:a,[`${t}-tag--avatar`]:c,[`${t}-tag--icon`]:l,[`${t}-tag--closable`]:r}],style:this.cssVars,onClick:this.handleClick,onMouseenter:this.onMouseenter,onMouseleave:this.onMouseleave},l||c,h(`span`,{class:`${t}-tag__content`,ref:`contentRef`},(e=this.$slots).default?.call(e)),!this.checkable&&r?h(S,{clsPrefix:t,class:`${t}-tag__close`,disabled:this.disabled,onClick:this.handleCloseClick,focusable:this.internalCloseFocusable,round:a,isButtonTag:this.internalCloseIsButtonTag,absolute:!0}):null,!this.checkable&&this.mergedBordered?h(`div`,{class:`${t}-tag__border`,style:{borderColor:i}}):null)}});export{j as t};