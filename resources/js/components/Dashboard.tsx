import React, { useState, useEffect } from 'react';
import {
  LogOut,
  Hospital,
  Pill,
  TestTube,
  FileText,
  Users,
  ShieldCheck,
  CircleAlert,
  Utensils,
  Settings,
  User,
  X
} from 'lucide-react';
import type { User as UserType, Application, UserApplication } from '../types';
import { useAuth } from '../hooks/useAuth';
import { dashboardService } from '../services/dashboardService';
import { ssoService } from '../services/ssoService';

interface DashboardProps {
  user: UserType;
  applications?: Array<{
    app_key: string;
    name: string;
    description: string;
    app_url?: string;
    enabled: boolean;
    logo_url?: string | null;
  }>;
  accessProfiles?: Array<{
    id: number;
    slug: string;
    name: string;
    description?: string;
    is_system: boolean;
    is_active: boolean;
    applications_count: number;
    applications: Array<{
      id: number;
      app_key: string;
      name: string;
      description?: string;
      enabled: boolean;
      logo_url?: string;
      app_url?: string;
      redirect_uris?: string[];
      role: {
        id: number;
        slug: string;
        name: string;
        description?: string;
      };
    }>;
  }>;
}

interface ApplicationWithIcon extends Application {
  icon: React.ElementType;
  gradient: string;
  isOnline: boolean;
  userRole?: string;
}

