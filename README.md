 # 🔐 Laravel IAM + SSO RBAC

Central Identity & Access Management (IAM) server dengan Single Sign-On (SSO) dan Role-Based Access Control (RBAC) untuk ekosistem aplikasi Rumah Sakit.

<p align="left">
  <a href="https://www.php.net/releases/8.2/en.php"><img alt="PHP" src="https://img.shields.io/badge/PHP-%5E8.2-777BB4?logo=php&logoColor=white"></a>
  <a href="https://laravel.com"><img alt="Laravel" src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white"></a>
  <a href="https://filamentphp.com"><img alt="Filament" src="https://img.shields.io/badge/Filament-4-00B5D8"></a>
  <a href="https://spatie.be/docs/laravel-permission"><img alt="Spatie Permission" src="https://img.shields.io/badge/Spatie_Permission-6-00B5D8"></a>
</p>

---

## ⚠️ Important: NIP-Based Authentication

**Version 2.0** - Sistem telah dioptimalkan untuk menggunakan **NIP (Nomor Induk Pegawai)** sebagai identifier utama menggantikan email.

- ✅ **Login Field**: Gunakan `nip` bukan `email`
- ✅ **Primary Identifier**: NIP (unique, required)
- ✅ **Email**: Opsional (nullable)
- 📚 **Migration Guide**: Lihat [NIP-MIGRATION-SUMMARY.md](./docs/NIP-MIGRATION-SUMMARY.md)

---

## ✨ Features

- 🔐 **Central Authentication** - Single source of truth untuk user authentication
- 🆔 **NIP-Based Login** - Authentication menggunakan NIP sebagai identifier utama
- 🎫 **OAuth2-like SSO Flow** - Authorization code grant dengan JWT tokens
- 👥 **RBAC Management** - Roles & Permissions menggunakan Spatie Permission
- 🔑 **JWT Tokens** - Access & Refresh tokens dengan signature verification
- 📱 **Multi-Application Support** - Manage multiple client applications
- 🛡️ **Security First** - Hashed secrets, CSRF protection, token revocation
- 📊 **Filament Admin Panel** - Beautiful UI untuk manage users, roles, permissions, applications
- 🔄 **Token Introspection** - Validate tokens dari client applications
- 📝 **Comprehensive Docs** - Full documentation untuk IAM server & client integration

---

## 🏗️ Architecture

```
┌─────────────────┐         ┌─────────────────┐
│  Client Apps    │         │   IAM Server    │
│  (SIIMUT, etc)  │◄────────┤   (This Repo)   │
│                 │ Tokens  │                 │
└─────────────────┘         └─────────────────┘
```

**Supported Client Applications:**
- SIIMUT - Sistem Informasi Manajemen Rumah Sakit
- Incident Reporting System
- Pharmacy Management System
- Any custom application

---

## 🚀 Quick Start

 ## 🚀 Quick Start

1) **Clone & Install**
```bash
git clone https://github.com/juniyasyos/laravel-iam.git
cd laravel-iam
composer install
npm install
```

2) **Environment Setup**
```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` with your database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=iam_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

3) **Run Migrations**
```bash
php artisan migrate
```

4) **Seed Sample Data (Optional)**
```bash
php artisan db:seed --class=IAMSampleDataSeeder
```

This creates sample users, roles, permissions, and applications.

**Sample Credentials (NIP-based):**
- Admin: NIP `admin001` / password `password`
- Doctor: NIP `doctor001` / password `password`
- Nurse: NIP `nurse001` / password `password`

> **Note**: Login menggunakan NIP, bukan email. Email bersifat opsional.

**Sample Applications:**
- SIIMUT: `siimut.app` / `siimut_secret_key_123`
- Incident Report: `incident-report.app` / `incident_secret_key_456`

5) **Build Assets & Start Server**
```bash
npm run build
php artisan serve
```

Visit: `http://localhost:8000/admin`

---

## 📚 Documentation

### 🆕 NIP-Based Authentication (v2.0)
- **[SSO Client Integration - NIP](docs/SSO-CLIENT-NIP-INTEGRATION.md)** - 📖 Panduan lengkap integrasi SSO dengan NIP
- **[SSO Quick Start - NIP](docs/SSO-NIP-QUICK-START.md)** - 🚀 Quick start guide (5 menit)
- **[NIP Migration Summary](docs/NIP-MIGRATION-SUMMARY.md)** - 🔄 Summary perubahan dan migration guide

### Core Documentation
- **[IAM + SSO RBAC Full Documentation](docs/IAM-SSO-RBAC-DOCUMENTATION.md)** - Complete technical documentation
- **[Client Integration Guide](docs/CLIENT-INTEGRATION.md)** - How to integrate client applications
- **[Setup Guide](docs/SETUP.md)** - Installation & deployment guide
- **[API Response Format](docs/API-RESPONSE-FORMAT.md)** - API response structure
- **[Quick Reference](docs/QUICK-REFERENCE.md)** - Quick reference guide

### What's Included

