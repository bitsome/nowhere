import{$ as e,At as t,B as n,Ct as r,Dt as i,Et as a,Gt as o,J as s,Nt as c,Qt as l,Tt as u,Ut as d,V as f,_n as p,a as m,at as h,c as g,cn as _,d as v,et as y,fn as b,gn as x,hn as S,i as C,it as w,jt as T,kt as E,mn as D,n as O,nt as k,on as A,qt as j,r as M,rt as N,s as P,t as F,wt as I,yt as L}from"./_plugin-vue_export-helper-xtfkLN2I.js";import{c as R,d as z,f as B}from"./light-F9vJ8mix.js";import{a as V,n as H}from"./fade-in.cssr-DyPg4UAa.js";import{n as ee}from"./Modal-C0YEl3cY.js";import{n as te,t as ne}from"./FormItem-SacMv6Kk.js";import{a as re,t as ie}from"./Button-CwpV6XVQ.js";import{t as U}from"./Input-CAn9mQsw.js";import{t as W}from"./Empty-DHtpkbMb.js";import{t as G}from"./Tag-BMUSraHz.js";import{t as K}from"./Alert-CM3264HV.js";import{t as q}from"./use-message-Dgk_uyP4.js";import{t as J}from"./Rate-D6bSbTzb.js";import{c as Y,l as ae,n as oe,r as X,rt as se,s as ce,st as le,t as ue}from"./index-Cxr_Uq9z.js";import{a as de,t as Z}from"./LevelBadge-Ckkt-V14.js";function fe(e){let{primaryColor:t,opacityDisabled:n,borderRadius:r,textColor3:i}=e;return Object.assign(Object.assign({},ae),{iconColor:i,textColor:`white`,loadingColor:t,opacityDisabled:n,railColor:`rgba(0, 0, 0, .14)`,railColorActive:t,buttonBoxShadow:`0 1px 4px 0 rgba(0, 0, 0, 0.3), inset 0 0 1px 0 rgba(0, 0, 0, 0.05)`,buttonColor:`#FFF`,railBorderRadiusSmall:r,railBorderRadiusMedium:r,railBorderRadiusLarge:r,buttonBorderRadiusSmall:r,buttonBorderRadiusMedium:r,buttonBorderRadiusLarge:r,boxShadowFocus:`0 0 0 2px ${s(t,{alpha:.2})}`})}var pe={name:`Switch`,common:C,self:fe},me=y(`switch`,`
 height: var(--n-height);
 min-width: var(--n-width);
 vertical-align: middle;
 user-select: none;
 -webkit-user-select: none;
 display: inline-flex;
 outline: none;
 justify-content: center;
 align-items: center;
`,[k(`children-placeholder`,`
 height: var(--n-rail-height);
 display: flex;
 flex-direction: column;
 overflow: hidden;
 pointer-events: none;
 visibility: hidden;
 `),k(`rail-placeholder`,`
 display: flex;
 flex-wrap: none;
 `),k(`button-placeholder`,`
 width: calc(1.75 * var(--n-rail-height));
 height: var(--n-rail-height);
 `),y(`base-loading`,`
 position: absolute;
 top: 50%;
 left: 50%;
 transform: translateX(-50%) translateY(-50%);
 font-size: calc(var(--n-button-width) - 4px);
 color: var(--n-loading-color);
 transition: color .3s var(--n-bezier);
 `,[P({left:`50%`,top:`50%`,originalTransform:`translateX(-50%) translateY(-50%)`})]),k(`checked, unchecked`,`
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
 `),k(`checked`,`
 right: 0;
 padding-right: calc(1.25 * var(--n-rail-height) - var(--n-offset));
 `),k(`unchecked`,`
 left: 0;
 justify-content: flex-end;
 padding-left: calc(1.25 * var(--n-rail-height) - var(--n-offset));
 `),e(`&:focus`,[k(`rail`,`
 box-shadow: var(--n-box-shadow-focus);
 `)]),N(`round`,[k(`rail`,`border-radius: calc(var(--n-rail-height) / 2);`,[k(`button`,`border-radius: calc(var(--n-button-height) / 2);`)])]),w(`disabled`,[w(`icon`,[N(`rubber-band`,[N(`pressed`,[k(`rail`,[k(`button`,`max-width: var(--n-button-width-pressed);`)])]),k(`rail`,[e(`&:active`,[k(`button`,`max-width: var(--n-button-width-pressed);`)])]),N(`active`,[N(`pressed`,[k(`rail`,[k(`button`,`left: calc(100% - var(--n-offset) - var(--n-button-width-pressed));`)])]),k(`rail`,[e(`&:active`,[k(`button`,`left: calc(100% - var(--n-offset) - var(--n-button-width-pressed));`)])])])])])]),N(`active`,[k(`rail`,[k(`button`,`left: calc(100% - var(--n-button-width) - var(--n-offset))`)])]),k(`rail`,`
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
 `,[k(`button-icon`,`
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
 `,[P()]),k(`button`,`
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
 `)]),N(`active`,[k(`rail`,`background-color: var(--n-rail-color-active);`)]),N(`loading`,[k(`rail`,`
 cursor: wait;
 `)]),N(`disabled`,[k(`rail`,`
 cursor: not-allowed;
 opacity: .5;
 `)])]),he=Object.assign(Object.assign({},v.props),{size:String,value:{type:[String,Number,Boolean],default:void 0},loading:Boolean,defaultValue:{type:[String,Number,Boolean],default:!1},disabled:{type:Boolean,default:void 0},round:{type:Boolean,default:!0},"onUpdate:value":[Function,Array],onUpdateValue:[Function,Array],checkedValue:{type:[String,Number,Boolean],default:!0},uncheckedValue:{type:[String,Number,Boolean],default:!1},railStyle:Function,rubberBand:{type:Boolean,default:!0},spinProps:Object,onChange:[Function,Array]}),Q,ge=T({name:`Switch`,props:he,slots:Object,setup(e){Q===void 0&&(Q=typeof CSS<`u`?CSS.supports!==void 0&&CSS.supports(`width`,`max(1px)`):!0);let{mergedClsPrefixRef:t,inlineThemeDisabled:i,mergedComponentPropsRef:a}=f(e),o=v(`Switch`,`-switch`,me,pe,e,t),s=re(e,{mergedSize(t){return e.size===void 0?t?t.mergedSize.value:a?.value?.Switch?.size||`medium`:e.size}}),{mergedSizeRef:c,mergedDisabledRef:l}=s,u=_(e.defaultValue),d=b(e,`value`),p=se(d,u),m=r(()=>p.value===e.checkedValue),g=_(!1),y=_(!1),x=r(()=>{let{railStyle:t}=e;if(t)return t({focused:y.value,checked:m.value})});function S(t){let{"onUpdate:value":n,onChange:r,onUpdateValue:i}=e,{nTriggerFormInput:a,nTriggerFormChange:o}=s;n&&B(n,t),i&&B(i,t),r&&B(r,t),u.value=t,a(),o()}function C(){let{nTriggerFormFocus:e}=s;e()}function w(){let{nTriggerFormBlur:e}=s;e()}function T(){e.loading||l.value||(p.value===e.checkedValue?S(e.uncheckedValue):S(e.checkedValue))}function E(){y.value=!0,C()}function D(){y.value=!1,w(),g.value=!1}function O(t){e.loading||l.value||t.key===` `&&(p.value===e.checkedValue?S(e.uncheckedValue):S(e.checkedValue),g.value=!1)}function k(t){e.loading||l.value||t.key===` `&&(t.preventDefault(),g.value=!0)}let A=r(()=>{let{value:e}=c,{self:{opacityDisabled:t,railColor:n,railColorActive:r,buttonBoxShadow:i,buttonColor:a,boxShadowFocus:s,loadingColor:l,textColor:u,iconColor:d,[h(`buttonHeight`,e)]:f,[h(`buttonWidth`,e)]:p,[h(`buttonWidthPressed`,e)]:m,[h(`railHeight`,e)]:g,[h(`railWidth`,e)]:_,[h(`railBorderRadius`,e)]:v,[h(`buttonBorderRadius`,e)]:y},common:{cubicBezierEaseInOut:b}}=o.value,x,S,C;return Q?(x=`calc((${g} - ${f}) / 2)`,S=`max(${g}, ${f})`,C=`max(${_}, calc(${_} + ${f} - ${g}))`):(x=V((H(g)-H(f))/2),S=V(Math.max(H(g),H(f))),C=H(g)>H(f)?_:V(H(_)+H(f)-H(g))),{"--n-bezier":b,"--n-button-border-radius":y,"--n-button-box-shadow":i,"--n-button-color":a,"--n-button-width":p,"--n-button-width-pressed":m,"--n-button-height":f,"--n-height":S,"--n-offset":x,"--n-opacity-disabled":t,"--n-rail-border-radius":v,"--n-rail-color":n,"--n-rail-color-active":r,"--n-rail-height":g,"--n-rail-width":_,"--n-width":C,"--n-box-shadow-focus":s,"--n-loading-color":l,"--n-text-color":u,"--n-icon-color":d}}),j=i?n(`switch`,r(()=>c.value[0]),A,e):void 0;return{handleClick:T,handleBlur:D,handleFocus:E,handleKeyup:O,handleKeydown:k,mergedRailStyle:x,pressed:g,mergedClsPrefix:t,mergedValue:p,checked:m,mergedDisabled:l,cssVars:i?void 0:A,themeClass:j?.themeClass,onRender:j?.onRender}},render(){let{mergedClsPrefix:e,mergedDisabled:t,checked:n,mergedRailStyle:r,onRender:i,$slots:a}=this;i?.();let{checked:o,unchecked:s,icon:l,"checked-icon":u,"unchecked-icon":d}=a,f=!(R(l)&&R(u)&&R(d));return c(`div`,{role:`switch`,"aria-checked":n,class:[`${e}-switch`,this.themeClass,f&&`${e}-switch--icon`,n&&`${e}-switch--active`,t&&`${e}-switch--disabled`,this.round&&`${e}-switch--round`,this.loading&&`${e}-switch--loading`,this.pressed&&`${e}-switch--pressed`,this.rubberBand&&`${e}-switch--rubber-band`],tabindex:this.mergedDisabled?void 0:0,style:this.cssVars,onClick:this.handleClick,onFocus:this.handleFocus,onBlur:this.handleBlur,onKeyup:this.handleKeyup,onKeydown:this.handleKeydown},c(`div`,{class:`${e}-switch__rail`,"aria-hidden":`true`,style:r},z(o,t=>z(s,n=>t||n?c(`div`,{"aria-hidden":!0,class:`${e}-switch__children-placeholder`},c(`div`,{class:`${e}-switch__rail-placeholder`},c(`div`,{class:`${e}-switch__button-placeholder`}),t),c(`div`,{class:`${e}-switch__rail-placeholder`},c(`div`,{class:`${e}-switch__button-placeholder`}),n)):null)),c(`div`,{class:`${e}-switch__button`},z(l,t=>z(u,n=>z(d,r=>c(g,null,{default:()=>this.loading?c(m,Object.assign({key:`loading`,clsPrefix:e,strokeWidth:20},this.spinProps)):this.checked&&(n||t)?c(`div`,{class:`${e}-switch__button-icon`,key:n?`checked-icon`:`icon`},n||t):!this.checked&&(r||t)?c(`div`,{class:`${e}-switch__button-icon`,key:r?`unchecked-icon`:`icon`},r||t):null})))),z(o,t=>t&&c(`div`,{key:`checked`,class:`${e}-switch__checked`},t)),z(s,t=>t&&c(`div`,{key:`unchecked`,class:`${e}-switch__unchecked`},t)))))}}),_e={class:`profile-hero`},ve={class:`profile-hero__avatar`},ye={class:`profile-hero__name-row`},be={class:`profile-hero__name`},xe={class:`profile-hero__meta`},Se={class:`profile-hero__meta`},Ce={class:`profile-hero__badges`},we={key:0,class:`verify-badge`,title:`차량 인증 완료`},Te={key:1,class:`verify-badge`,title:`면허 인증 완료`},Ee={key:2,class:`profile-stats`},De={class:`profile-stats__card`},Oe={class:`profile-stats__value`},ke={class:`profile-stats__card`},Ae={class:`profile-stats__value`},je={class:`profile-stats__card`},Me={key:1,class:`profile-review-list`},Ne={class:`profile-review__head`},Pe={class:`profile-review__author`},Fe=[`textContent`],Ie={class:`profile-review__time`},Le={class:`verify-row`},Re={class:`verify-row`},ze={class:`verify-row__label`},Be={key:0,class:`verify-row__done`},Ve={key:1,class:`verify-row__pending`},$={key:1,class:`verify-row__badge`},He={class:`verify-row`},Ue={class:`verify-row__label`},We={key:0,class:`verify-row__done`},Ge={key:1,class:`verify-row__pending`},Ke={key:1,class:`verify-row__badge`},qe={class:`level-head`},Je={class:`level-head__text`},Ye={class:`level-bar`},Xe={class:`level-hint`},Ze={key:0,class:`xp-events`},Qe=F({__name:`ProfileView`,setup(e){let n=ce(),s=le(),c=q(),f=_(!1),m=_(``),h=_(``),g=_(``),v=_(ue()),y=async e=>{if(e&&`Notification`in window&&Notification.permission==="default"&&!await oe()){v.value=!1,X(!1),c.error(`브라우저 알림 권한이 거부되었습니다. 브라우저 설정에서 허용해 주세요.`);return}X(e),c.success(e?`브라우저 알림이 켜졌습니다.`:`브라우저 알림이 꺼졌습니다.`)},b=_(null),C=A({name:``,phone:``}),w=r(()=>({"Super Admin":`최고 관리자`,Admin:`관리자`,Operator:`운영자`,Driver:`드라이버`})[n.user?.role]??n.user?.role??`-`),T=r(()=>n.user?.name?.charAt(0)??`N`),k=r(()=>n.user?.level??null),N=e=>(e??0).toLocaleString(),P=async e=>{m.value=e;try{await O.post(`/verification/request`,{[e]:!0}),c.success(`인증 신청이 관리자에게 전달되었습니다.`)}catch(e){c.error(M(e))}finally{m.value=``}};d(()=>{C.name=n.user?.name??``,C.phone=n.user?.phone??``,de(n.user?.id).then(({data:e})=>{b.value=e.data}).catch(()=>{})});let F=async()=>{if(!C.name.trim()){h.value=`이름을 입력해주세요.`,c.warning(`이름을 입력해주세요.`);return}f.value=!0,h.value=``,g.value=``;try{let{data:e}=await Y({name:C.name.trim(),phone:C.phone.trim()});n.user=e.data,g.value=`회원정보가 저장되었습니다.`,c.success(`프로필이 저장되었습니다.`)}catch(e){h.value=M(e,`저장에 실패했습니다.`),c.error(h.value)}finally{f.value=!1}},R=async()=>{await n.logout(),s.push({name:`login`})};return(e,r)=>{let c=K,d=G,_=ee,O=W,A=J,M=ge,z=ie,B=U,V=ne,H=te;return o(),i(`div`,null,[h.value?(o(),u(c,{key:0,type:`error`,"show-icon":!0,class:`profile-block`},{default:l(()=>[E(p(h.value),1)]),_:1})):a(``,!0),g.value?(o(),u(c,{key:1,type:`success`,"show-icon":!0,class:`profile-block`},{default:l(()=>[E(p(g.value),1)]),_:1})):a(``,!0),t(_,{bordered:!0,class:`profile-block`},{default:l(()=>[I(`div`,_e,[I(`span`,ve,p(T.value),1),I(`div`,null,[I(`div`,ye,[I(`strong`,be,p(D(n).user?.name),1),k.value?(o(),u(Z,{key:0,level:k.value.level,size:`sm`},null,8,[`level`])):a(``,!0),D(n).user?.is_vip?(o(),u(d,{key:1,size:`small`,round:``,type:`warning`},{default:l(()=>[...r[6]||=[E(`VIP`,-1)]]),_:1})):a(``,!0)]),I(`span`,xe,p(D(n).user?.email),1),I(`span`,Se,p(D(n).user?.phone),1),I(`div`,Ce,[D(n).user?.is_vehicle_verified?(o(),i(`span`,we,`차량 인증`)):a(``,!0),D(n).user?.is_license_verified?(o(),i(`span`,Te,`면허 인증`)):a(``,!0),t(d,{size:`small`,round:``},{default:l(()=>[E(p(w.value),1)]),_:1})])])])]),_:1}),b.value?(o(),i(`div`,Ee,[I(`div`,De,[r[8]||=I(`span`,{class:`profile-stats__label`},`완료 운행`,-1),I(`strong`,Oe,[E(p(b.value.stats.completed_orders),1),r[7]||=I(`small`,null,`건`,-1)])]),I(`div`,ke,[r[10]||=I(`span`,{class:`profile-stats__label`},`누적 매출`,-1),I(`strong`,Ae,[E(p(N(b.value.stats.total_revenue)),1),r[9]||=I(`small`,null,`원`,-1)])]),I(`div`,je,[r[11]||=I(`span`,{class:`profile-stats__label`},`받은 평점`,-1),I(`strong`,{class:S([`profile-stats__value`,{"profile-stats__value--muted":b.value.reviewSummary.count===0}])},[E(p(b.value.reviewSummary.count>0?b.value.reviewSummary.avg:`-`),1),I(`small`,null,p(b.value.reviewSummary.count>0?`점 / ${b.value.reviewSummary.count}개`:``),1)],2)])])):a(``,!0),b.value?(o(),u(_,{key:3,bordered:!0,class:`profile-block`},{default:l(()=>[r[12]||=I(`div`,{class:`verify-head`},[I(`strong`,null,`받은 리뷰`),I(`span`,{class:`verify-hint`},`완료된 운행 후 상대방이 남긴 리뷰입니다`)],-1),b.value.reviews.length?(o(),i(`div`,Me,[(o(!0),i(L,null,j(b.value.reviews,e=>(o(),i(`article`,{key:e.id,class:`profile-review`},[I(`div`,Ne,[I(`span`,Pe,p(e.reviewer?.name),1),t(A,{value:e.rating,readonly:``,size:`small`,color:`#ffa940`},null,8,[`value`])]),I(`p`,{class:`profile-review__content`,textContent:p(e.content)},null,8,Fe),I(`span`,Ie,p(e.created_at),1)]))),128))])):(o(),u(O,{key:0,description:`아직 받은 리뷰가 없습니다.`,"image-size":60,class:`profile-reviews-empty`}))]),_:1})):a(``,!0),t(_,{bordered:!0,class:`profile-block`},{default:l(()=>[r[14]||=I(`div`,{class:`verify-head`},[I(`strong`,null,`알림`),I(`span`,{class:`verify-hint`},`새 운행·채팅·알림이 도착하면 화면 상단에 데스크톱 알림으로 알려드립니다.`)],-1),I(`div`,Le,[r[13]||=I(`span`,{class:`verify-row__label`},`브라우저 알림`,-1),t(M,{value:v.value,"onUpdate:value":y},null,8,[`value`])])]),_:1}),t(_,{bordered:!0,class:`profile-block`},{default:l(()=>[r[19]||=I(`div`,{class:`verify-head`},[I(`strong`,null,`인증`),I(`span`,{class:`verify-hint`},`관리자 승인 후 마켓에서 인증 배지가 표시됩니다`)],-1),I(`div`,Re,[I(`span`,ze,[r[15]||=E(` 차량 인증 `,-1),D(n).user?.is_vehicle_verified?(o(),i(`span`,Be,`완료`)):(o(),i(`span`,Ve,`미인증`))]),D(n).user?.is_vehicle_verified?(o(),i(`span`,$,`✓`)):(o(),u(z,{key:0,size:`small`,type:`primary`,ghost:``,loading:m.value===`vehicle`,onClick:r[0]||=e=>P(`vehicle`)},{default:l(()=>[...r[16]||=[E(` 신청 `,-1)]]),_:1},8,[`loading`]))]),I(`div`,He,[I(`span`,Ue,[r[17]||=E(` 면허 인증 `,-1),D(n).user?.is_license_verified?(o(),i(`span`,We,`완료`)):(o(),i(`span`,Ge,`미인증`))]),D(n).user?.is_license_verified?(o(),i(`span`,Ke,`✓`)):(o(),u(z,{key:0,size:`small`,type:`primary`,ghost:``,loading:m.value===`license`,onClick:r[1]||=e=>P(`license`)},{default:l(()=>[...r[18]||=[E(` 신청 `,-1)]]),_:1},8,[`loading`]))])]),_:1}),k.value?(o(),u(_,{key:4,bordered:!0,class:`profile-block`},{default:l(()=>[I(`div`,qe,[t(Z,{level:k.value.level,size:`lg`},null,8,[`level`]),I(`div`,Je,[I(`strong`,null,p(k.value.title),1),I(`span`,null,`누적 XP `+p(N(D(n).user?.xp??0))+`점`,1)])]),I(`div`,Ye,[I(`div`,{class:`level-bar__fill`,style:x({width:`${k.value.progress}%`})},null,4)]),I(`p`,Xe,[k.value.next_xp===null?(o(),i(L,{key:1},[E(` 최고 레벨에 도달했습니다! `)],64)):(o(),i(L,{key:0},[r[20]||=E(` 다음 레벨까지 `,-1),I(`strong`,null,p(N(k.value.next_xp-(D(n).user?.xp??0)))+`점`,1),E(` 남았습니다 (`+p(k.value.min_xp)+` → `+p(k.value.next_xp)+`점). `,1)],64))]),D(n).user?.recent_xp_events?.length?(o(),i(`div`,Ze,[(o(!0),i(L,null,j(D(n).user.recent_xp_events,e=>(o(),i(`div`,{key:e.created_at,class:`xp-event`},[I(`span`,null,p(e.label),1),I(`strong`,null,`+`+p(e.xp),1)]))),128))])):a(``,!0)]),_:1})):a(``,!0),t(_,{bordered:!0,class:`profile-block`},{default:l(()=>[I(`button`,{type:`button`,class:`community-entry`,onClick:r[2]||=e=>D(s).push({name:`community`})},[...r[21]||=[I(`span`,{class:`community-entry__icon`},[I(`svg`,{viewBox:`0 0 24 24`,fill:`none`,stroke:`currentColor`,"stroke-width":`1.8`},[I(`circle`,{cx:`9`,cy:`8`,r:`3.5`}),I(`path`,{d:`M2.5 20c.8-3.4 3.4-5 6.5-5s5.7 1.6 6.5 5`}),I(`circle`,{cx:`17.5`,cy:`9`,r:`2.5`}),I(`path`,{d:`M15.5 15.2c2.6.2 4.6 1.5 5.5 4.3`})])],-1),I(`span`,{class:`community-entry__text`},[I(`strong`,null,`유저 커뮤니티`),I(`small`,null,`드라이버·운영진과 일상을 공유하는 피드`)],-1),I(`span`,{class:`community-entry__arrow`},`›`,-1)]]),I(`button`,{type:`button`,class:`community-entry`,onClick:r[3]||=e=>D(s).push({name:`user-page`,params:{id:D(n).user?.id}})},[...r[22]||=[I(`span`,{class:`community-entry__icon`},[I(`svg`,{viewBox:`0 0 24 24`,fill:`none`,stroke:`currentColor`,"stroke-width":`1.8`},[I(`path`,{d:`M4 4h16v16H4z`}),I(`path`,{d:`M8 9h8M8 13h8M8 17h5`})])],-1),I(`span`,{class:`community-entry__text`},[I(`strong`,null,`내가 올린 글`),I(`small`,null,`내 프로필·글·운행을 다른 유저에게 보여주는 공개 페이지`)],-1),I(`span`,{class:`community-entry__arrow`},`›`,-1)]])]),_:1}),t(_,{bordered:!0,class:`profile-block`,title:`프로필 수정`},{default:l(()=>[t(H,{"label-placement":`top`,"label-width":`auto`},{default:l(()=>[t(V,{label:`이름`,required:``},{default:l(()=>[t(B,{value:C.name,"onUpdate:value":r[4]||=e=>C.name=e,placeholder:`이름`},null,8,[`value`])]),_:1}),t(V,{label:`연락처`},{default:l(()=>[t(B,{value:C.phone,"onUpdate:value":r[5]||=e=>C.phone=e,placeholder:`예) 010-1234-5678`},null,8,[`value`])]),_:1}),t(V,{label:`이메일`},{default:l(()=>[t(B,{value:D(n).user?.email,disabled:``},null,8,[`value`])]),_:1})]),_:1}),t(z,{type:`primary`,size:`large`,loading:f.value,onClick:F},{default:l(()=>[...r[23]||=[E(` 저장 `,-1)]]),_:1},8,[`loading`])]),_:1}),t(z,{type:`error`,tertiary:``,size:`large`,block:``,class:`profile-logout`,onClick:R},{default:l(()=>[...r[24]||=[E(` 로그아웃 `,-1)]]),_:1})])}}},[[`__scopeId`,`data-v-347e7304`]]);export{Qe as default};