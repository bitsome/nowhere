import{$ as e,B as t,Dt as n,Ft as r,Lt as i,V as a,a as o,at as s,d as c,et as l,fn as u,ht as d,o as f,rt as p,tn as m}from"./_plugin-vue_export-helper-YnU260iM.js";import{a as h,t as g}from"./fade-in.cssr-DfpcyV2R.js";import{U as _,o as v}from"./index-BX0gzByF.js";var y=e([e(`@keyframes spin-rotate`,`
 from {
 transform: rotate(0);
 }
 to {
 transform: rotate(360deg);
 }
 `),l(`spin-container`,`
 position: relative;
 `,[l(`spin-body`,`
 position: absolute;
 top: 50%;
 left: 50%;
 transform: translateX(-50%) translateY(-50%);
 `,[g()])]),l(`spin-body`,`
 display: inline-flex;
 align-items: center;
 justify-content: center;
 flex-direction: column;
 `),l(`spin`,`
 display: inline-flex;
 height: var(--n-size);
 width: var(--n-size);
 font-size: var(--n-size);
 color: var(--n-color);
 `,[p(`rotate`,`
 animation: spin-rotate 2s linear infinite;
 `)]),l(`spin-description`,`
 display: inline-block;
 font-size: var(--n-font-size);
 color: var(--n-text-color);
 transition: color .3s var(--n-bezier);
 margin-top: 8px;
 `),l(`spin-content`,`
 opacity: 1;
 transition: opacity .3s var(--n-bezier);
 pointer-events: all;
 `,[p(`spinning`,`
 user-select: none;
 -webkit-user-select: none;
 pointer-events: none;
 opacity: var(--n-opacity-spinning);
 `)])]),b={small:20,medium:18,large:16},x=Object.assign(Object.assign(Object.assign({},c.props),{contentClass:String,contentStyle:[Object,String],description:String,size:{type:[String,Number],default:`medium`},show:{type:Boolean,default:!0},rotate:{type:Boolean,default:!0},spinning:{type:Boolean,validator:()=>!0,default:void 0},delay:Number}),f),S=r({name:`Spin`,props:x,slots:Object,setup(e){let{mergedClsPrefixRef:r,inlineThemeDisabled:i}=a(e),o=c(`Spin`,`-spin`,y,v,e,r),l=n(()=>{let{size:t}=e,{common:{cubicBezierEaseInOut:n},self:r}=o.value,{opacitySpinning:i,color:a,textColor:c}=r;return{"--n-bezier":n,"--n-opacity-spinning":i,"--n-size":typeof t==`number`?h(t):r[s(`size`,t)],"--n-color":a,"--n-text-color":c}}),d=i?t(`spin`,n(()=>{let{size:t}=e;return typeof t==`number`?String(t):t[0]}),l,e):void 0,f=_(e,[`spinning`,`show`]),p=u(!1);return m(t=>{let n;if(f.value){let{delay:r}=e;if(r){n=window.setTimeout(()=>{p.value=!0},r),t(()=>{clearTimeout(n)});return}}p.value=f.value}),{mergedClsPrefix:r,active:p,mergedStrokeWidth:n(()=>{let{strokeWidth:t}=e;if(t!==void 0)return t;let{size:n}=e;return b[typeof n==`number`?`medium`:n]}),cssVars:i?void 0:l,themeClass:d?.themeClass,onRender:d?.onRender}},render(){var e;let{$slots:t,mergedClsPrefix:n,description:r}=this,a=t.icon&&this.rotate,s=(r||t.description)&&i(`div`,{class:`${n}-spin-description`},r||t.description?.call(t)),c=t.icon?i(`div`,{class:[`${n}-spin-body`,this.themeClass]},i(`div`,{class:[`${n}-spin`,a&&`${n}-spin--rotate`],style:t.default?``:this.cssVars},t.icon()),s):i(`div`,{class:[`${n}-spin-body`,this.themeClass]},i(o,{clsPrefix:n,style:t.default?``:this.cssVars,stroke:this.stroke,"stroke-width":this.mergedStrokeWidth,radius:this.radius,scale:this.scale,class:`${n}-spin`}),s);return(e=this.onRender)==null||e.call(this),t.default?i(`div`,{class:[`${n}-spin-container`,this.themeClass],style:this.cssVars},i(`div`,{class:[`${n}-spin-content`,this.active&&`${n}-spin-content--spinning`,this.contentClass],style:this.contentStyle},t),i(d,{name:`fade-in-transition`},{default:()=>this.active?c:null})):c}});export{S as t};