**Database Schema:**
- `applications` - Client application registry
- `users` - User accounts with unit information
- `roles` - User roles (via Spatie Permission)
- `permissions` - Granular permissions (via Spatie Permission)
- `model_has_roles` - User-role assignments
- `model_has_permissions` - Direct permission assignments
- `role_has_permissions` - Role-permission assignments

**API Endpoints:**
- `GET /oauth/authorize` - Authorization endpoint
- `POST /oauth/token` - Token exchange & refresh
- `POST /oauth/introspect` - Token validation
- `GET /oauth/userinfo` - User information
- `POST /oauth/revoke` - Token revocation

**JWT Token Payload (v2.0 - NIP-based):**
```json
{
  "iss": "https://iam.example.com",
  "sub": "123",
  "nip": "198501012010121001",
  "email": "doctor@rs.id",
  "name": "Dr. John Doe",
  "app": "siimut.app",
  "roles": ["doctor"],
  "iat": 1700294400,
  "exp": 1700298000
}
```

**Key Changes:**
- ⭐ `nip`: PRIMARY IDENTIFIER (new, required)
- 🔄 `email`: Optional (nullable)
- ✅ `iss`: Token issuer
- ✅ `app`: Application key (updated from `app_key`)

---

## 🔧 Managing Applications

### Via Filament Admin Panel

1. Login to admin panel: `/admin`
2. Navigate to "Applications"
3. Create new application with:
   - App Key (e.g., `myapp.app`)
   - Name & Description
   - Redirect URIs (JSON array)
   - Client Secret
   - Allowed Scopes (permissions)
   - Token Expiry (seconds)

### Via Artisan Tinker

```php
php artisan tinker

use  App\Domain\Iam\Models\Application;;

$app = Application::create([
    'app_key' => 'myapp.app',
    'name' => 'My Application',
    'enabled' => true,
    'redirect_uris' => ['http://localhost:3000/auth/callback'],
    'secret' => 'my_client_secret', // Automatically hashed
    'token_expiry' => 3600,
]);
```

---

## 👥 Managing Roles & Permissions

### Via Filament Admin Panel

1. Navigate to "Roles"
2. Create roles (e.g., `doctor`, `nurse`, `admin`)
3. Assign permissions to roles
4. Navigate to "Users"
5. Assign roles to users

### Via Artisan Tinker

```php
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// Create permission
Permission::create(['name' => 'read:patients']);

// Create role
$doctor = Role::create(['name' => 'doctor']);
$doctor->givePermissionTo('read:patients');

// Assign role to user
$user = User::find(1);
$user->assignRole('doctor');
```

---

## 🧪 Testing SSO Flow

### 1. Start IAM Server
```bash
php artisan serve
```

### 2. Test Authorization
Visit:
```
http://localhost:8000/oauth/authorize?app_key=siimut.app&redirect_uri=http://localhost:3000/auth/callback&state=random123
```

### 3. Exchange Code for Token
```bash
curl -X POST http://localhost:8000/oauth/token \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "authorization_code",
    "app_key": "siimut.app",
    "app_secret": "siimut_secret_key_123",
    "code": "YOUR_AUTH_CODE",
    "redirect_uri": "http://localhost:3000/auth/callback"
  }'
```

### 4. Get User Info
```bash
curl -X GET http://localhost:8000/oauth/userinfo \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

---

## 🔌 Client Integration

### Quick Setup for Client Apps

1. **Install JWT Library**
```bash
composer require firebase/php-jwt
```

2. **Configure IAM**
```env
IAM_SERVER_URL=https://iam.rs.id
IAM_APP_KEY=your_app_key
IAM_APP_SECRET=your_client_secret
IAM_REDIRECT_URI=https://your-app.rs.id/auth/callback
```

3. **Copy Middleware Files**
Copy from IAM repository:
- `app/Services/JWTTokenService.php`
- `app/Http/Middleware/VerifyIAMAccessToken.php`
- `app/Http/Middleware/InjectIAMUserContext.php`
- `app/Http/Middleware/CheckIAMPermission.php`
- `app/Http/Middleware/CheckIAMRole.php`

4. **Protect Routes**
```php
Route::middleware(['iam.verify', 'iam.inject'])->group(function () {
    // Authenticated routes
    Route::get('/patients', [PatientController::class, 'index']);
    
    // With permission check
    Route::middleware('iam.permission:write:patients')->group(function () {
        Route::post('/patients', [PatientController::class, 'store']);
    });
});
```

**Full integration guide:** [docs/CLIENT-INTEGRATION.md](docs/CLIENT-INTEGRATION.md)

---

## 🛡️ Security Features

- ✅ **Hashed Client Secrets** - SHA-256 hashing untuk application secrets
- ✅ **CSRF Protection** - State parameter validation
- ✅ **Short-lived Access Tokens** - Default 1 hour expiry
- ✅ **Token Revocation** - Refresh tokens dapat di-revoke
- ✅ **Redirect URI Validation** - Strict whitelist validation
- ✅ **JWT Signature Verification** - HS256 algorithm
- ✅ **One-time Authorization Codes** - 5 minute TTL, single use
- ✅ **Rate Limiting** - Throttle on sensitive endpoints

---

## 📦 Tech Stack

- **Backend:** Laravel 12, PHP 8.2
- **Database:** MySQL / PostgreSQL
- **Cache:** Redis (for auth codes & tokens)
- **Admin Panel:** Filament v4
- **JWT:** Firebase PHP-JWT
- **RBAC:** Spatie Laravel Permission
- **Testing:** Pest PHP
- **Frontend (Admin):** Vue 3 + Inertia.js + Tailwind CSS v4

---

## 🔄 SSO Flow Diagram

```
┌─────────┐                ┌─────────┐                ┌─────────┐
│ Client  │                │   IAM   │                │  User   │
│   App   │                │ Server  │                │ Browser │
└────┬────┘                └────┬────┘                └────┬────┘
     │                          │                          │
     │ 1. Redirect /oauth/authorize                        │
     ├─────────────────────────►│                          │
     │                          │ 2. Show login (if needed)│
     │                          ├─────────────────────────►│
     │                          │ 3. User authenticates    │
     │                          │◄─────────────────────────┤
     │                          │ 4. Generate auth code    │
     │ 5. Redirect with code    │                          │
     │◄─────────────────────────┤                          │
     │ 6. POST /oauth/token     │                          │
     ├─────────────────────────►│                          │
     │ 7. Return tokens         │                          │
     │◄─────────────────────────┤                          │
     │ 8. API calls with token  │                          │
     ├─────────────────────────►│                          │
