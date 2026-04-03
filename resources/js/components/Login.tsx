import { useState, useEffect } from 'react';
import { Hospital, Lock, User, Heart, Activity, Stethoscope } from 'lucide-react';

interface LoginProps {
  onLogin: (nip: string, password: string) => void;
  isLoading?: boolean;
  error?: string | null;
  devAutofill?: {
    nip?: string;
    password?: string;
  } | null;
}

export default function Login({ onLogin, isLoading = false, error, devAutofill = null }: LoginProps) {
  const [nip, setNip] = useState('');
  const [password, setPassword] = useState('');
  const [focusedInput, setFocusedInput] = useState<string | null>(null);

  // Auto-fill untuk development mode
  useEffect(() => {
    if (devAutofill?.nip && devAutofill?.password) {
      setNip(devAutofill.nip);
      setPassword(devAutofill.password);
      return;
    }

    const isDev = import.meta.env.VITE_APP_ENV === 'dev';
    if (isDev) {
      const devNip = import.meta.env.VITE_DEV_NIP || '';
      const devPassword = import.meta.env.VITE_DEV_PASSWORD || '';
      setNip(devNip);
      setPassword(devPassword);
    }
  }, [devAutofill]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (nip && password && !isLoading) {
      onLogin(nip, password);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-cyan-50 p-4 md:p-6 relative overflow-hidden">
      {/* Animated background elements */}
      <div className="absolute inset-0 overflow-hidden">
        {/* Animated gradient mesh background */}
        <div className="absolute inset-0 bg-gradient-to-br from-blue-50 via-cyan-50 to-teal-50" />
        
        {/* Floating medical icons with different animations */}
        <div className="absolute top-20 left-[10%] text-blue-300/40 animate-float-slow">
          <Heart className="w-16 h-16" />
        </div>
        <div className="absolute top-40 right-[15%] text-cyan-300/40 animate-float-medium">
          <Activity className="w-20 h-20" />
        </div>
        <div className="absolute bottom-32 left-[20%] text-teal-300/40 animate-float-fast">
          <Stethoscope className="w-14 h-14" />
        </div>
        <div className="absolute bottom-20 right-[10%] text-blue-300/40 animate-float-slow" style={{ animationDelay: '1s' }}>
          <Hospital className="w-12 h-12" />
        </div>
        <div className="absolute top-[60%] left-[5%] text-cyan-200/30 animate-float-medium" style={{ animationDelay: '2s' }}>
          <Heart className="w-10 h-10" />
        </div>
        <div className="absolute top-[30%] right-[5%] text-teal-200/30 animate-float-fast" style={{ animationDelay: '1.5s' }}>
          <Activity className="w-12 h-12" />
        </div>

        {/* Animated gradient orbs with movement */}
        <div className="absolute -top-40 -left-40 w-80 h-80 bg-gradient-to-br from-blue-300/30 to-cyan-300/30 rounded-full blur-3xl animate-blob" />
        <div className="absolute -bottom-40 -right-40 w-96 h-96 bg-gradient-to-br from-cyan-300/30 to-teal-300/30 rounded-full blur-3xl animate-blob" style={{ animationDelay: '2s' }} />
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-gradient-to-br from-cyan-200/20 to-blue-200/20 rounded-full blur-3xl animate-blob" style={{ animationDelay: '4s' }} />
        
        {/* Floating particles */}
        <div className="absolute top-[15%] left-[25%] w-3 h-3 bg-blue-400/50 rounded-full animate-float-particle" />
        <div className="absolute top-[45%] left-[15%] w-2 h-2 bg-cyan-400/50 rounded-full animate-float-particle" style={{ animationDelay: '1s' }} />
        <div className="absolute top-[70%] right-[20%] w-4 h-4 bg-teal-400/50 rounded-full animate-float-particle" style={{ animationDelay: '2s' }} />
        <div className="absolute top-[25%] right-[30%] w-2 h-2 bg-blue-400/50 rounded-full animate-float-particle" style={{ animationDelay: '3s' }} />
        <div className="absolute bottom-[40%] left-[35%] w-3 h-3 bg-cyan-400/50 rounded-full animate-float-particle" style={{ animationDelay: '1.5s' }} />
        
        {/* Animated lines */}
        <div className="absolute top-0 left-[20%] w-px h-full bg-gradient-to-b from-transparent via-blue-200/30 to-transparent animate-slide-down" />
        <div className="absolute top-0 left-[60%] w-px h-full bg-gradient-to-b from-transparent via-cyan-200/30 to-transparent animate-slide-down" style={{ animationDelay: '2s' }} />
        <div className="absolute top-0 right-[25%] w-px h-full bg-gradient-to-b from-transparent via-teal-200/30 to-transparent animate-slide-down" style={{ animationDelay: '4s' }} />
      </div>

      {/* Login Container */}
      <div className="w-full max-w-md relative z-10">
        {/* Logo and Title */}
        <div className="text-center mb-12 animate-fadeIn">
          <div className="inline-flex items-center justify-center w-24 h-24 bg-gradient-to-br from-blue-500 via-cyan-500 to-teal-500 rounded-full mb-6 shadow-2xl animate-float">
            <Hospital className="w-12 h-12 text-white" />
          </div>
          <h1 className="text-4xl font-bold bg-gradient-to-r from-blue-700 via-cyan-600 to-teal-600 bg-clip-text text-transparent mb-3">
            RS Citra Husada
          </h1>
          <p className="text-slate-500 text-lg">Single Sign-On Portal</p>
          {import.meta.env.VITE_APP_ENV === 'dev' && (
            <div className="mt-2 px-3 py-1 bg-orange-100 border border-orange-300 rounded-full text-orange-800 text-sm font-medium text-center">
              Development Mode - Auto-filled credentials
            </div>
          )}
          <div className="mt-4 h-1 w-20 bg-gradient-to-r from-blue-400 to-cyan-400 mx-auto rounded-full animate-pulse" />
        </div>

        {/* Login Form */}
        <div className="space-y-8">
          {/* NIP Input */}
          <div className="relative group">
            <label htmlFor="nip" className="block text-slate-600 font-medium mb-3 ml-1">
              NIP
            </label>
            <div className="relative">
              <div className={`absolute left-0 top-1/2 -translate-y-1/2 transition-all duration-300 ${
                focusedInput === 'nip' ? 'text-blue-500' : 'text-slate-400'
              }`}>
                <User className="w-5 h-5" />
              </div>
              <input
                type="text"
                id="nip"
                value={nip}
                onChange={(e) => setNip(e.target.value)}
                onFocus={() => setFocusedInput('nip')}
                onBlur={() => setFocusedInput(null)}
                placeholder="Masukkan NIP"
                className={`w-full pl-10 pr-4 py-4 bg-transparent border-b-2 border-slate-200 transition-all duration-300 outline-none text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:pl-12 ${
                  import.meta.env.VITE_APP_ENV === 'dev' && nip ? 'bg-orange-50/50' : ''
                }`}
                required
                disabled={isLoading}
              />
              <div className={`absolute bottom-0 left-0 h-0.5 bg-gradient-to-r from-blue-400 to-cyan-400 transition-all duration-300 ${
                focusedInput === 'nip' ? 'w-full' : 'w-0'
              }`} />
            </div>
          </div>

          {/* Password Input */}
          <div className="relative group">
            <label htmlFor="password" className="block text-slate-600 font-medium mb-3 ml-1">
              Password
            </label>
            <div className="relative">
              <div className={`absolute left-0 top-1/2 -translate-y-1/2 transition-all duration-300 ${
                focusedInput === 'password' ? 'text-blue-500' : 'text-slate-400'
              }`}>
                <Lock className="w-5 h-5" />
              </div>
              <input
                type="password"
                id="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                onFocus={() => setFocusedInput('password')}
                onBlur={() => setFocusedInput(null)}
                placeholder="Masukkan password"
                className={`w-full pl-10 pr-4 py-4 bg-transparent border-b-2 border-slate-200 transition-all duration-300 outline-none text-slate-700 placeholder:text-slate-400 focus:border-blue-500 focus:pl-12 ${
                  import.meta.env.VITE_APP_ENV === 'dev' && password ? 'bg-orange-50/50' : ''
                }`}
                required
                disabled={isLoading}
              />
              <div className={`absolute bottom-0 left-0 h-0.5 bg-gradient-to-r from-blue-400 to-cyan-400 transition-all duration-300 ${
                focusedInput === 'password' ? 'w-full' : 'w-0'
              }`} />
            </div>
          </div>

          {/* Login Button */}
          <button
            onClick={handleSubmit}
            disabled={isLoading}
            className="w-full relative group overflow-hidden bg-gradient-to-r from-blue-500 to-cyan-500 text-white py-4 rounded-full shadow-lg hover:shadow-2xl transition-all duration-300 mt-8 hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
          >
            <span className="relative z-10 font-semibold text-lg">
              {isLoading ? 'Logging in...' : 'Login'}
            </span>
            <div className="absolute inset-0 bg-gradient-to-r from-blue-400 to-cyan-400 opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
          </button>

          {/* Error Message */}
          {error && (
            <div className="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
              {error}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="mt-10 text-center">
          <p className="text-slate-400 text-sm">
            Sistem Informasi Terpadu RS Citra Husada
          </p>
          <div className="mt-4 flex items-center justify-center gap-2">
            <div className="w-2 h-2 bg-blue-400 rounded-full animate-ping" />
            <div className="w-2 h-2 bg-cyan-400 rounded-full animate-ping" style={{ animationDelay: '0.5s' }} />
            <div className="w-2 h-2 bg-blue-400 rounded-full animate-ping" style={{ animationDelay: '1s' }} />
          </div>
        </div>
      </div>

      <style>{`
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
      `}</style>
    </div>
  );
}