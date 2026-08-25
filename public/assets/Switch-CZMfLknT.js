import{B as e,G as t,J as n,K as r,N as i,P as a,X as o,Y as s,Z as c,cn as l,ft as u,i as d,in as f,n as p,wt as m,xt as h}from"./_plugin-vue_export-helper-CHFfEUVo.js";import{a as g,d as _,h as v,i as y,l as b,m as x,t as S}from"./Loading-DTHYZrnQ.js";import{s as C,u as w}from"./Close-DCuJsqL1.js";import{t as T}from"./use-merged-state-D9bC4wj2.js";import{C as E}from"./index-BFrYTqRE.js";function D(t){let{primaryColor:n,opacityDisabled:r,borderRadius:i,textColor3:a}=t;return Object.assign(Object.assign({},E),{iconColor:a,textColor:`white`,loadingColor:n,opacityDisabled:r,railColor:`rgba(0, 0, 0, .14)`,railColorActive:n,buttonBoxShadow:`0 1px 4px 0 rgba(0, 0, 0, 0.3), inset 0 0 1px 0 rgba(0, 0, 0, 0.05)`,buttonColor:`#FFF`,railBorderRadiusSmall:i,railBorderRadiusMedium:i,railBorderRadiusLarge:i,buttonBorderRadiusSmall:i,buttonBorderRadiusMedium:i,buttonBorderRadiusLarge:i,boxShadowFocus:`0 0 0 2px ${e(n,{alpha:.2})}`})}var O={name:`Switch`,common:p,self:D},k=r(`switch`,`
 height: var(--n-height);
 min-width: var(--n-width);
 vertical-align: middle;
 user-select: none;
 -webkit-user-select: none;
 display: inline-flex;
 outline: none;
 justify-content: center;
 align-items: center;
`,[n(`children-placeholder`,`
 height: var(--n-rail-height);
 display: flex;
 flex-direction: column;
 overflow: hidden;
 pointer-events: none;
 visibility: hidden;
 `),n(`rail-placeholder`,`
 display: flex;
 flex-wrap: none;
 `),n(`button-placeholder`,`
 width: calc(1.75 * var(--n-rail-height));
 height: var(--n-rail-height);
 `),r(`base-loading`,`
 position: absolute;
 top: 50%;
 left: 50%;
 transform: translateX(-50%) translateY(-50%);
 font-size: calc(var(--n-button-width) - 4px);
 color: var(--n-loading-color);
 transition: color .3s var(--n-bezier);
 `,[y({left:`50%`,top:`50%`,originalTransform:`translateX(-50%) translateY(-50%)`})]),n(`checked, unchecked`,`
 transition: color .3s var(--n-bezier);
 color: var(--n-text-color);
 box-sizing: border-box;
 position: absolute;
 white-space: nowrap;
 top: 0;
 bottom: 0;
 display: flex;
 align-items: center;
 line-height: 1;
 `),n(`checked`,`
 right: 0;
 padding-right: calc(1.25 * var(--n-rail-height) - var(--n-offset));
 `),n(`unchecked`,`
 left: 0;
 justify-content: flex-end;
 padding-left: calc(1.25 * var(--n-rail-height) - var(--n-offset));
 `),t(`&:focus`,[n(`rail`,`
 box-shadow: var(--n-box-shadow-focus);
 `)]),s(`round`,[n(`rail`,`border-radius: calc(var(--n-rail-height) / 2);`,[n(`button`,`border-radius: calc(var(--n-button-height) / 2);`)])]),o(`disabled`,[o(`icon`,[s(`rubber-band`,[s(`pressed`,[n(`rail`,[n(`button`,`max-width: var(--n-button-width-pressed);`)])]),n(`rail`,[t(`&:active`,[n(`button`,`max-width: var(--n-button-width-pressed);`)])]),s(`active`,[s(`pressed`,[n(`rail`,[n(`button`,`left: calc(100% - var(--n-offset) - var(--n-button-width-pressed));`)])]),n(`rail`,[t(`&:active`,[n(`button`,`left: calc(100% - var(--n-offset) - var(--n-button-width-pressed));`)])])])])])]),s(`active`,[n(`rail`,[n(`button`,`left: calc(100% - var(--n-button-width) - var(--n-offset))`)])]),n(`rail`,`
 overflow: hidden;
 height: var(--n-rail-height);
 min-width: var(--n-rail-width);
 border-radius: var(--n-rail-border-radius);
 cursor: pointer;
 position: relative;
 transition:
 opacity .3s var(--n-bezier),
 background .3s var(--n-bezier),
 box-shadow .3s var(--n-bezier);
 background-color: var(--n-rail-color);
 `,[n(`button-icon`,`
 color: var(--n-icon-color);
 transition: color .3s var(--n-bezier);
 font-size: calc(var(--n-button-height) - 4px);
 position: absolute;
 left: 0;
 right: 0;
 top: 0;
 bottom: 0;
 display: flex;
 justify-content: center;
 align-items: center;
 line-height: 1;
 `,[y()]),n(`button`,`
 align-items: center; 
 top: var(--n-offset);
 left: var(--n-offset);
 height: var(--n-button-height);
 width: var(--n-button-width-pressed);
 max-width: var(--n-button-width);
 border-radius: var(--n-button-border-radius);
 background-color: var(--n-button-color);
 box-shadow: var(--n-button-box-shadow);
 box-sizing: border-box;
 cursor: inherit;
 content: "";
 position: absolute;
 transition:
 background-color .3s var(--n-bezier),
 left .3s var(--n-bezier),
 opacity .3s var(--n-bezier),
 max-width .3s var(--n-bezier),
 box-shadow .3s var(--n-bezier);
 `)]),s(`active`,[n(`rail`,`background-color: var(--n-rail-color-active);`)]),s(`loading`,[n(`rail`,`
 cursor: wait;
 `)]),s(`disabled`,[n(`rail`,`
 cursor: not-allowed;
 opacity: .5;
 `)])]),A=Object.assign(Object.assign({},d.props),{size:String,value:{type:[String,Number,Boolean],default:void 0},loading:Boolean,defaultValue:{type:[String,Number,Boolean],default:!1},disabled:{type:Boolean,default:void 0},round:{type:Boolean,default:!0},"onUpdate:value":[Function,Array],onUpdateValue:[Function,Array],checkedValue:{type:[String,Number,Boolean],default:!0},uncheckedValue:{type:[String,Number,Boolean],default:!1},railStyle:Function,rubberBand:{type:Boolean,default:!0},spinProps:Object,onChange:[Function,Array]}),j,M=h({name:`Switch`,props:A,slots:Object,setup(e){j===void 0&&(j=typeof CSS<`u`?CSS.supports!==void 0&&CSS.supports(`width`,`max(1px)`):!0);let{mergedClsPrefixRef:t,inlineThemeDisabled:n,mergedComponentPropsRef:r}=a(e),o=d(`Switch`,`-switch`,k,O,e,t),s=b(e,{mergedSize(t){return e.size===void 0?t?t.mergedSize.value:r?.value?.Switch?.size||`medium`:e.size}}),{mergedSizeRef:p,mergedDisabledRef:m}=s,h=f(e.defaultValue),g=l(e,`value`),_=T(g,h),y=u(()=>_.value===e.checkedValue),x=f(!1),S=f(!1),E=u(()=>{let{railStyle:t}=e;if(t)return t({focused:S.value,checked:y.value})});function D(t){let{"onUpdate:value":n,onChange:r,onUpdateValue:i}=e,{nTriggerFormInput:a,nTriggerFormChange:o}=s;n&&v(n,t),i&&v(i,t),r&&v(r,t),h.value=t,a(),o()}function A(){let{nTriggerFormFocus:e}=s;e()}function M(){let{nTriggerFormBlur:e}=s;e()}function N(){e.loading||m.value||(_.value===e.checkedValue?D(e.uncheckedValue):D(e.checkedValue))}function P(){S.value=!0,A()}function F(){S.value=!1,M(),x.value=!1}function I(t){e.loading||m.value||t.key===` `&&(_.value===e.checkedValue?D(e.uncheckedValue):D(e.checkedValue),x.value=!1)}function L(t){e.loading||m.value||t.key===` `&&(t.preventDefault(),x.value=!0)}let R=u(()=>{let{value:e}=p,{self:{opacityDisabled:t,railColor:n,railColorActive:r,buttonBoxShadow:i,buttonColor:a,boxShadowFocus:s,loadingColor:l,textColor:u,iconColor:d,[c(`buttonHeight`,e)]:f,[c(`buttonWidth`,e)]:m,[c(`buttonWidthPressed`,e)]:h,[c(`railHeight`,e)]:g,[c(`railWidth`,e)]:_,[c(`railBorderRadius`,e)]:v,[c(`buttonBorderRadius`,e)]:y},common:{cubicBezierEaseInOut:b}}=o.value,x,S,T;return j?(x=`calc((${g} - ${f}) / 2)`,S=`max(${g}, ${f})`,T=`max(${_}, calc(${_} + ${f} - ${g}))`):(x=w((C(g)-C(f))/2),S=w(Math.max(C(g),C(f))),T=C(g)>C(f)?_:w(C(_)+C(f)-C(g))),{"--n-bezier":b,"--n-button-border-radius":y,"--n-button-box-shadow":i,"--n-button-color":a,"--n-button-width":m,"--n-button-width-pressed":h,"--n-button-height":f,"--n-height":S,"--n-offset":x,"--n-opacity-disabled":t,"--n-rail-border-radius":v,"--n-rail-color":n,"--n-rail-color-active":r,"--n-rail-height":g,"--n-rail-width":_,"--n-width":T,"--n-box-shadow-focus":s,"--n-loading-color":l,"--n-text-color":u,"--n-icon-color":d}}),z=n?i(`switch`,u(()=>p.value[0]),R,e):void 0;return{handleClick:N,handleBlur:F,handleFocus:P,handleKeyup:I,handleKeydown:L,mergedRailStyle:E,pressed:x,mergedClsPrefix:t,mergedValue:_,checked:y,mergedDisabled:m,cssVars:n?void 0:R,themeClass:z?.themeClass,onRender:z?.onRender}},render(){let{mergedClsPrefix:e,mergedDisabled:t,checked:n,mergedRailStyle:r,onRender:i,$slots:a}=this;i?.();let{checked:o,unchecked:s,icon:c,"checked-icon":l,"unchecked-icon":u}=a,d=!(_(c)&&_(l)&&_(u));return m(`div`,{role:`switch`,"aria-checked":n,class:[`${e}-switch`,this.themeClass,d&&`${e}-switch--icon`,n&&`${e}-switch--active`,t&&`${e}-switch--disabled`,this.round&&`${e}-switch--round`,this.loading&&`${e}-switch--loading`,this.pressed&&`${e}-switch--pressed`,this.rubberBand&&`${e}-switch--rubber-band`],tabindex:this.mergedDisabled?void 0:0,style:this.cssVars,onClick:this.handleClick,onFocus:this.handleFocus,onBlur:this.handleBlur,onKeyup:this.handleKeyup,onKeydown:this.handleKeydown},m(`div`,{class:`${e}-switch__rail`,"aria-hidden":`true`,style:r},x(o,t=>x(s,n=>t||n?m(`div`,{"aria-hidden":!0,class:`${e}-switch__children-placeholder`},m(`div`,{class:`${e}-switch__rail-placeholder`},m(`div`,{class:`${e}-switch__button-placeholder`}),t),m(`div`,{class:`${e}-switch__rail-placeholder`},m(`div`,{class:`${e}-switch__button-placeholder`}),n)):null)),m(`div`,{class:`${e}-switch__button`},x(c,t=>x(l,n=>x(u,r=>m(g,null,{default:()=>this.loading?m(S,Object.assign({key:`loading`,clsPrefix:e,strokeWidth:20},this.spinProps)):this.checked&&(n||t)?m(`div`,{class:`${e}-switch__button-icon`,key:n?`checked-icon`:`icon`},n||t):!this.checked&&(r||t)?m(`div`,{class:`${e}-switch__button-icon`,key:r?`unchecked-icon`:`icon`},r||t):null})))),x(o,t=>t&&m(`div`,{key:`checked`,class:`${e}-switch__checked`},t)),x(s,t=>t&&m(`div`,{key:`unchecked`,class:`${e}-switch__unchecked`},t)))))}});export{M as t};