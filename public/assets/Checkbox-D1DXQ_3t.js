import{$ as e,Et as t,G as n,J as r,K as i,N as a,P as o,Q as s,Rt as c,Y as l,Z as u,cn as d,ft as f,i as p,in as m,wt as h,xt as g,z as _}from"./_plugin-vue_export-helper-CHFfEUVo.js";import{a as v,h as y,i as b,l as x,m as S,s as C}from"./Loading-DTHYZrnQ.js";import{u as w}from"./Scrollbar-DMBpoFRH.js";import{t as T}from"./misc-v7XGxIq4.js";import{u as E}from"./Button-BEyPvId2.js";import{t as D}from"./use-merged-state-D9bC4wj2.js";import{I as O}from"./index-BFrYTqRE.js";var k=_(`n-checkbox-group`),A=g({name:`CheckboxGroup`,props:{min:Number,max:Number,size:String,value:Array,defaultValue:{type:Array,default:null},disabled:{type:Boolean,default:void 0},"onUpdate:value":[Function,Array],onUpdateValue:[Function,Array],onChange:[Function,Array]},setup(e){let{mergedClsPrefixRef:t}=o(e),n=x(e),{mergedSizeRef:r,mergedDisabledRef:i}=n,a=m(e.defaultValue),s=f(()=>e.value),l=D(s,a),u=f(()=>l.value?.length||0),p=f(()=>Array.isArray(l.value)?new Set(l.value):new Set);function h(t,r){let{nTriggerFormInput:i,nTriggerFormChange:o}=n,{onChange:s,"onUpdate:value":c,onUpdateValue:u}=e;if(Array.isArray(l.value)){let e=Array.from(l.value),n=e.findIndex(e=>e===r);t?~n||(e.push(r),u&&y(u,e,{actionType:`check`,value:r}),c&&y(c,e,{actionType:`check`,value:r}),i(),o(),a.value=e,s&&y(s,e)):~n&&(e.splice(n,1),u&&y(u,e,{actionType:`uncheck`,value:r}),c&&y(c,e,{actionType:`uncheck`,value:r}),s&&y(s,e),a.value=e,i(),o())}else t?(u&&y(u,[r],{actionType:`check`,value:r}),c&&y(c,[r],{actionType:`check`,value:r}),s&&y(s,[r]),a.value=[r],i(),o()):(u&&y(u,[],{actionType:`uncheck`,value:r}),c&&y(c,[],{actionType:`uncheck`,value:r}),s&&y(s,[]),a.value=[],i(),o())}return c(k,{checkedCountRef:u,maxRef:d(e,`max`),minRef:d(e,`min`),valueSetRef:p,disabledRef:i,mergedSizeRef:r,toggleCheckbox:h}),{mergedClsPrefix:t}},render(){return h(`div`,{class:`${this.mergedClsPrefix}-checkbox-group`,role:`group`},this.$slots)}}),j=()=>h(`svg`,{viewBox:`0 0 64 64`,class:`check-icon`},h(`path`,{d:`M50.42,16.76L22.34,39.45l-8.1-11.46c-1.12-1.58-3.3-1.96-4.88-0.84c-1.58,1.12-1.95,3.3-0.84,4.88l10.26,14.51  c0.56,0.79,1.42,1.31,2.38,1.45c0.16,0.02,0.32,0.03,0.48,0.03c0.8,0,1.57-0.27,2.2-0.78l30.99-25.03c1.5-1.21,1.74-3.42,0.52-4.92  C54.13,15.78,51.93,15.55,50.42,16.76z`})),M=()=>h(`svg`,{viewBox:`0 0 100 100`,class:`line-icon`},h(`path`,{d:`M80.2,55.5H21.4c-2.8,0-5.1-2.5-5.1-5.5l0,0c0-3,2.3-5.5,5.1-5.5h58.7c2.8,0,5.1,2.5,5.1,5.5l0,0C85.2,53.1,82.9,55.5,80.2,55.5z`})),N=n([i(`checkbox`,`
 font-size: var(--n-font-size);
 outline: none;
 cursor: pointer;
 display: inline-flex;
 flex-wrap: nowrap;
 align-items: flex-start;
 word-break: break-word;
 line-height: var(--n-size);
 --n-merged-color-table: var(--n-color-table);
 `,[l(`show-label`,`line-height: var(--n-label-line-height);`),n(`&:hover`,[i(`checkbox-box`,[r(`border`,`border: var(--n-border-checked);`)])]),n(`&:focus:not(:active)`,[i(`checkbox-box`,[r(`border`,`
 border: var(--n-border-focus);
 box-shadow: var(--n-box-shadow-focus);
 `)])]),l(`inside-table`,[i(`checkbox-box`,`
 background-color: var(--n-merged-color-table);
 `)]),l(`checked`,[i(`checkbox-box`,`
 background-color: var(--n-color-checked);
 `,[i(`checkbox-icon`,[n(`.check-icon`,`
 opacity: 1;
 transform: scale(1);
 `)])])]),l(`indeterminate`,[i(`checkbox-box`,[i(`checkbox-icon`,[n(`.check-icon`,`
 opacity: 0;
 transform: scale(.5);
 `),n(`.line-icon`,`
 opacity: 1;
 transform: scale(1);
 `)])])]),l(`checked, indeterminate`,[n(`&:focus:not(:active)`,[i(`checkbox-box`,[r(`border`,`
 border: var(--n-border-checked);
 box-shadow: var(--n-box-shadow-focus);
 `)])]),i(`checkbox-box`,`
 background-color: var(--n-color-checked);
 border-left: 0;
 border-top: 0;
 `,[r(`border`,{border:`var(--n-border-checked)`})])]),l(`disabled`,{cursor:`not-allowed`},[l(`checked`,[i(`checkbox-box`,`
 background-color: var(--n-color-disabled-checked);
 `,[r(`border`,{border:`var(--n-border-disabled-checked)`}),i(`checkbox-icon`,[n(`.check-icon, .line-icon`,{fill:`var(--n-check-mark-color-disabled-checked)`})])])]),i(`checkbox-box`,`
 background-color: var(--n-color-disabled);
 `,[r(`border`,`
 border: var(--n-border-disabled);
 `),i(`checkbox-icon`,[n(`.check-icon, .line-icon`,`
 fill: var(--n-check-mark-color-disabled);
 `)])]),r(`label`,`
 color: var(--n-text-color-disabled);
 `)]),i(`checkbox-box-wrapper`,`
 position: relative;
 width: var(--n-size);
 flex-shrink: 0;
 flex-grow: 0;
 user-select: none;
 -webkit-user-select: none;
 `),i(`checkbox-box`,`
 position: absolute;
 left: 0;
 top: 50%;
 transform: translateY(-50%);
 height: var(--n-size);
 width: var(--n-size);
 display: inline-block;
 box-sizing: border-box;
 border-radius: var(--n-border-radius);
 background-color: var(--n-color);
 transition: background-color 0.3s var(--n-bezier);
 `,[r(`border`,`
 transition:
 border-color .3s var(--n-bezier),
 box-shadow .3s var(--n-bezier);
 border-radius: inherit;
 position: absolute;
 left: 0;
 right: 0;
 top: 0;
 bottom: 0;
 border: var(--n-border);
 `),i(`checkbox-icon`,`
 display: flex;
 align-items: center;
 justify-content: center;
 position: absolute;
 left: 1px;
 right: 1px;
 top: 1px;
 bottom: 1px;
 `,[n(`.check-icon, .line-icon`,`
 width: 100%;
 fill: var(--n-check-mark-color);
 opacity: 0;
 transform: scale(0.5);
 transform-origin: center;
 transition:
 fill 0.3s var(--n-bezier),
 transform 0.3s var(--n-bezier),
 opacity 0.3s var(--n-bezier),
 border-color 0.3s var(--n-bezier);
 `),b({left:`1px`,top:`1px`})])]),r(`label`,`
 color: var(--n-text-color);
 transition: color .3s var(--n-bezier);
 user-select: none;
 -webkit-user-select: none;
 padding: var(--n-label-padding);
 font-weight: var(--n-label-font-weight);
 `,[n(`&:empty`,{display:`none`})])]),s(i(`checkbox`,`
 --n-merged-color-table: var(--n-color-table-modal);
 `)),e(i(`checkbox`,`
 --n-merged-color-table: var(--n-color-table-popover);
 `))]),P=Object.assign(Object.assign({},p.props),{size:String,checked:{type:[Boolean,String,Number],default:void 0},defaultChecked:{type:[Boolean,String,Number],default:!1},value:[String,Number],disabled:{type:Boolean,default:void 0},indeterminate:Boolean,label:String,focusable:{type:Boolean,default:!0},checkedValue:{type:[Boolean,String,Number],default:!0},uncheckedValue:{type:[Boolean,String,Number],default:!1},"onUpdate:checked":[Function,Array],onUpdateChecked:[Function,Array],privateInsideTable:Boolean,onChange:[Function,Array]}),F=g({name:`Checkbox`,props:P,setup(e){let n=t(k,null),r=m(null),{mergedClsPrefixRef:i,inlineThemeDisabled:s,mergedRtlRef:c,mergedComponentPropsRef:l}=o(e),h=m(e.defaultChecked),g=d(e,`checked`),_=D(g,h),v=E(()=>{if(n){let t=n.valueSetRef.value;return t&&e.value!==void 0?t.has(e.value):!1}return _.value===e.checkedValue}),b=x(e,{mergedSize(t){let{size:r}=e;if(r!==void 0)return r;if(n){let{value:e}=n.mergedSizeRef;if(e!==void 0)return e}if(t){let{mergedSize:e}=t;if(e!==void 0)return e.value}return l?.value?.Checkbox?.size||`medium`},mergedDisabled(t){let{disabled:r}=e;if(r!==void 0)return r;if(n){if(n.disabledRef.value)return!0;let{maxRef:{value:e},checkedCountRef:t}=n;if(e!==void 0&&t.value>=e&&!v.value)return!0;let{minRef:{value:r}}=n;if(r!==void 0&&t.value<=r&&v.value)return!0}return t?t.disabled.value:!1}}),{mergedDisabledRef:S,mergedSizeRef:w}=b,A=p(`Checkbox`,`-checkbox`,N,O,e,i);function j(t){if(n&&e.value!==void 0)n.toggleCheckbox(!v.value,e.value);else{let{onChange:n,"onUpdate:checked":r,onUpdateChecked:i}=e,{nTriggerFormInput:a,nTriggerFormChange:o}=b,s=v.value?e.uncheckedValue:e.checkedValue;r&&y(r,s,t),i&&y(i,s,t),n&&y(n,s,t),a(),o(),h.value=s}}function M(e){S.value||j(e)}function P(e){if(!S.value)switch(e.key){case` `:case`Enter`:j(e)}}function F(e){e.key===` `&&e.preventDefault()}let I={focus:()=>{var e;(e=r.value)==null||e.focus()},blur:()=>{var e;(e=r.value)==null||e.blur()}},L=C(`Checkbox`,c,i),R=f(()=>{let{value:e}=w,{common:{cubicBezierEaseInOut:t},self:{borderRadius:n,color:r,colorChecked:i,colorDisabled:a,colorTableHeader:o,colorTableHeaderModal:s,colorTableHeaderPopover:c,checkMarkColor:l,checkMarkColorDisabled:d,border:f,borderFocus:p,borderDisabled:m,borderChecked:h,boxShadowFocus:g,textColor:_,textColorDisabled:v,checkMarkColorDisabledChecked:y,colorDisabledChecked:b,borderDisabledChecked:x,labelPadding:S,labelLineHeight:C,labelFontWeight:T,[u(`fontSize`,e)]:E,[u(`size`,e)]:D}}=A.value;return{"--n-label-line-height":C,"--n-label-font-weight":T,"--n-size":D,"--n-bezier":t,"--n-border-radius":n,"--n-border":f,"--n-border-checked":h,"--n-border-focus":p,"--n-border-disabled":m,"--n-border-disabled-checked":x,"--n-box-shadow-focus":g,"--n-color":r,"--n-color-checked":i,"--n-color-table":o,"--n-color-table-modal":s,"--n-color-table-popover":c,"--n-color-disabled":a,"--n-color-disabled-checked":b,"--n-text-color":_,"--n-text-color-disabled":v,"--n-check-mark-color":l,"--n-check-mark-color-disabled":d,"--n-check-mark-color-disabled-checked":y,"--n-font-size":E,"--n-label-padding":S}}),z=s?a(`checkbox`,f(()=>w.value[0]),R,e):void 0;return Object.assign(b,I,{rtlEnabled:L,selfRef:r,mergedClsPrefix:i,mergedDisabled:S,renderedChecked:v,mergedTheme:A,labelId:T(),handleClick:M,handleKeyUp:P,handleKeyDown:F,cssVars:s?void 0:R,themeClass:z?.themeClass,onRender:z?.onRender})},render(){var e;let{$slots:t,renderedChecked:n,mergedDisabled:r,indeterminate:i,privateInsideTable:a,cssVars:o,labelId:s,label:c,mergedClsPrefix:l,focusable:u,handleKeyUp:d,handleKeyDown:f,handleClick:p}=this;(e=this.onRender)==null||e.call(this);let m=S(t.default,e=>c||e?h(`span`,{class:`${l}-checkbox__label`,id:s},c||e):null);return h(`div`,{ref:`selfRef`,class:[`${l}-checkbox`,this.themeClass,this.rtlEnabled&&`${l}-checkbox--rtl`,n&&`${l}-checkbox--checked`,r&&`${l}-checkbox--disabled`,i&&`${l}-checkbox--indeterminate`,a&&`${l}-checkbox--inside-table`,m&&`${l}-checkbox--show-label`],tabindex:r||!u?void 0:0,role:`checkbox`,"aria-checked":i?`mixed`:n,"aria-labelledby":s,style:o,onKeyup:d,onKeydown:f,onClick:p,onMousedown:()=>{w(`selectstart`,window,e=>{e.preventDefault()},{once:!0})}},h(`div`,{class:`${l}-checkbox-box-wrapper`},`\xA0`,h(`div`,{class:`${l}-checkbox-box`},h(v,null,{default:()=>this.indeterminate?h(`div`,{key:`indeterminate`,class:`${l}-checkbox-icon`},M()):h(`div`,{key:`check`,class:`${l}-checkbox-icon`},j())}),h(`div`,{class:`${l}-checkbox-box__border`}))),m)}});export{A as n,F as t};