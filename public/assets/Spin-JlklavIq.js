import{$ as e,B as t,Ct as n,Nt as r,V as i,Zt as a,a as o,at as s,cn as c,d as l,dt as u,et as d,jt as f,o as p,rt as m}from"./_plugin-vue_export-helper-BRaiA-U2.js";import{a as h,t as g}from"./fade-in.cssr-DOjf-8Ff.js";import{Q as _,o as v}from"./index-D2BO3n0Q.js";var y=e([e(`@keyframes spin-rotate`,`
 from {
 transform: rotate(0);
 }
 to {
 transform: rotate(360deg);
 }
 `),d(`spin-container`,`
 position: relative;
 `,[d(`spin-body`,`
 position: absolute;
 top: 50%;
 left: 50%;
 transform: translateX(-50%) translateY(-50%);
 `,[g()])]),d(`spin-body`,`
 display: inline-flex;
 align-items: center;
 justify-content: center;
 flex-direction: column;
 `),d(`spin`,`
 display: inline-flex;
 height: var(--n-size);
 width: var(--n-size);
 font-size: var(--n-size);
 color: var(--n-color);
 `,[m(`rotate`,`
 animation: spin-rotate 2s linear infinite;
 `)]),d(`spin-description`,`
 display: inline-block;
 font-size: var(--n-font-size);
 color: var(--n-text-color);
 transition: color .3s var(--n-bezier);
 margin-top: 8px;
 `),d(`spin-content`,`
 opacity: 1;
 transition: opacity .3s var(--n-bezier);
 pointer-events: all;
 `,[m(`spinning`,`
 user-select: none;
 -webkit-user-select: none;
 pointer-events: none;
 opacity: var(--n-opacity-spinning);
 `)])]),b={small:20,medium:18,large:16},x=Object.assign(Object.assign(Object.assign({},l.props),{contentClass:String,contentStyle:[Object,String],description:String,size:{type:[String,Number],default:`medium`},show:{type:Boolean,default:!0},rotate:{type:Boolean,default:!0},spinning:{type:Boolean,validator:()=>!0,default:void 0},delay:Number}),p),S=f({name:`Spin`,props:x,slots:Object,setup(e){let{mergedClsPrefixRef:r,inlineThemeDisabled:o}=i(e),u=l(`Spin`,`-spin`,y,v,e,r),d=n(()=>{let{size:t}=e,{common:{cubicBezierEaseInOut:n},self:r}=u.value,{opacitySpinning:i,color:a,textColor:o}=r;return{"--n-bezier":n,"--n-opacity-spinning":i,"--n-size":typeof t==`number`?h(t):r[s(`size`,t)],"--n-color":a,"--n-text-color":o}}),f=o?t(`spin`,n(()=>{let{size:t}=e;return typeof t==`number`?String(t):t[0]}),d,e):void 0,p=_(e,[`spinning`,`show`]),m=c(!1);return a(t=>{let n;if(p.value){let{delay:r}=e;if(r){n=window.setTimeout(()=>{m.value=!0},r),t(()=>{clearTimeout(n)});return}}m.value=p.value}),{mergedClsPrefix:r,active:m,mergedStrokeWidth:n(()=>{let{strokeWidth:t}=e;if(t!==void 0)return t;let{size:n}=e;return b[typeof n==`number`?`medium`:n]}),cssVars:o?void 0:d,themeClass:f?.themeClass,onRender:f?.onRender}},render(){var e;let{$slots:t,mergedClsPrefix:n,description:i}=this,a=t.icon&&this.rotate,s=(i||t.description)&&r(`div`,{class:`${n}-spin-description`},i||t.description?.call(t)),c=t.icon?r(`div`,{class:[`${n}-spin-body`,this.themeClass]},r(`div`,{class:[`${n}-spin`,a&&`${n}-spin--rotate`],style:t.default?``:this.cssVars},t.icon()),s):r(`div`,{class:[`${n}-spin-body`,this.themeClass]},r(o,{clsPrefix:n,style:t.default?``:this.cssVars,stroke:this.stroke,"stroke-width":this.mergedStrokeWidth,radius:this.radius,scale:this.scale,class:`${n}-spin`}),s);return(e=this.onRender)==null||e.call(this),t.default?r(`div`,{class:[`${n}-spin-container`,this.themeClass],style:this.cssVars},r(`div`,{class:[`${n}-spin-content`,this.active&&`${n}-spin-content--spinning`,this.contentClass],style:this.contentStyle},t),r(u,{name:`fade-in-transition`},{default:()=>this.active?c:null})):c}});export{S as t};