```

---

## 🧰 Development

 5) Open the panel
 - http://localhost:8000/panel

 ---

 ## 🎨 Theming & Branding
 Konfigurasi tema dan panel dibuat modular.

 - Panel config: `config/panel.php`
   - id, path, name, version
   - theme.plugin (kelas plugin tema)
 - Theme config: `config/panel-theme.php`
   - colors, default_mode, brand (logo, favicon)
   - vite_path untuk entry CSS

 Plugin dan provider:
 - Plugin: `app/Filament/Plugins/PanelTheme.php`
 - Panel provider: `app/Providers/Filament/PanelPanelProvider.php`
 - CSS entry: `resources/css/filament/panel/theme.css` (sudah di `vite.config.ts`)

 ### Konfigurasi lewat .env
 ```env
 # Panel Settings
 PANEL_ID=panel
 PANEL_PATH=panel
 PANEL_NAME=Panel
 # Optional version label at topbar
 # PANEL_VERSION=1.0.0

 # Theme Colors & Mode
 PANEL_THEME_PRIMARY=#f59e0b
 PANEL_THEME_MODE=system  # system | light | dark

 # Branding (opsional)
 PANEL_BRAND_NAME=Panel
 # PANEL_BRAND_LOGO=/images/brand/logo-light.svg
 # PANEL_BRAND_LOGO_DARK=/images/brand/logo-dark.svg
 # PANEL_BRAND_LOGO_HEIGHT=1.5rem
 # PANEL_BRAND_FAVICON=/favicon.ico
 ```
 Simpan aset di `public/` (mis. `public/images/brand/...`).

 Setelah perubahan, clear config dan rebuild assets jika perlu:
 ```bash
 php artisan config:clear
 npm run dev # atau npm run build
 ```

 ### Catatan
 - Provider membaca `config('panel.*')` untuk `id`, `path`, `name`, `version`, dan `theme.plugin`.
 - Jika `PANEL_VERSION` diset, label versi tampil di topbar.

 ---

 ## 📦 Tech Stack
 - Laravel 12, PHP 8.2
 - Filament v4 (Panel at `/panel`)
 - Inertia + Vue 3
 - Vite + Tailwind v4 (`@tailwindcss/vite`)
 - Pest tests

 ---

 ## 🧰 NPM/Composer Scripts
 - `composer dev` — run server, queue, logs, and Vite together
 - `composer dev:ssr` — same but with Inertia SSR
 - `npm run dev` — Vite dev server
 - `npm run build` — production build
 - `composer test` — run tests

 ---

 ## 📁 Notable Paths
 - Panel provider: `app/Providers/Filament/PanelPanelProvider.php`
 - Theme plugin: `app/Filament/Plugins/PanelTheme.php`
 - Theme CSS: `resources/css/filament/panel/theme.css`
 - Panel config: `config/panel.php`
 - Theme config: `config/panel-theme.php`

 ---

 ## 🙌 Tips
 - Butuh palette lain? Gunakan `Filament\Support\Colors\Color::*` atau hex.
 - Ingin tampilan kustom? Ubah `resources/css/filament/panel/*` atau tambahkan partial baru.
 - Prefer MySQL/PostgreSQL? Update `.env` dan rerun migrations.

 ---

 ## 📝 License
 MIT — feel free to use, modify, and ship.

 ---

 ## 💬 Feedback
 Found something to improve or an idea to enhance the starter? Issues and PRs are welcome.

 Happy building! ✨