// Enterprise Style Modal Content (Clean, Minimal, Professional)
function ModalContent({ user, nip, logout, onClose, isMobile = false, accessProfiles = [] }: {
  user: UserType;
  nip: string;
  logout: () => void;
  onClose: () => void;
  isMobile?: boolean;
  accessProfiles?: Array<{
    id: number;
    slug: string;
    name: string;
    description?: string;
    is_system: boolean;
    is_active: boolean;
    applications_count: number;
    applications: Array<{
      id: number;
      app_key: string;
      name: string;
      description?: string;
      enabled: boolean;
      logo_url?: string;
      app_url?: string;
      redirect_uris?: string[];
      role: {
        id: number;
        slug: string;
        name: string;
        description?: string;
      };
    }>;
  }>;
}) {
  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h2 className="text-base font-semibold text-gray-900">Info Akun</h2>
        <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
          <X className="w-5 h-5" />
        </button>
      </div>

      {/* Profile */}
      <div className="px-5 py-4 flex items-center gap-3">
        <div className="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center">
          <User className="w-5 h-5 text-gray-600" />
        </div>
        <div>
          <p className="font-medium text-gray-900">{user?.name || 'User'}</p>
          <p className="text-sm text-gray-500">{nip}</p>
        </div>
      </div>

      <div className="border-t border-gray-100" />

      {/* Details */}
      <div className={`px-5 py-4 space-y-3 text-sm ${isMobile ? 'flex-1 overflow-y-auto' : ''}`}>
        <div className="flex justify-between items-center">
          <span className="text-gray-500">Status</span>
          <span className="text-xs px-2 py-1 rounded-full bg-emerald-100 text-emerald-600 font-medium">
            Active
          </span>
        </div>

        {/* Access Profiles Section */}
        {accessProfiles && accessProfiles.length > 0 && (
          <div className="pt-4 border-t border-gray-100 space-y-3">
            <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide">
              Profil Akses
            </h3>

            {accessProfiles.map((profile) => (
              <div key={profile.id} className="space-y-2 pb-3 border-b border-gray-100 last:border-0">
                <div className="flex items-start justify-between gap-2">
                  <div className="flex-1">
                    <p className="text-sm font-semibold text-gray-900">
                      {profile.name}
                    </p>
                    {profile.description && (
                      <p className="text-xs text-gray-500 mt-1">
                        {profile.description}
                      </p>
                    )}
                  </div>
                  {profile.is_system && (
                    <span className="text-xs px-2 py-1 rounded bg-amber-100 text-amber-700 font-medium flex-shrink-0">
                      System
                    </span>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}

        {/* No access profiles message */}
        {(!accessProfiles || accessProfiles.length === 0) && (
          <div className="pt-4 border-t border-gray-100">
            <p className="text-xs text-gray-500 italic">
              Tidak memiliki akses profil. Hubungi administrator untuk diberikan akses.
            </p>
          </div>
        )}
      </div>

      {/* Actions */}
      <div className="p-4 border-t border-gray-100 space-y-2">
        <button
          onClick={() => ssoService.redirectToAdminPanel()}
          className="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2.5 rounded-lg flex items-center justify-center gap-2"
        >
          <Settings className="w-4 h-4" />
          Admin Panel
        </button>

        <button
          onClick={logout}
          className="w-full text-sm text-red-600 hover:bg-red-50 py-2.5 rounded-lg flex items-center justify-center gap-2"
        >
          <LogOut className="w-4 h-4" />
          Keluar
        </button>
      </div>
    </div>
  );
}


export default function Dashboard({ user, applications: appsFromProps = [], accessProfiles = [] }: DashboardProps) {
  const { logout } = useAuth();
  const [showInfoModal, setShowInfoModal] = useState(false);
  const [applications, setApplications] = useState<ApplicationWithIcon[]>([]);
  const [loading, setLoading] = useState(true);

  // Map of app names to icons and gradients
  const appConfig: Record<string, { icon: React.ElementType; gradient: string }> = {
    'Application Control-Client': { icon: ShieldCheck, gradient: 'from-blue-500 to-blue-600' },
    'Incident Reporting System': { icon: CircleAlert, gradient: 'from-orange-500 to-orange-600' },
    'Pharmacy Management System': { icon: Pill, gradient: 'from-emerald-500 to-emerald-600' },
    'SIMGIZI - Sistem Informasi Manajemen': { icon: Utensils, gradient: 'from-teal-500 to-teal-600' },
    'Tamasudeva - Eticom Management Unit': { icon: Hospital, gradient: 'from-purple-500 to-purple-600' },
    'Laboratorium Klinik': { icon: TestTube, gradient: 'from-indigo-500 to-indigo-600' },
    'Rekam Medis Elektronik': { icon: FileText, gradient: 'from-cyan-500 to-cyan-600' },
    'Sistem Antrian Pasien': { icon: Users, gradient: 'from-pink-500 to-pink-600' },
  };

  useEffect(() => {
    const processApplications = async () => {
      try {
        // In Inertia, use applications from props directly
        // No need for API fallback - DashboardController already handles access control
        // console.log('📋 [Dashboard-Inertia] Processing applications from props...');
        // console.log('📋 [Dashboard-Inertia] appsFromProps.length:', appsFromProps.length);
        // console.log('📋 [Dashboard-Inertia] appsFromProps data:', appsFromProps);

        const appsList = appsFromProps; // Use props directly, no API fallback
        // console.log('📊 [Dashboard-Inertia] Using apps from props:', appsList);
        // console.log('📊 [Dashboard-Inertia] appsList.length:', appsList.length);

        const appsWithIcons = appsList.map((app) => {
          // Determine icon and gradient based on app name
          const config = appConfig[app.name] || {
            icon: Hospital,
            gradient: 'from-gray-500 to-gray-600'
          };

          // Status based on enabled flag - simplified to single status
          const appStatus: 'Siap Diakses' | 'Dalam Pengembangan' = app.enabled ? 'Siap Diakses' : 'Dalam Pengembangan';
          const isOnline = app.enabled;

          // Get user role in this application
          const userAppData = user?.applications?.find((ua: UserApplication) => ua.app_key === app.app_key);
          const userRole = userAppData?.roles?.[0]?.name || undefined;

          // console.log(`  ✅ App: ${app.name} (app_key: ${app.app_key}, enabled: ${app.enabled})`);

          return {
            id: app.app_key,
            name: app.name,
            description: app.description || '',
            status: appStatus,
            url: app.app_url || `/${app.app_key}`,
            notifications: 0,
            icon: config.icon,
            gradient: config.gradient,
            isOnline: isOnline,
            userRole: userRole,
          };
        });

        // console.log('🎯 [Dashboard-Inertia] Final appsWithIcons:', appsWithIcons);
        // console.log('✅ [Dashboard-Inertia] Successfully rendered', appsWithIcons.length, 'applications');
        setApplications(appsWithIcons);
      } catch (error) {
        // console.error('❌ [Dashboard-Inertia] Failed to process applications:', error);
        // Fallback to empty array - user can see no apps loaded
        setApplications([]);
      } finally {
        setLoading(false);
      }
    };

    processApplications();
  }, [appsFromProps, user?.applications]);

  // Placeholder for nip, will be fetched from user data
  const nip = user?.nip || '---';


  const handleAppClick = (app: Application) => {
    if (app.url) {
      window.open(app.url, '_blank');
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-cyan-50 relative overflow-hidden pb-20">
      {/* Animated Background Elements */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-0 right-0 w-96 h-96 bg-gradient-to-br from-blue-300/20 to-cyan-300/20 rounded-full blur-3xl animate-pulse" style={{ animationDuration: '15s' }} />
        <div className="absolute bottom-0 left-0 w-96 h-96 bg-gradient-to-br from-teal-300/20 to-emerald-300/20 rounded-full blur-3xl animate-pulse" style={{ animationDuration: '15s', animationDelay: '3s' }} />
      </div>

      {/* User Info Modal */}
      {showInfoModal && (
        <>
          {/* Backdrop */}
          <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50" onClick={() => setShowInfoModal(false)} />

          {/* Modal - Desktop Popup */}
          <div className="fixed top-20 right-4 md:right-8 z-50 hidden md:block">
            <div className="bg-white rounded-2xl shadow-2xl w-96 animate-slideDown" onClick={(e) => e.stopPropagation()}>
              <ModalContent user={user} nip={nip} logout={logout} onClose={() => setShowInfoModal(false)} accessProfiles={accessProfiles} />
            </div>
          </div>

          {/* Modal - Mobile Sidebar */}
          <div className="fixed top-0 right-0 h-full z-50 md:hidden w-96 max-w-[100vw] animate-slideLeft" onClick={(e) => e.stopPropagation()}>
            <div className="bg-white h-full shadow-2xl flex flex-col">
              <ModalContent user={user} nip={nip} logout={logout} onClose={() => setShowInfoModal(false)} isMobile accessProfiles={accessProfiles} />
            </div>
          </div>
        </>
      )}

      {/* Main Content */}
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12 relative z-10">
        {/* Header - Logo and User Info */}
        <div className="flex items-center justify-between mb-8" style={{ animation: 'fadeIn 0.8s ease-out forwards' }}>
          {/* Logo and Title */}
          <div className="flex items-center gap-3">
            <div className="bg-gradient-to-br from-blue-500 via-cyan-500 to-teal-500 p-2.5 rounded-xl shadow-lg relative">
              <Hospital className="w-7 h-7 text-white" />
              <div className="absolute inset-0 bg-gradient-to-br from-blue-400 to-cyan-400 rounded-xl blur-lg opacity-50 -z-10"></div>
            </div>
            <div>
              <h1 className="text-2xl font-bold bg-gradient-to-r from-blue-700 via-cyan-600 to-teal-600 bg-clip-text text-transparent">
                Single Sign-On
              </h1>
              <p className="text-sm text-gray-600">
                Portal akses terpadu Rumah Sakit Citra Husada Jember
              </p>
            </div>
          </div>

          {/* User Account Button */}
          <button
            onClick={() => setShowInfoModal(true)}
            className="relative group"
          >
            <div className="w-12 h-12 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-xl flex items-center justify-center shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-110 active:scale-95">
              <User className="w-6 h-6 text-white" />
            </div>

          </button>
        </div>

        {/* Welcome Text */}
        <div className="mb-8" style={{ animation: 'fadeIn 0.8s ease-out 0.2s forwards', opacity: 0 }}>
          <p className="text-gray-600 text-xs md:text-sm mb-2">SELAMAT DATANG, {user?.name?.toUpperCase() || 'USER'}</p>
          <h2 className="text-2xl md:text-4xl font-bold text-gray-900 mb-3">
            Satu pintu untuk semua <span className="bg-gradient-to-r from-cyan-500 to-blue-500 bg-clip-text text-transparent">aplikasi layanan.</span>
          </h2>
          <p className="text-gray-600 text-sm md:text-lg">
            Masuk sekali, lalu akses seluruh aplikasi operasional rumah sakit dengan aman: mutu, insiden, dokumen, hingga analitik manajemen.
          </p>
        </div>

        <div className="w-full">
          {loading ? (
            <div className="flex justify-center items-center py-12">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
            </div>
          ) : applications.length === 0 ? (
            <div className="flex justify-center items-center py-12">
              <div className="text-center">
                <Hospital className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                <p className="text-gray-600 text-lg">Tidak ada aplikasi yang tersedia</p>
                <p className="text-gray-400 text-sm mt-2">Hubungi administrator untuk akses aplikasi</p>
              </div>
            </div>
          ) : (
            <>
              {/* Applications Grid - Full Width */}
              <div className="w-full">
                {/* Applications Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5">
                  {applications.map((app, index) => {
                    const Icon = app.icon;
                    return (
                      <div
                        key={app.id}
                        style={{ opacity: 0, animation: `slideUp 0.6s ease-out ${0.1 * index}s forwards` }}
                      >
                        <button
                          onClick={() => handleAppClick(app)}
                          disabled={!app.isOnline}
                          className="relative cursor-pointer group w-full text-left h-full disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                          <div className={`bg-white/70 backdrop-blur-md rounded-2xl p-4 sm:p-5 md:p-6 shadow-lg hover:shadow-2xl border border-blue-100/50 transition-all duration-300 h-full relative overflow-hidden ${!app.isOnline ? 'opacity-75' : 'hover:scale-105 active:scale-95'}`}>
                            {/* Gradient overlay on hover */}
                            <div className="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-cyan-500/5 to-teal-500/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-2xl" />

                            {/* Offline overlay */}
                            {!app.isOnline && (
                              <div className="absolute inset-0 bg-red-500/10 rounded-2xl z-30 flex items-center justify-center">
                                <span className="text-red-700 font-semibold text-sm bg-red-100/80 px-3 py-1.5 rounded-lg backdrop-blur-sm">Offline</span>
                              </div>
                            )}



                            {/* Icon */}
                            <div className={`inline-flex p-3 sm:p-3.5 rounded-xl bg-gradient-to-br ${app.gradient} text-white shadow-lg mb-3 sm:mb-4 relative z-10 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300`}>
                              <Icon className="w-6 sm:w-8 h-6 sm:h-8" />
                              <div className={`absolute inset-0 bg-gradient-to-br ${app.gradient} rounded-xl blur-md opacity-50 -z-10`}></div>
                            </div>

                            {/* Content */}
                            <div className="mb-4 relative z-10">
                              <h3 className="text-sm sm:text-base md:text-lg font-bold text-gray-900 mb-2 leading-snug group-hover:text-blue-600 transition-colors line-clamp-2">
                                {app.name}
                              </h3>
                              <p className="text-xs sm:text-sm text-gray-600 line-clamp-2 mb-3">
                                {app.description}
                              </p>

                              {/* Status Badge and User Role */}
                              <div className="space-y-2">
                                {/* Status Badge - Simplified */}
                                {app.status && (
                                  <div className="flex items-center gap-2">
                                    <span className={`text-xs font-semibold px-2.5 py-1 rounded-full flex items-center gap-1.5 flex-shrink-0 ${app.status === 'Siap Diakses' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' :
                                      'bg-amber-100 text-amber-700 border border-amber-200'
                                      }`}>
                                      <span className={`w-1.5 h-1.5 rounded-full ${app.status === 'Siap Diakses' ? 'bg-emerald-500' : 'bg-amber-500 animate-pulse'
                                        }`} />
                                      {app.status}
                                    </span>
                                  </div>
                                )}

                                {/* User Role in Application */}
                                {app.userRole && (
                                  <div className="flex items-center gap-2">
                                    <span className="text-xs text-blue-700 bg-blue-50/80 border border-blue-200 px-2.5 py-1 rounded-full font-medium flex-shrink-0">
                                      👤 {app.userRole}
                                    </span>
                                  </div>
                                )}
                              </div>
                            </div>

                            {/* Hover indicator */}
                            <div className={`absolute bottom-4 right-4 opacity-0 transition-opacity duration-300 z-10 ${!app.isOnline ? 'hidden' : 'group-hover:opacity-100'}`}>
                              <div className="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center shadow-lg">
                                <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                </svg>
                              </div>
                            </div>
                          </div>
                        </button>
                      </div>
                    );
                  })}
                </div>
              </div>
            </>
          )}
        </div>

        {/* Footer Info */}
        <div className="mt-16 text-center" style={{ animation: 'fadeIn 0.8s ease-out 1.5s forwards', opacity: 0 }}>
          <p className="text-sm text-gray-500 mb-2">
            💡 Tip: Klik pada aplikasi untuk membuka, atau akses Admin Panel untuk pengaturan tambahan
          </p>
          <p className="text-xs text-gray-400">
            Semua data terlindungi dengan enkripsi tingkat enterprise
          </p>
        </div>
      </main>

      <style>{`
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideLeft { from { transform: translateX(100%); } to { transform: translateX(0); } }
        .animate-slideDown { animation: slideDown 0.3s ease-out forwards; }
        .animate-slideLeft { animation: slideLeft 0.3s ease-out forwards; }
      `}</style>
    </div>
  );
}