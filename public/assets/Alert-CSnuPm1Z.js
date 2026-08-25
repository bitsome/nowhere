import{B as e,G as t,J as n,K as r,N as i,Ot as a,P as o,V as s,Y as c,Z as l,ft as u,i as d,in as f,n as p,wt as m,xt as h}from"./_plugin-vue_export-helper-CHFfEUVo.js";import{f as g,m as _,r as v,s as y}from"./Loading-DTHYZrnQ.js";import{l as b,r as x,t as S}from"./Close-DCuJsqL1.js";import{B as C,G as w,K as T,W as E,q as D,z as O}from"./index-BFrYTqRE.js";function k(t){let{lineHeight:n,borderRadius:r,fontWeightStrong:i,baseColor:a,dividerColor:o,actionColor:c,textColor1:l,textColor2:u,closeColorHover:d,closeColorPressed:f,closeIconColor:p,closeIconColorHover:m,closeIconColorPressed:h,infoColor:g,successColor:_,warningColor:v,errorColor:y,fontSize:b}=t;return Object.assign(Object.assign({},C),{fontSize:b,lineHeight:n,titleFontWeight:i,borderRadius:r,border:`1px solid ${o}`,color:c,titleTextColor:l,iconColor:u,contentTextColor:u,closeBorderRadius:r,closeColorHover:d,closeColorPressed:f,closeIconColor:p,closeIconColorHover:m,closeIconColorPressed:h,borderInfo:`1px solid ${s(a,e(g,{alpha:.25}))}`,colorInfo:s(a,e(g,{alpha:.08})),titleTextColorInfo:l,iconColorInfo:g,contentTextColorInfo:u,closeColorHoverInfo:d,closeColorPressedInfo:f,closeIconColorInfo:p,closeIconColorHoverInfo:m,closeIconColorPressedInfo:h,borderSuccess:`1px solid ${s(a,e(_,{alpha:.25}))}`,colorSuccess:s(a,e(_,{alpha:.08})),titleTextColorSuccess:l,iconColorSuccess:_,contentTextColorSuccess:u,closeColorHoverSuccess:d,closeColorPressedSuccess:f,closeIconColorSuccess:p,closeIconColorHoverSuccess:m,closeIconColorPressedSuccess:h,borderWarning:`1px solid ${s(a,e(v,{alpha:.33}))}`,colorWarning:s(a,e(v,{alpha:.08})),titleTextColorWarning:l,iconColorWarning:v,contentTextColorWarning:u,closeColorHoverWarning:d,closeColorPressedWarning:f,closeIconColorWarning:p,closeIconColorHoverWarning:m,closeIconColorPressedWarning:h,borderError:`1px solid ${s(a,e(y,{alpha:.25}))}`,colorError:s(a,e(y,{alpha:.08})),titleTextColorError:l,iconColorError:y,contentTextColorError:u,closeColorHoverError:d,closeColorPressedError:f,closeIconColorError:p,closeIconColorHoverError:m,closeIconColorPressedError:h})}var A={name:`Alert`,common:p,self:k},j=r(`alert`,`
 line-height: var(--n-line-height);
 border-radius: var(--n-border-radius);
 position: relative;
 transition: background-color .3s var(--n-bezier);
 background-color: var(--n-color);
 text-align: start;
 word-break: break-word;
`,[n(`border`,`
 border-radius: inherit;
 position: absolute;
 left: 0;
 right: 0;
 top: 0;
 bottom: 0;
 transition: border-color .3s var(--n-bezier);
 border: var(--n-border);
 pointer-events: none;
 `),c(`closable`,[r(`alert-body`,[n(`title`,`
 padding-right: 24px;
 `)])]),n(`icon`,{color:`var(--n-icon-color)`}),r(`alert-body`,{padding:`var(--n-padding)`},[n(`title`,{color:`var(--n-title-text-color)`}),n(`content`,{color:`var(--n-content-text-color)`})]),O({originalTransition:`transform .3s var(--n-bezier)`,enterToProps:{transform:`scale(1)`},leaveToProps:{transform:`scale(0.9)`}}),n(`icon`,`
 position: absolute;
 left: 0;
 top: 0;
 align-items: center;
 justify-content: center;
 display: flex;
 width: var(--n-icon-size);
 height: var(--n-icon-size);
 font-size: var(--n-icon-size);
 margin: var(--n-icon-margin);
 `),n(`close`,`
 transition:
 color .3s var(--n-bezier),
 background-color .3s var(--n-bezier);
 position: absolute;
 right: 0;
 top: 0;
 margin: var(--n-close-margin);
 `),c(`show-icon`,[r(`alert-body`,{paddingLeft:`calc(var(--n-icon-margin-left) + var(--n-icon-size) + var(--n-icon-margin-right))`})]),c(`right-adjust`,[r(`alert-body`,{paddingRight:`calc(var(--n-close-size) + var(--n-padding) + 2px)`})]),r(`alert-body`,`
 border-radius: var(--n-border-radius);
 transition: border-color .3s var(--n-bezier);
 `,[n(`title`,`
 transition: color .3s var(--n-bezier);
 font-size: 16px;
 line-height: 19px;
 font-weight: var(--n-title-font-weight);
 `,[t(`& +`,[n(`content`,{marginTop:`9px`})])]),n(`content`,{transition:`color .3s var(--n-bezier)`,fontSize:`var(--n-font-size)`})]),n(`icon`,{transition:`color .3s var(--n-bezier)`})]),M=Object.assign(Object.assign({},d.props),{title:String,showIcon:{type:Boolean,default:!0},type:{type:String,default:`default`},bordered:{type:Boolean,default:!0},closable:Boolean,onClose:Function,onAfterLeave:Function,onAfterHide:Function}),N=h({name:`Alert`,inheritAttrs:!1,props:M,slots:Object,setup(e){let{mergedClsPrefixRef:t,mergedBorderedRef:n,inlineThemeDisabled:r,mergedRtlRef:a}=o(e),s=d(`Alert`,`-alert`,j,A,e,t),c=y(`Alert`,a,t),p=u(()=>{let{common:{cubicBezierEaseInOut:t},self:n}=s.value,{fontSize:r,borderRadius:i,titleFontWeight:a,lineHeight:o,iconSize:c,iconMargin:u,iconMarginRtl:d,closeIconSize:f,closeBorderRadius:p,closeSize:m,closeMargin:h,closeMarginRtl:g,padding:_}=n,{type:v}=e,{left:y,right:x}=b(u);return{"--n-bezier":t,"--n-color":n[l(`color`,v)],"--n-close-icon-size":f,"--n-close-border-radius":p,"--n-close-color-hover":n[l(`closeColorHover`,v)],"--n-close-color-pressed":n[l(`closeColorPressed`,v)],"--n-close-icon-color":n[l(`closeIconColor`,v)],"--n-close-icon-color-hover":n[l(`closeIconColorHover`,v)],"--n-close-icon-color-pressed":n[l(`closeIconColorPressed`,v)],"--n-icon-color":n[l(`iconColor`,v)],"--n-border":n[l(`border`,v)],"--n-title-text-color":n[l(`titleTextColor`,v)],"--n-content-text-color":n[l(`contentTextColor`,v)],"--n-line-height":o,"--n-border-radius":i,"--n-font-size":r,"--n-title-font-weight":a,"--n-icon-size":c,"--n-icon-margin":u,"--n-icon-margin-rtl":d,"--n-close-size":m,"--n-close-margin":h,"--n-close-margin-rtl":g,"--n-padding":_,"--n-icon-margin-left":y,"--n-icon-margin-right":x}}),m=r?i(`alert`,u(()=>e.type[0]),p,e):void 0,h=f(!0),g=()=>{let{onAfterLeave:t,onAfterHide:n}=e;t&&t(),n&&n()};return{rtlEnabled:c,mergedClsPrefix:t,mergedBordered:n,visible:h,handleCloseClick:()=>{Promise.resolve(e.onClose?.call(e)).then(e=>{e!==!1&&(h.value=!1)})},handleAfterLeave:()=>{g()},mergedTheme:s,cssVars:r?void 0:p,themeClass:m?.themeClass,onRender:m?.onRender}},render(){var e;return(e=this.onRender)==null||e.call(this),m(v,{onAfterLeave:this.handleAfterLeave},{default:()=>{let{mergedClsPrefix:e,$slots:t}=this,n={class:[`${e}-alert`,this.themeClass,this.closable&&`${e}-alert--closable`,this.showIcon&&`${e}-alert--show-icon`,!this.title&&this.closable&&`${e}-alert--right-adjust`,this.rtlEnabled&&`${e}-alert--rtl`],style:this.cssVars,role:`alert`};return this.visible?m(`div`,Object.assign({},a(this.$attrs,n)),this.closable&&m(S,{clsPrefix:e,class:`${e}-alert__close`,onClick:this.handleCloseClick}),this.bordered&&m(`div`,{class:`${e}-alert__border`}),this.showIcon&&m(`div`,{class:`${e}-alert__icon`,"aria-hidden":`true`},g(t.icon,()=>[m(x,{clsPrefix:e},{default:()=>{switch(this.type){case`success`:return m(w,null);case`info`:return m(T,null);case`warning`:return m(E,null);case`error`:return m(D,null);default:return null}}})])),m(`div`,{class:[`${e}-alert-body`,this.mergedBordered&&`${e}-alert-body--bordered`]},_(t.header,t=>{let n=t||this.title;return n?m(`div`,{class:`${e}-alert-body__title`},n):null}),t.default&&m(`div`,{class:`${e}-alert-body__content`},t))):null}})}});export{N as t};