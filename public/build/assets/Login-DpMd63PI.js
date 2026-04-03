import{r as i,j as a,u as v,a as x}from"./app-DjujZ4gR.js";import{c as f,H as b,U as w,u as j}from"./useAuth-Dwz_zI0s.js";/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const N=[["path",{d:"M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2",key:"169zse"}]],h=f("activity",N);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const k=[["path",{d:"M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5",key:"mvr1a0"}]],y=f("heart",k);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const D=[["rect",{width:"18",height:"11",x:"3",y:"11",rx:"2",ry:"2",key:"1w4ew1"}],["path",{d:"M7 11V7a5 5 0 0 1 10 0v4",key:"fwvmzm"}]],S=f("lock",D);/**
 * @license lucide-react v1.7.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const Y=[["path",{d:"M11 2v2",key:"1539x4"}],["path",{d:"M5 2v2",key:"1yf1q8"}],["path",{d:"M5 3H4a2 2 0 0 0-2 2v4a6 6 0 0 0 12 0V5a2 2 0 0 0-2-2h-1",key:"rb5t3r"}],["path",{d:"M8 15a6 6 0 0 0 12 0v-3",key:"x18d4x"}],["circle",{cx:"20",cy:"10",r:"2",key:"ts1r5v"}]],_=f("stethoscope",Y);function M({onLogin:d,isLoading:e=!1,error:c,devAutofill:t=null}){const[s,m]=i.useState(""),[o,u]=i.useState(""),[l,n]=i.useState(null);i.useEffect(()=>{if(t?.nip&&t?.password){m(t.nip),u(t.password);return}},[t]);const p=r=>{r.preventDefault(),s&&o&&!e&&d(s,o)};return a.jsxs("div",{className:"min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-cyan-50 p-4 md:p-6 relative overflow-hidden",children:[a.jsxs("div",{className:"absolute inset-0 overflow-hidden",children:[a.jsx("div",{className:"absolute inset-0 bg-gradient-to-br from-blue-50 via-cyan-50 to-teal-50"}),a.jsx("div",{className:"absolute top-20 left-[10%] text-blue-300/40 animate-float-slow",children:a.jsx(y,{className:"w-16 h-16"})}),a.jsx("div",{className:"absolute top-40 right-[15%] text-cyan-300/40 animate-float-medium",children:a.jsx(h,{className:"w-20 h-20"})}),a.jsx("div",{className:"absolute bottom-32 left-[20%] text-teal-300/40 animate-float-fast",children:a.jsx(_,{className:"w-14 h-14"})}),a.jsx("div",{className:"absolute bottom-20 right-[10%] text-blue-300/40 animate-float-slow",style:{animationDelay:"1s"},children:a.jsx(b,{className:"w-12 h-12"})}),a.jsx("div",{className:"absolute top-[60%] left-[5%] text-cyan-200/30 animate-float-medium",style:{animationDelay:"2s"},children:a.jsx(y,{className:"w-10 h-10"})}),a.jsx("div",{className:"absolute top-[30%] right-[5%] text-teal-200/30 animate-float-fast",style:{animationDelay:"1.5s"},children:a.jsx(h,{className:"w-12 h-12"})}),a.jsx("div",{className:"absolute -top-40 -left-40 w-80 h-80 bg-gradient-to-br from-blue-300/30 to-cyan-300/30 rounded-full blur-3xl animate-blob"}),a.jsx("div",{className:"absolute -bottom-40 -right-40 w-96 h-96 bg-gradient-to-br from-cyan-300/30 to-teal-300/30 rounded-full blur-3xl animate-blob",style:{animationDelay:"2s"}}),a.jsx("div",{className:"absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-gradient-to-br from-cyan-200/20 to-blue-200/20 rounded-full blur-3xl animate-blob",style:{animationDelay:"4s"}}),a.jsx("div",{className:"absolute top-[15%] left-[25%] w-3 h-3 bg-blue-400/50 rounded-full animate-float-particle"}),a.jsx("div",{className:"absolute top-[45%] left-[15%] w-2 h-2 bg-cyan-400/50 rounded-full animate-float-particle",style:{animationDelay:"1s"}}),a.jsx("div",{className:"absolute top-[70%] right-[20%] w-4 h-4 bg-teal-400/50 rounded-full animate-float-particle",style:{animationDelay:"2s"}}),a.jsx("div",{className:"absolute top-[25%] right-[30%] w-2 h-2 bg-blue-400/50 rounded-full animate-float-particle",style:{animationDelay:"3s"}}),a.jsx("div",{className:"absolute bottom-[40%] left-[35%] w-3 h-3 bg-cyan-400/50 rounded-full animate-float-particle",style:{animationDelay:"1.5s"}}),a.jsx("div",{className:"absolute top-0 left-[20%] w-px h-full bg-gradient-to-b from-transparent via-blue-200/30 to-transparent animate-slide-down"}),a.jsx("div",{className:"absolute top-0 left-[60%] w-px h-full bg-gradient-to-b from-transparent via-cyan-200/30 to-transparent animate-slide-down",style:{animationDelay:"2s"}}),a.jsx("div",{className:"absolute top-0 right-[25%] w-px h-full bg-gradient-to-b from-transparent via-teal-200/30 to-transparent animate-slide-down",style:{animationDelay:"4s"}})]}),a.jsxs("div",{className:"w-full max-w-md relative z-10",children:[a.jsxs("div",{className:"text-center mb-12 animate-fadeIn",children:[a.jsx("div",{className:"inline-flex items-center justify-center w-24 h-24 bg-gradient-to-br from-blue-500 via-cyan-500 to-teal-500 rounded-full mb-6 shadow-2xl animate-float",children:a.jsx(b,{className:"w-12 h-12 text-white"})}),a.jsx("h1",{className:"text-4xl font-bold bg-gradient-to-r from-blue-700 via-cyan-600 to-teal-600 bg-clip-text text-transparent mb-3",children:"RS Citra Husada"}),a.jsx("p",{className:"text-slate-500 text-lg",children:"Single Sign-On Portal"}),!1,a.jsx("div",{className:"mt-4 h-1 w-20 bg-gradient-to-r from-blue-400 to-cyan-400 mx-auto rounded-full animate-pulse"})]}),a.jsxs("div",{className:"space-y-8",children:[a.jsxs("div",{className:"relative group",children:[a.jsx("label",{htmlFor:"nip",className:"block text-slate-600 font-medium mb-3 ml-1",children:"NIP"}),a.jsxs("div",{className:"relative",children:[a.jsx("div",{className:`absolute left-0 top-1/2 -translate-y-1/2 transition-all duration-300 ${l==="nip"?"text-blue-500":"text-slate-400"}`,children:a.jsx(w,{className:"w-5 h-5"})}),a.jsx("input",{type:"text",id:"nip",value:s,onChange:r=>m(r.target.value),onFocus:()=>n("nip"),onBlur:()=>n(null),placeholder:"Masukkan NIP",className:"w-full pl-10 pr-4 py-4 bg-transparent border-b-2 border-slate-200 transition-all duration-300 outline-none text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:pl-12 ",required:!0,disabled:e}),a.jsx("div",{className:`absolute bottom-0 left-0 h-0.5 bg-gradient-to-r from-blue-400 to-cyan-400 transition-all duration-300 ${l==="nip"?"w-full":"w-0"}`})]})]}),a.jsxs("div",{className:"relative group",children:[a.jsx("label",{htmlFor:"password",className:"block text-slate-600 font-medium mb-3 ml-1",children:"Password"}),a.jsxs("div",{className:"relative",children:[a.jsx("div",{className:`absolute left-0 top-1/2 -translate-y-1/2 transition-all duration-300 ${l==="password"?"text-blue-500":"text-slate-400"}`,children:a.jsx(S,{className:"w-5 h-5"})}),a.jsx("input",{type:"password",id:"password",value:o,onChange:r=>u(r.target.value),onFocus:()=>n("password"),onBlur:()=>n(null),placeholder:"Masukkan password",className:"w-full pl-10 pr-4 py-4 bg-transparent border-b-2 border-slate-200 transition-all duration-300 outline-none text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:pl-12 ",required:!0,disabled:e}),a.jsx("div",{className:`absolute bottom-0 left-0 h-0.5 bg-gradient-to-r from-blue-400 to-cyan-400 transition-all duration-300 ${l==="password"?"w-full":"w-0"}`})]})]}),a.jsxs("button",{onClick:p,disabled:e,className:"w-full relative group overflow-hidden bg-gradient-to-r from-blue-500 to-cyan-500 text-white py-4 rounded-full shadow-lg hover:shadow-2xl transition-all duration-300 mt-8 hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100",children:[a.jsx("span",{className:"relative z-10 font-semibold text-lg",children:e?"Logging in...":"Login"}),a.jsx("div",{className:"absolute inset-0 bg-gradient-to-r from-blue-400 to-cyan-400 opacity-0 group-hover:opacity-100 transition-opacity duration-300"})]}),c&&a.jsx("div",{className:"mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm",children:c})]}),a.jsxs("div",{className:"mt-10 text-center",children:[a.jsx("p",{className:"text-slate-400 text-sm",children:"Sistem Informasi Terpadu RS Citra Husada"}),a.jsxs("div",{className:"mt-4 flex items-center justify-center gap-2",children:[a.jsx("div",{className:"w-2 h-2 bg-blue-400 rounded-full animate-ping"}),a.jsx("div",{className:"w-2 h-2 bg-cyan-400 rounded-full animate-ping",style:{animationDelay:"0.5s"}}),a.jsx("div",{className:"w-2 h-2 bg-blue-400 rounded-full animate-ping",style:{animationDelay:"1s"}})]})]})]}),a.jsx("style",{children:`
        @keyframes fadeIn {
          from {
            opacity: 0;
            transform: translateY(-20px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }

        @keyframes float {
          0%, 100% {
            transform: translateY(0px);
          }
          50% {
            transform: translateY(-10px);
          }
        }

        @keyframes floatSlow {
          0%, 100% {
            transform: translateY(0px) translateX(0px) rotate(0deg);
          }
          33% {
            transform: translateY(-20px) translateX(10px) rotate(5deg);
          }
          66% {
            transform: translateY(-10px) translateX(-10px) rotate(-5deg);
          }
        }

        @keyframes floatMedium {
          0%, 100% {
            transform: translateY(0px) translateX(0px) rotate(0deg);
          }
          50% {
            transform: translateY(-30px) translateX(15px) rotate(10deg);
          }
        }

        @keyframes floatFast {
          0%, 100% {
            transform: translateY(0px) translateX(0px) scale(1);
          }
          25% {
            transform: translateY(-15px) translateX(10px) scale(1.1);
          }
          75% {
            transform: translateY(-25px) translateX(-10px) scale(0.9);
          }
        }

        @keyframes blob {
          0%, 100% {
            transform: translate(0, 0) scale(1);
          }
          33% {
            transform: translate(30px, -50px) scale(1.1);
          }
          66% {
            transform: translate(-20px, 20px) scale(0.9);
          }
        }

        @keyframes floatParticle {
          0% {
            transform: translateY(0) translateX(0) scale(1);
            opacity: 0;
          }
          10% {
            opacity: 1;
          }
          90% {
            opacity: 1;
          }
          100% {
            transform: translateY(-100vh) translateX(20px) scale(0.5);
            opacity: 0;
          }
        }

        @keyframes slideDown {
          0% {
            transform: translateY(-100%);
          }
          100% {
            transform: translateY(100%);
          }
        }

        .animate-fadeIn {
          animation: fadeIn 0.8s ease-out;
        }

        .animate-float {
          animation: float 3s ease-in-out infinite;
        }

        .animate-float-slow {
          animation: floatSlow 8s ease-in-out infinite;
        }

        .animate-float-medium {
          animation: floatMedium 6s ease-in-out infinite;
        }

        .animate-float-fast {
          animation: floatFast 4s ease-in-out infinite;
        }

        .animate-blob {
          animation: blob 10s ease-in-out infinite;
        }

        .animate-float-particle {
          animation: floatParticle 15s ease-in-out infinite;
        }

        .animate-slide-down {
          animation: slideDown 8s linear infinite;
        }
      `})]})}function g(){const{isAuthenticated:d,isLoading:e,error:c}=j(),t=v(),s=t.props.auth??{},m=t.props?.errors?.nip||t.props?.errors?.password||null,o=t.props?.devAutofill??null;i.useEffect(()=>{(s?.user||d)&&x.visit("/dashboard")},[d,s?.user]);const u=async(l,n)=>{try{await x.post("/login",{nip:l,password:n})}catch{}};return a.jsx(M,{onLogin:u,isLoading:e,error:m||c,devAutofill:o})}const I=Object.freeze(Object.defineProperty({__proto__:null,default:g},Symbol.toStringTag,{value:"Module"})),L=Object.freeze(Object.defineProperty({__proto__:null,default:g},Symbol.toStringTag,{value:"Module"}));export{I as L,L as a};
