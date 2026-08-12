import{$ as e,B as t,Ct as n,J as r,Lt as i,Nt as a,V as o,Y as s,at as c,cn as l,d as u,et as d,i as f,jt as p,l as m,nt as h,rt as g}from"./_plugin-vue_export-helper-xtfkLN2I.js";import{a as _,d as v,l as y,o as b}from"./light-F9vJ8mix.js";import{i as x}from"./fade-in.cssr-DyPg4UAa.js";import{B as S,E as C,I as w,L as T,R as E,T as D,z as O}from"./index-Cxr_Uq9z.js";function k(e){let{lineHeight:t,borderRadius:n,fontWeightStrong:i,baseColor:a,dividerColor:o,actionColor:c,textColor1:l,textColor2:u,closeColorHover:d,closeColorPressed:f,closeIconColor:p,closeIconColorHover:m,closeIconColorPressed:h,infoColor:g,successColor:_,warningColor:v,errorColor:y,fontSize:b}=e;return Object.assign(Object.assign({},C),{fontSize:b,lineHeight:t,titleFontWeight:i,borderRadius:n,border:`1px solid ${o}`,color:c,titleTextColor:l,iconColor:u,contentTextColor:u,closeBorderRadius:n,closeColorHover:d,closeColorPressed:f,closeIconColor:p,closeIconColorHover:m,closeIconColorPressed:h,borderInfo:`1px solid ${s(a,r(g,{alpha:.25}))}`,colorInfo:s(a,r(g,{alpha:.08})),titleTextColorInfo:l,iconColorInfo:g,contentTextColorInfo:u,closeColorHoverInfo:d,closeColorPressedInfo:f,closeIconColorInfo:p,closeIconColorHoverInfo:m,closeIconColorPressedInfo:h,borderSuccess:`1px solid ${s(a,r(_,{alpha:.25}))}`,colorSuccess:s(a,r(_,{alpha:.08})),titleTextColorSuccess:l,iconColorSuccess:_,contentTextColorSuccess:u,closeColorHoverSuccess:d,closeColorPressedSuccess:f,closeIconColorSuccess:p,closeIconColorHoverSuccess:m,closeIconColorPressedSuccess:h,borderWarning:`1px solid ${s(a,r(v,{alpha:.33}))}`,colorWarning:s(a,r(v,{alpha:.08})),titleTextColorWarning:l,iconColorWarning:v,contentTextColorWarning:u,closeColorHoverWarning:d,closeColorPressedWarning:f,closeIconColorWarning:p,closeIconColorHoverWarning:m,closeIconColorPressedWarning:h,borderError:`1px solid ${s(a,r(y,{alpha:.25}))}`,colorError:s(a,r(y,{alpha:.08})),titleTextColorError:l,iconColorError:y,contentTextColorError:u,closeColorHoverError:d,closeColorPressedError:f,closeIconColorError:p,closeIconColorHoverError:m,closeIconColorPressedError:h})}var A={name:`Alert`,common:f,self:k},j=d(`alert`,`
 line-height: var(--n-line-height);
 border-radius: var(--n-border-radius);
 position: relative;
 transition: background-color .3s var(--n-bezier);
 background-color: var(--n-color);
 text-align: start;
 word-break: break-word;
`,[h(`border`,`
 border-radius: inherit;
 position: absolute;
 left: 0;
 right: 0;
 top: 0;
 bottom: 0;
 transition: border-color .3s var(--n-bezier);
 border: var(--n-border);
 pointer-events: none;
 `),g(`closable`,[d(`alert-body`,[h(`title`,`
 padding-right: 24px;
 `)])]),h(`icon`,{color:`var(--n-icon-color)`}),d(`alert-body`,{padding:`var(--n-padding)`},[h(`title`,{color:`var(--n-title-text-color)`}),h(`content`,{color:`var(--n-content-text-color)`})]),D({originalTransition:`transform .3s var(--n-bezier)`,enterToProps:{transform:`scale(1)`},leaveToProps:{transform:`scale(0.9)`}}),h(`icon`,`
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
 `),h(`close`,`
 transition:
 color .3s var(--n-bezier),
 background-color .3s var(--n-bezier);
 position: absolute;
 right: 0;
 top: 0;
 margin: var(--n-close-margin);
 `),g(`show-icon`,[d(`alert-body`,{paddingLeft:`calc(var(--n-icon-margin-left) + var(--n-icon-size) + var(--n-icon-margin-right))`})]),g(`right-adjust`,[d(`alert-body`,{paddingRight:`calc(var(--n-close-size) + var(--n-padding) + 2px)`})]),d(`alert-body`,`
 border-radius: var(--n-border-radius);
 transition: border-color .3s var(--n-bezier);
 `,[h(`title`,`
 transition: color .3s var(--n-bezier);
 font-size: 16px;
 line-height: 19px;
 font-weight: var(--n-title-font-weight);
 `,[e(`& +`,[h(`content`,{marginTop:`9px`})])]),h(`content`,{transition:`color .3s var(--n-bezier)`,fontSize:`var(--n-font-size)`})]),h(`icon`,{transition:`color .3s var(--n-bezier)`})]),M=Object.assign(Object.assign({},u.props),{title:String,showIcon:{type:Boolean,default:!0},type:{type:String,default:`default`},bordered:{type:Boolean,default:!0},closable:Boolean,onClose:Function,onAfterLeave:Function,onAfterHide:Function}),N=p({name:`Alert`,inheritAttrs:!1,props:M,slots:Object,setup(e){let{mergedClsPrefixRef:r,mergedBorderedRef:i,inlineThemeDisabled:a,mergedRtlRef:s}=o(e),d=u(`Alert`,`-alert`,j,A,e,r),f=b(`Alert`,s,r),p=n(()=>{let{common:{cubicBezierEaseInOut:t},self:n}=d.value,{fontSize:r,borderRadius:i,titleFontWeight:a,lineHeight:o,iconSize:s,iconMargin:l,iconMarginRtl:u,closeIconSize:f,closeBorderRadius:p,closeSize:m,closeMargin:h,closeMarginRtl:g,padding:_}=n,{type:v}=e,{left:y,right:b}=x(l);return{"--n-bezier":t,"--n-color":n[c(`color`,v)],"--n-close-icon-size":f,"--n-close-border-radius":p,"--n-close-color-hover":n[c(`closeColorHover`,v)],"--n-close-color-pressed":n[c(`closeColorPressed`,v)],"--n-close-icon-color":n[c(`closeIconColor`,v)],"--n-close-icon-color-hover":n[c(`closeIconColorHover`,v)],"--n-close-icon-color-pressed":n[c(`closeIconColorPressed`,v)],"--n-icon-color":n[c(`iconColor`,v)],"--n-border":n[c(`border`,v)],"--n-title-text-color":n[c(`titleTextColor`,v)],"--n-content-text-color":n[c(`contentTextColor`,v)],"--n-line-height":o,"--n-border-radius":i,"--n-font-size":r,"--n-title-font-weight":a,"--n-icon-size":s,"--n-icon-margin":l,"--n-icon-margin-rtl":u,"--n-close-size":m,"--n-close-margin":h,"--n-close-margin-rtl":g,"--n-padding":_,"--n-icon-margin-left":y,"--n-icon-margin-right":b}}),m=a?t(`alert`,n(()=>e.type[0]),p,e):void 0,h=l(!0),g=()=>{let{onAfterLeave:t,onAfterHide:n}=e;t&&t(),n&&n()};return{rtlEnabled:f,mergedClsPrefix:r,mergedBordered:i,visible:h,handleCloseClick:()=>{Promise.resolve(e.onClose?.call(e)).then(e=>{e!==!1&&(h.value=!1)})},handleAfterLeave:()=>{g()},mergedTheme:d,cssVars:a?void 0:p,themeClass:m?.themeClass,onRender:m?.onRender}},render(){var e;return(e=this.onRender)==null||e.call(this),a(_,{onAfterLeave:this.handleAfterLeave},{default:()=>{let{mergedClsPrefix:e,$slots:t}=this,n={class:[`${e}-alert`,this.themeClass,this.closable&&`${e}-alert--closable`,this.showIcon&&`${e}-alert--show-icon`,!this.title&&this.closable&&`${e}-alert--right-adjust`,this.rtlEnabled&&`${e}-alert--rtl`],style:this.cssVars,role:`alert`};return this.visible?a(`div`,Object.assign({},i(this.$attrs,n)),this.closable&&a(w,{clsPrefix:e,class:`${e}-alert__close`,onClick:this.handleCloseClick}),this.bordered&&a(`div`,{class:`${e}-alert__border`}),this.showIcon&&a(`div`,{class:`${e}-alert__icon`,"aria-hidden":`true`},y(t.icon,()=>[a(m,{clsPrefix:e},{default:()=>{switch(this.type){case`success`:return a(E,null);case`info`:return a(O,null);case`warning`:return a(T,null);case`error`:return a(S,null);default:return null}}})])),a(`div`,{class:[`${e}-alert-body`,this.mergedBordered&&`${e}-alert-body--bordered`]},v(t.header,t=>{let n=t||this.title;return n?a(`div`,{class:`${e}-alert-body__title`},n):null}),t.default&&a(`div`,{class:`${e}-alert-body__content`},t))):null}})}});export{N as t};