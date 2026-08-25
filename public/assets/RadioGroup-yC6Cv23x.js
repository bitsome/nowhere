import{Et as e,G as t,J as n,K as r,N as i,P as a,Rt as o,X as s,Y as c,Z as l,cn as u,ft as d,i as f,in as p,wt as m,xt as h,z as g}from"./_plugin-vue_export-helper-CHFfEUVo.js";import{h as _,l as v,m as y,s as b}from"./Loading-DTHYZrnQ.js";import{u as x}from"./Button-BEyPvId2.js";import{t as S}from"./use-merged-state-D9bC4wj2.js";import{t as C}from"./flatten-XDzPCGlv.js";import{t as w}from"./get-slot-6kXJmSMP.js";import{M as T}from"./index-BFrYTqRE.js";var E={name:String,value:{type:[String,Number,Boolean],default:`on`},checked:{type:Boolean,default:void 0},defaultChecked:Boolean,disabled:{type:Boolean,default:void 0},label:String,size:String,onUpdateChecked:[Function,Array],"onUpdate:checked":[Function,Array],checkedValue:{type:Boolean,default:void 0}},D=g(`n-radio-group`);function O(t){let n=e(D,null),{mergedClsPrefixRef:r,mergedComponentPropsRef:i}=a(t),o=v(t,{mergedSize(e){let{size:r}=t;if(r!==void 0)return r;if(n){let{mergedSizeRef:{value:e}}=n;if(e!==void 0)return e}return e?e.mergedSize.value:i?.value?.Radio?.size||`medium`},mergedDisabled(e){return!!(t.disabled||n?.disabledRef.value||e?.disabled.value)}}),{mergedSizeRef:s,mergedDisabledRef:c}=o,l=p(null),d=p(null),f=p(t.defaultChecked),m=u(t,`checked`),h=S(m,f),g=x(()=>n?n.valueRef.value===t.value:h.value),y=x(()=>{let{name:e}=t;if(e!==void 0)return e;if(n)return n.nameRef.value}),b=p(!1);function C(){if(n){let{doUpdateValue:e}=n,{value:r}=t;_(e,r)}else{let{onUpdateChecked:e,"onUpdate:checked":n}=t,{nTriggerFormInput:r,nTriggerFormChange:i}=o;e&&_(e,!0),n&&_(n,!0),r(),i(),f.value=!0}}function w(){c.value||g.value||C()}function T(){w(),l.value&&(l.value.checked=g.value)}function E(){b.value=!1}function O(){b.value=!0}return{mergedClsPrefix:n?n.mergedClsPrefixRef:r,inputRef:l,labelRef:d,mergedName:y,mergedDisabled:c,renderSafeChecked:g,focus:b,mergedSize:s,handleRadioInputChange:T,handleRadioInputBlur:E,handleRadioInputFocus:O}}var k=h({name:`RadioButton`,props:E,setup:O,render(){let{mergedClsPrefix:e}=this;return m(`label`,{class:[`${e}-radio-button`,this.mergedDisabled&&`${e}-radio-button--disabled`,this.renderSafeChecked&&`${e}-radio-button--checked`,this.focus&&[`${e}-radio-button--focus`]]},m(`input`,{ref:`inputRef`,type:`radio`,class:`${e}-radio-input`,value:this.value,name:this.mergedName,checked:this.renderSafeChecked,disabled:this.mergedDisabled,onChange:this.handleRadioInputChange,onFocus:this.handleRadioInputFocus,onBlur:this.handleRadioInputBlur}),m(`div`,{class:`${e}-radio-button__state-border`}),y(this.$slots.default,t=>!t&&!this.label?null:m(`div`,{ref:`labelRef`,class:`${e}-radio__label`},t||this.label)))}}),A=r(`radio-group`,`
 display: inline-block;
 font-size: var(--n-font-size);
`,[n(`splitor`,`
 display: inline-block;
 vertical-align: bottom;
 width: 1px;
 transition:
 background-color .3s var(--n-bezier),
 opacity .3s var(--n-bezier);
 background: var(--n-button-border-color);
 `,[c(`checked`,{backgroundColor:`var(--n-button-border-color-active)`}),c(`disabled`,{opacity:`var(--n-opacity-disabled)`})]),c(`button-group`,`
 white-space: nowrap;
 height: var(--n-height);
 line-height: var(--n-height);
 `,[r(`radio-button`,{height:`var(--n-height)`,lineHeight:`var(--n-height)`}),n(`splitor`,{height:`var(--n-height)`})]),r(`radio-button`,`
 vertical-align: bottom;
 outline: none;
 position: relative;
 user-select: none;
 -webkit-user-select: none;
 display: inline-block;
 box-sizing: border-box;
 padding-left: 14px;
 padding-right: 14px;
 white-space: nowrap;
 transition:
 background-color .3s var(--n-bezier),
 opacity .3s var(--n-bezier),
 border-color .3s var(--n-bezier),
 color .3s var(--n-bezier);
 background: var(--n-button-color);
 color: var(--n-button-text-color);
 border-top: 1px solid var(--n-button-border-color);
 border-bottom: 1px solid var(--n-button-border-color);
 `,[r(`radio-input`,`
 pointer-events: none;
 position: absolute;
 border: 0;
 border-radius: inherit;
 left: 0;
 right: 0;
 top: 0;
 bottom: 0;
 opacity: 0;
 z-index: 1;
 `),n(`state-border`,`
 z-index: 1;
 pointer-events: none;
 position: absolute;
 box-shadow: var(--n-button-box-shadow);
 transition: box-shadow .3s var(--n-bezier);
 left: -1px;
 bottom: -1px;
 right: -1px;
 top: -1px;
 `),t(`&:first-child`,`
 border-top-left-radius: var(--n-button-border-radius);
 border-bottom-left-radius: var(--n-button-border-radius);
 border-left: 1px solid var(--n-button-border-color);
 `,[n(`state-border`,`
 border-top-left-radius: var(--n-button-border-radius);
 border-bottom-left-radius: var(--n-button-border-radius);
 `)]),t(`&:last-child`,`
 border-top-right-radius: var(--n-button-border-radius);
 border-bottom-right-radius: var(--n-button-border-radius);
 border-right: 1px solid var(--n-button-border-color);
 `,[n(`state-border`,`
 border-top-right-radius: var(--n-button-border-radius);
 border-bottom-right-radius: var(--n-button-border-radius);
 `)]),s(`disabled`,`
 cursor: pointer;
 `,[t(`&:hover`,[n(`state-border`,`
 transition: box-shadow .3s var(--n-bezier);
 box-shadow: var(--n-button-box-shadow-hover);
 `),s(`checked`,{color:`var(--n-button-text-color-hover)`})]),c(`focus`,[t(`&:not(:active)`,[n(`state-border`,{boxShadow:`var(--n-button-box-shadow-focus)`})])])]),c(`checked`,`
 background: var(--n-button-color-active);
 color: var(--n-button-text-color-active);
 border-color: var(--n-button-border-color-active);
 `),c(`disabled`,`
 cursor: not-allowed;
 opacity: var(--n-opacity-disabled);
 `)])]);function j(e,t,n){let r=[],i=!1;for(let a=0;a<e.length;++a){let o=e[a],s=o.type?.name;s===`RadioButton`&&(i=!0);let c=o.props;if(s!==`RadioButton`){r.push(o);continue}if(a===0)r.push(o);else{let e=r[r.length-1].props,i=t===e.value,a=e.disabled,s=t===c.value,l=c.disabled,u=(i?2:0)+ +!a,d=(s?2:0)+ +!l,f={[`${n}-radio-group__splitor--disabled`]:a,[`${n}-radio-group__splitor--checked`]:i},p={[`${n}-radio-group__splitor--disabled`]:l,[`${n}-radio-group__splitor--checked`]:s},h=u<d?p:f;r.push(m(`div`,{class:[`${n}-radio-group__splitor`,h]}),o)}}return{children:r,isButtonGroup:i}}var M=Object.assign(Object.assign({},f.props),{name:String,value:[String,Number,Boolean],defaultValue:{type:[String,Number,Boolean],default:null},size:String,disabled:{type:Boolean,default:void 0},"onUpdate:value":[Function,Array],onUpdateValue:[Function,Array]}),N=h({name:`RadioGroup`,props:M,setup(e){let t=p(null),{mergedSizeRef:n,mergedDisabledRef:r,nTriggerFormChange:s,nTriggerFormInput:c,nTriggerFormBlur:m,nTriggerFormFocus:h}=v(e),{mergedClsPrefixRef:g,inlineThemeDisabled:y,mergedRtlRef:x}=a(e),C=f(`Radio`,`-radio-group`,A,T,e,g),w=p(e.defaultValue),E=u(e,`value`),O=S(E,w);function k(t){let{onUpdateValue:n,"onUpdate:value":r}=e;n&&_(n,t),r&&_(r,t),w.value=t,s(),c()}function j(e){let{value:n}=t;n&&(n.contains(e.relatedTarget)||h())}function M(e){let{value:n}=t;n&&(n.contains(e.relatedTarget)||m())}o(D,{mergedClsPrefixRef:g,nameRef:u(e,`name`),valueRef:O,disabledRef:r,mergedSizeRef:n,doUpdateValue:k});let N=b(`Radio`,x,g),P=d(()=>{let{value:e}=n,{common:{cubicBezierEaseInOut:t},self:{buttonBorderColor:r,buttonBorderColorActive:i,buttonBorderRadius:a,buttonBoxShadow:o,buttonBoxShadowFocus:s,buttonBoxShadowHover:c,buttonColor:u,buttonColorActive:d,buttonTextColor:f,buttonTextColorActive:p,buttonTextColorHover:m,opacityDisabled:h,[l(`buttonHeight`,e)]:g,[l(`fontSize`,e)]:_}}=C.value;return{"--n-font-size":_,"--n-bezier":t,"--n-button-border-color":r,"--n-button-border-color-active":i,"--n-button-border-radius":a,"--n-button-box-shadow":o,"--n-button-box-shadow-focus":s,"--n-button-box-shadow-hover":c,"--n-button-color":u,"--n-button-color-active":d,"--n-button-text-color":f,"--n-button-text-color-hover":m,"--n-button-text-color-active":p,"--n-height":g,"--n-opacity-disabled":h}}),F=y?i(`radio-group`,d(()=>n.value[0]),P,e):void 0;return{selfElRef:t,rtlEnabled:N,mergedClsPrefix:g,mergedValue:O,handleFocusout:M,handleFocusin:j,cssVars:y?void 0:P,themeClass:F?.themeClass,onRender:F?.onRender}},render(){var e;let{mergedValue:t,mergedClsPrefix:n,handleFocusin:r,handleFocusout:i}=this,{children:a,isButtonGroup:o}=j(C(w(this)),t,n);return(e=this.onRender)==null||e.call(this),m(`div`,{onFocusin:r,onFocusout:i,ref:`selfElRef`,class:[`${n}-radio-group`,this.rtlEnabled&&`${n}-radio-group--rtl`,this.themeClass,o&&`${n}-radio-group--button-group`],style:this.cssVars},a)}});export{k as n,N as t};