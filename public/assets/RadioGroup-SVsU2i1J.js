import{$ as e,B as t,Ct as n,Ft as r,K as i,Kt as a,Nt as o,V as s,at as c,cn as l,d as u,et as d,fn as f,it as p,jt as m,nt as h,rt as g}from"./_plugin-vue_export-helper-BRaiA-U2.js";import{d as _,f as v,h as y,o as b}from"./light-C6m6bYe2.js";import{t as x}from"./flatten-e7zF5Arh.js";import{t as S}from"./get-slot-6kXJmSMP.js";import{a as C}from"./Button-BR4zNRVZ.js";import{$ as w,m as T}from"./index-D2BO3n0Q.js";var E={name:String,value:{type:[String,Number,Boolean],default:`on`},checked:{type:Boolean,default:void 0},defaultChecked:Boolean,disabled:{type:Boolean,default:void 0},label:String,size:String,onUpdateChecked:[Function,Array],"onUpdate:checked":[Function,Array],checkedValue:{type:Boolean,default:void 0}},D=i(`n-radio-group`);function O(e){let t=r(D,null),{mergedClsPrefixRef:n,mergedComponentPropsRef:i}=s(e),a=C(e,{mergedSize(n){let{size:r}=e;if(r!==void 0)return r;if(t){let{mergedSizeRef:{value:e}}=t;if(e!==void 0)return e}return n?n.mergedSize.value:i?.value?.Radio?.size||`medium`},mergedDisabled(n){return!!(e.disabled||t?.disabledRef.value||n?.disabled.value)}}),{mergedSizeRef:o,mergedDisabledRef:c}=a,u=l(null),d=l(null),p=l(e.defaultChecked),m=f(e,`checked`),h=w(m,p),g=y(()=>t?t.valueRef.value===e.value:h.value),_=y(()=>{let{name:n}=e;if(n!==void 0)return n;if(t)return t.nameRef.value}),b=l(!1);function x(){if(t){let{doUpdateValue:n}=t,{value:r}=e;v(n,r)}else{let{onUpdateChecked:t,"onUpdate:checked":n}=e,{nTriggerFormInput:r,nTriggerFormChange:i}=a;t&&v(t,!0),n&&v(n,!0),r(),i(),p.value=!0}}function S(){c.value||g.value||x()}function T(){S(),u.value&&(u.value.checked=g.value)}function E(){b.value=!1}function O(){b.value=!0}return{mergedClsPrefix:t?t.mergedClsPrefixRef:n,inputRef:u,labelRef:d,mergedName:_,mergedDisabled:c,renderSafeChecked:g,focus:b,mergedSize:o,handleRadioInputChange:T,handleRadioInputBlur:E,handleRadioInputFocus:O}}var k=m({name:`RadioButton`,props:E,setup:O,render(){let{mergedClsPrefix:e}=this;return o(`label`,{class:[`${e}-radio-button`,this.mergedDisabled&&`${e}-radio-button--disabled`,this.renderSafeChecked&&`${e}-radio-button--checked`,this.focus&&[`${e}-radio-button--focus`]]},o(`input`,{ref:`inputRef`,type:`radio`,class:`${e}-radio-input`,value:this.value,name:this.mergedName,checked:this.renderSafeChecked,disabled:this.mergedDisabled,onChange:this.handleRadioInputChange,onFocus:this.handleRadioInputFocus,onBlur:this.handleRadioInputBlur}),o(`div`,{class:`${e}-radio-button__state-border`}),_(this.$slots.default,t=>!t&&!this.label?null:o(`div`,{ref:`labelRef`,class:`${e}-radio__label`},t||this.label)))}}),A=d(`radio-group`,`
 display: inline-block;
 font-size: var(--n-font-size);
`,[h(`splitor`,`
 display: inline-block;
 vertical-align: bottom;
 width: 1px;
 transition:
 background-color .3s var(--n-bezier),
 opacity .3s var(--n-bezier);
 background: var(--n-button-border-color);
 `,[g(`checked`,{backgroundColor:`var(--n-button-border-color-active)`}),g(`disabled`,{opacity:`var(--n-opacity-disabled)`})]),g(`button-group`,`
 white-space: nowrap;
 height: var(--n-height);
 line-height: var(--n-height);
 `,[d(`radio-button`,{height:`var(--n-height)`,lineHeight:`var(--n-height)`}),h(`splitor`,{height:`var(--n-height)`})]),d(`radio-button`,`
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
 `,[d(`radio-input`,`
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
 `),h(`state-border`,`
 z-index: 1;
 pointer-events: none;
 position: absolute;
 box-shadow: var(--n-button-box-shadow);
 transition: box-shadow .3s var(--n-bezier);
 left: -1px;
 bottom: -1px;
 right: -1px;
 top: -1px;
 `),e(`&:first-child`,`
 border-top-left-radius: var(--n-button-border-radius);
 border-bottom-left-radius: var(--n-button-border-radius);
 border-left: 1px solid var(--n-button-border-color);
 `,[h(`state-border`,`
 border-top-left-radius: var(--n-button-border-radius);
 border-bottom-left-radius: var(--n-button-border-radius);
 `)]),e(`&:last-child`,`
 border-top-right-radius: var(--n-button-border-radius);
 border-bottom-right-radius: var(--n-button-border-radius);
 border-right: 1px solid var(--n-button-border-color);
 `,[h(`state-border`,`
 border-top-right-radius: var(--n-button-border-radius);
 border-bottom-right-radius: var(--n-button-border-radius);
 `)]),p(`disabled`,`
 cursor: pointer;
 `,[e(`&:hover`,[h(`state-border`,`
 transition: box-shadow .3s var(--n-bezier);
 box-shadow: var(--n-button-box-shadow-hover);
 `),p(`checked`,{color:`var(--n-button-text-color-hover)`})]),g(`focus`,[e(`&:not(:active)`,[h(`state-border`,{boxShadow:`var(--n-button-box-shadow-focus)`})])])]),g(`checked`,`
 background: var(--n-button-color-active);
 color: var(--n-button-text-color-active);
 border-color: var(--n-button-border-color-active);
 `),g(`disabled`,`
 cursor: not-allowed;
 opacity: var(--n-opacity-disabled);
 `)])]);function j(e,t,n){let r=[],i=!1;for(let a=0;a<e.length;++a){let s=e[a],c=s.type?.name;c===`RadioButton`&&(i=!0);let l=s.props;if(c!==`RadioButton`){r.push(s);continue}if(a===0)r.push(s);else{let e=r[r.length-1].props,i=t===e.value,a=e.disabled,c=t===l.value,u=l.disabled,d=(i?2:0)+ +!a,f=(c?2:0)+ +!u,p={[`${n}-radio-group__splitor--disabled`]:a,[`${n}-radio-group__splitor--checked`]:i},m={[`${n}-radio-group__splitor--disabled`]:u,[`${n}-radio-group__splitor--checked`]:c},h=d<f?m:p;r.push(o(`div`,{class:[`${n}-radio-group__splitor`,h]}),s)}}return{children:r,isButtonGroup:i}}var M=Object.assign(Object.assign({},u.props),{name:String,value:[String,Number,Boolean],defaultValue:{type:[String,Number,Boolean],default:null},size:String,disabled:{type:Boolean,default:void 0},"onUpdate:value":[Function,Array],onUpdateValue:[Function,Array]}),N=m({name:`RadioGroup`,props:M,setup(e){let r=l(null),{mergedSizeRef:i,mergedDisabledRef:o,nTriggerFormChange:d,nTriggerFormInput:p,nTriggerFormBlur:m,nTriggerFormFocus:h}=C(e),{mergedClsPrefixRef:g,inlineThemeDisabled:_,mergedRtlRef:y}=s(e),x=u(`Radio`,`-radio-group`,A,T,e,g),S=l(e.defaultValue),E=f(e,`value`),O=w(E,S);function k(t){let{onUpdateValue:n,"onUpdate:value":r}=e;n&&v(n,t),r&&v(r,t),S.value=t,d(),p()}function j(e){let{value:t}=r;t&&(t.contains(e.relatedTarget)||h())}function M(e){let{value:t}=r;t&&(t.contains(e.relatedTarget)||m())}a(D,{mergedClsPrefixRef:g,nameRef:f(e,`name`),valueRef:O,disabledRef:o,mergedSizeRef:i,doUpdateValue:k});let N=b(`Radio`,y,g),P=n(()=>{let{value:e}=i,{common:{cubicBezierEaseInOut:t},self:{buttonBorderColor:n,buttonBorderColorActive:r,buttonBorderRadius:a,buttonBoxShadow:o,buttonBoxShadowFocus:s,buttonBoxShadowHover:l,buttonColor:u,buttonColorActive:d,buttonTextColor:f,buttonTextColorActive:p,buttonTextColorHover:m,opacityDisabled:h,[c(`buttonHeight`,e)]:g,[c(`fontSize`,e)]:_}}=x.value;return{"--n-font-size":_,"--n-bezier":t,"--n-button-border-color":n,"--n-button-border-color-active":r,"--n-button-border-radius":a,"--n-button-box-shadow":o,"--n-button-box-shadow-focus":s,"--n-button-box-shadow-hover":l,"--n-button-color":u,"--n-button-color-active":d,"--n-button-text-color":f,"--n-button-text-color-hover":m,"--n-button-text-color-active":p,"--n-height":g,"--n-opacity-disabled":h}}),F=_?t(`radio-group`,n(()=>i.value[0]),P,e):void 0;return{selfElRef:r,rtlEnabled:N,mergedClsPrefix:g,mergedValue:O,handleFocusout:M,handleFocusin:j,cssVars:_?void 0:P,themeClass:F?.themeClass,onRender:F?.onRender}},render(){var e;let{mergedValue:t,mergedClsPrefix:n,handleFocusin:r,handleFocusout:i}=this,{children:a,isButtonGroup:s}=j(x(S(this)),t,n);return(e=this.onRender)==null||e.call(this),o(`div`,{onFocusin:r,onFocusout:i,ref:`selfElRef`,class:[`${n}-radio-group`,this.rtlEnabled&&`${n}-radio-group--rtl`,this.themeClass,s&&`${n}-radio-group--button-group`],style:this.cssVars},a)}});export{k as n,N as t};