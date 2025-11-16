# IAM + SSO RBAC Documentation

## Overview
Sistem IAM (Identity & Access Management) ini berfungsi sebagai pusat autentikasi dan otorisasi untuk semua aplikasi RS (Rumah Sakit), termasuk SIIMUT, pelaporan insiden, dan aplikasi lainnya. Sistem ini menggunakan OAuth2-like flow dengan JWT tokens untuk Single Sign-On (SSO) dan Role-Based Access Control (RBAC) menggunakan Spatie Permission.

---

## Table of Contents
1. [Architecture](#architecture)
2. [Database Schema](#database-schema)
3. [JWT Token Structure](#jwt-token-structure)
4. [SSO Flow](#sso-flow)
5. [API Endpoints](#api-endpoints)
6. [Client Integration](#client-integration)
7. [RBAC Management](#rbac-management)
8. [Security Considerations](#security-considerations)

---

## Architecture

```
┌─────────────────┐         ┌─────────────────┐
│                 │         │                 │
│  Client App     │◄────────┤   IAM Server    │
│  (SIIMUT, etc)  │ Tokens  │   (Laravel)     │
│                 │         │                 │
└────────┬────────┘         └────────┬────────┘
         │                           │
         │                           │
         │                    ┌──────▼──────┐
         │                    │             │
         └────────────────────┤  Database   │
                              │  (MySQL)    │
                              │             │
                              └─────────────┘
```

**Components:**
- **IAM Server**: Central authentication & authorization server
- **Client Applications**: Apps that consume IAM services (SIIMUT, etc.)
- **Database**: Stores users, roles, permissions, and application registry

---

## Database Schema

### 1. Table: `applications`
Registry untuk semua aplikasi klien yang terhubung dengan IAM.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `app_key` | string | Unique identifier untuk aplikasi (client_id) |
| `name` | string | Nama aplikasi |
| `description` | text (nullable) | Deskripsi aplikasi |
| `enabled` | boolean | Status aktif aplikasi (default: true) |
| `redirect_uris` | json (nullable) | Array URI yang diizinkan untuk redirect |
| `callback_url` | string (nullable) | URL callback setelah login |
| `secret` | string (nullable) | Hashed client secret untuk autentikasi |
| `logo_url` | string (nullable) | URL logo aplikasi |
| `token_expiry` | integer (nullable) | Token expiry dalam detik (default: 3600) |
| `created_by` | bigint (nullable) | User ID pembuat aplikasi |
| `created_at` | timestamp | Waktu pembuatan |
| `updated_at` | timestamp | Waktu update terakhir |
| `deleted_at` | timestamp (nullable) | Soft delete timestamp |

**Indexes:**
- Primary: `id`
- Unique: `app_key`
- Foreign Key: `created_by` → `users.id` (nullOnDelete)

**Important Notes:**
- `app_key` berfungsi sebagai **client_id** dalam OAuth2 flow
- `secret` disimpan dalam bentuk **SHA-256 hash** (bukan plaintext)
- `redirect_uris` harus berisi array URI yang valid untuk keamanan

**Example Data:**
```json
{
  "app_key": "siimut.app",
  "name": "SIIMUT - Sistem Informasi Manajemen Rumah Sakit",
  "description": "Aplikasi manajemen utama RS",
  "enabled": true,
  "redirect_uris": ["https://siimut.rs.id/auth/callback"],
  "secret": "<hashed_value>",
  "token_expiry": 3600
}
```

---

### 2. Spatie Permission Tables

Sistem menggunakan **Spatie Laravel Permission** untuk RBAC:

#### Table: `roles`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string | Nama role (e.g., "admin", "doctor") |
| `guard_name` | string | Guard name (default: "web") |
| `created_at` | timestamp | Waktu pembuatan |
| `updated_at` | timestamp | Waktu update |

#### Table: `permissions`
| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string | Nama permission (e.g., "create-patient") |
| `guard_name` | string | Guard name (default: "web") |
| `created_at` | timestamp | Waktu pembuatan |
| `updated_at` | timestamp | Waktu update |

#### Table: `model_has_roles`
Pivot table untuk relasi user-role (many-to-many).

#### Table: `model_has_permissions`
Pivot table untuk direct permission assignment ke user.

#### Table: `role_has_permissions`
Pivot table untuk relasi role-permission (many-to-many).

---

### 3. Table: `users`
User table sudah ada, dengan penambahan kolom `unit`:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string | Nama lengkap user |
| `email` | string | Email address (unique) |
| `password` | string | Hashed password |
| `active` | boolean | Status aktif user |
| `unit` | string (nullable) | Unit kerja user (e.g., "ICU", "ER") |
| `created_at` | timestamp | Waktu pembuatan |
| `updated_at` | timestamp | Waktu update |

**Relations:**
- `roles()`: HasMany through Spatie Permission
- `permissions()`: HasMany through Spatie Permission

---

## JWT Token Structure

### Access Token Payload

```json
{
  "iss": "https://iam.rs.id",
  "sub": 123,
  "iat": 1700000000,
  "exp": 1700003600,
  "name": "Dr. John Doe",
  "email": "john.doe@rs.id",
  "app_key": "siimut.app",
  "roles": ["doctor", "department_head"],
  "unit": "ICU",
  "type": "access"
}
```

**Field Descriptions:**
- `iss`: Issuer (IAM server URL)
- `sub`: Subject (User ID)
- `iat`: Issued At (timestamp)
- `exp`: Expiration Time (timestamp)
- `name`: User full name
- `email`: User email
- `app_key`: Application identifier
- `roles`: Array of role names
- `permissions`: Array of permission names (merged from roles + direct permissions)
- `unit`: User's unit kerja (optional)
- `type`: Token type ("access")

---

### Refresh Token Payload

```json
{
  "iss": "https://iam.rs.id",
  "sub": 123,
  "iat": 1700000000,
  "exp": 1702592000,
  "app_key": "siimut.app",
  "type": "refresh"
}
```

**Field Descriptions:**
- Minimal payload untuk refresh token
- `exp`: 30 hari dari issuance
- Disimpan di cache untuk revocation capability

---

## SSO Flow

### Complete Authentication Flow

```
┌─────────┐                ┌─────────┐                ┌─────────┐
│ Client  │                │   IAM   │                │  User   │
│   App   │                │ Server  │                │ Browser │
└────┬────┘                └────┬────┘                └────┬────┘
     │                          │                          │
     │ 1. Redirect to /oauth/authorize                     │
     │    with app_key, redirect_uri, state                │
     ├─────────────────────────►│                          │
     │                          │                          │
     │                          │ 2. Check if user logged in│
     │                          ├─────────────────────────►│
     │                          │                          │
     │                          │ 3. User login (if needed)│
     │                          │◄─────────────────────────┤
     │                          │                          │
     │                          │ 4. Generate auth code    │
     │                          │                          │
     │ 5. Redirect to client with code & state             │
     │◄─────────────────────────┤                          │
     │                          │                          │
     │ 6. POST /oauth/token                                │
     │    (exchange code for tokens)                       │
     ├─────────────────────────►│                          │
     │                          │                          │
     │ 7. Return access_token + refresh_token              │
     │◄─────────────────────────┤                          │
     │                          │                          │
     │ 8. Use access_token for API calls                   │
     ├─────────────────────────►│                          │
     │                          │                          │
```

### Step-by-Step Process

#### **Step 1: Authorization Request**
Client application redirects user ke IAM:

```http
GET /oauth/authorize?app_key=siimut.app&redirect_uri=https://siimut.rs.id/auth/callback&state=random_string
```

**Parameters:**
- `app_key`: Application identifier
- `redirect_uri`: Callback URL (must match registered URI)
- `state`: Random string untuk CSRF protection (recommended)

---

#### **Step 2-3: User Authentication**
IAM server checks if user is logged in. Jika belum, redirect ke login page. Setelah login, IAM validates:
- Application exists dan enabled
- `redirect_uri` matches registered URIs

---

#### **Step 4-5: Authorization Code**
IAM generates authorization code dan redirect kembali ke client:

```http
HTTP/1.1 302 Found
Location: https://siimut.rs.id/auth/callback?code=AUTH_CODE&state=random_string
```

**Authorization Code:**
- Valid untuk **5 menit**
- **One-time use** (deleted after exchange)
- Stored in cache dengan data: `user_id`, `app_key`, `redirect_uri`

---

#### **Step 6: Token Exchange**
Client exchanges authorization code untuk tokens:

```http
POST /oauth/token
Content-Type: application/json

{
  "grant_type": "authorization_code",
  "app_key": "siimut.app",
  "app_secret": "your_client_secret",
  "code": "AUTH_CODE",
  "redirect_uri": "https://siimut.rs.id/auth/callback"
}
```

**Response:**
```json
{
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

---

#### **Step 7: Token Refresh**
Saat access token expired, gunakan refresh token:

```http
POST /oauth/token
Content-Type: application/json

{
  "grant_type": "refresh_token",
  "app_key": "siimut.app",
  "app_secret": "your_client_secret",
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Response:**
```json
{
  "access_token": "NEW_ACCESS_TOKEN",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

---

## API Endpoints

### IAM Server Endpoints

#### 1. Authorization Endpoint
```http
GET /oauth/authorize
```

**Query Parameters:**
- `app_key` (required): Application identifier
- `redirect_uri` (required): Callback URL
- `state` (optional): CSRF protection string

**Response:**
- Redirect to login (if not authenticated)
- Redirect to client with authorization code (if authenticated)

---

#### 2. Token Endpoint
```http
POST /oauth/token
```

**Request Body:**
```json
{
  "grant_type": "authorization_code|refresh_token",
  "app_key": "siimut.app",
  "app_secret": "your_secret",
  "code": "AUTH_CODE (if authorization_code)",
  "redirect_uri": "CALLBACK_URL (if authorization_code)",
  "refresh_token": "REFRESH_TOKEN (if refresh_token)"
}
```

**Response:**
```json
{
  "access_token": "JWT_TOKEN",
  "refresh_token": "JWT_TOKEN (only for authorization_code grant)",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

---

#### 3. Token Introspection
```http
POST /oauth/introspect
```

**Request Body:**
```json
{
  "token": "ACCESS_TOKEN",
  "app_key": "siimut.app",
  "app_secret": "your_secret"
}
```

**Response (Active Token):**
```json
{
  "active": true,
  "sub": 123,
  "name": "Dr. John Doe",
  "email": "john.doe@rs.id",
  "roles": ["doctor"],
  "unit": "ICU",
  "exp": 1700003600,
  "iat": 1700000000
}
```

**Response (Inactive Token):**
```json
{
  "active": false
}
```

---

#### 4. User Info Endpoint
```http
GET /oauth/userinfo
Authorization: Bearer ACCESS_TOKEN
```

**Response:**
```json
{
  "sub": 123,
  "name": "Dr. John Doe",
  "email": "john.doe@rs.id",
  "roles": ["doctor"],
  "unit": "ICU",
  "app_key": "siimut.app"
}
```

---

#### 5. Token Revocation
```http
POST /oauth/revoke
```

**Request Body:**
```json
{
  "token": "REFRESH_TOKEN",
  "app_key": "siimut.app",
  "app_secret": "your_secret"
}
```

**Response:**
```json
{
  "message": "Token revoked successfully"
}
```

---

## Client Integration

### Setup di Aplikasi Klien (e.g., SIIMUT)

#### 1. Install Firebase JWT Library

```bash
composer require firebase/php-jwt
```

---

#### 2. Konfigurasi IAM

Buat file `config/iam.php`:

```php
<?php

return [
    'server_url' => env('IAM_SERVER_URL', 'https://iam.rs.id'),
    'app_key' => env('IAM_APP_KEY', 'siimut.app'),
    'app_secret' => env('IAM_APP_SECRET'),
    'redirect_uri' => env('IAM_REDIRECT_URI', 'https://siimut.rs.id/auth/callback'),
];
```

**Environment Variables (.env):**
```env
IAM_SERVER_URL=https://iam.rs.id
IAM_APP_KEY=siimut.app
IAM_APP_SECRET=your_client_secret
IAM_REDIRECT_URI=https://siimut.rs.id/auth/callback
```

---

#### 3. Copy Middleware dari IAM

Copy 4 middleware files ke aplikasi klien:
- `VerifyIAMAccessToken.php`
- `InjectIAMUserContext.php`
- `CheckIAMPermission.php`
- `CheckIAMRole.php`

Dan copy `JWTTokenService.php` untuk verifikasi token.

---

#### 4. Register Middleware

Di `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'iam.verify' => \App\Http\Middleware\VerifyIAMAccessToken::class,
        'iam.inject' => \App\Http\Middleware\InjectIAMUserContext::class,
        'iam.permission' => \App\Http\Middleware\CheckIAMPermission::class,
        'iam.role' => \App\Http\Middleware\CheckIAMRole::class,
    ]);
})
```

---

#### 5. Implementasi SSO Login Flow

**Auth Controller:**

```php
<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SSOAuthController extends Controller
{
    public function redirectToIAM()
    {
        $query = http_build_query([
            'app_key' => config('iam.app_key'),
            'redirect_uri' => config('iam.redirect_uri'),
            'state' => session()->getId(), // CSRF protection
        ]);

        return redirect(config('iam.server_url').'/oauth/authorize?'.$query);
    }

    public function handleCallback(Request $request)
    {
        // Validate state
        if ($request->state !== session()->getId()) {
            return redirect('/login')->withErrors('Invalid state parameter');
        }

        // Exchange code for tokens
        $response = Http::post(config('iam.server_url').'/oauth/token', [
            'grant_type' => 'authorization_code',
            'app_key' => config('iam.app_key'),
            'app_secret' => config('iam.app_secret'),
            'code' => $request->code,
            'redirect_uri' => config('iam.redirect_uri'),
        ]);

        if (!$response->successful()) {
            return redirect('/login')->withErrors('Failed to authenticate');
        }

        $tokens = $response->json();

        // Store tokens in session
        session([
            'iam_access_token' => $tokens['access_token'],
            'iam_refresh_token' => $tokens['refresh_token'],
        ]);

        return redirect('/dashboard');
    }

    public function logout()
    {
        session()->forget(['iam_access_token', 'iam_refresh_token']);
        return redirect('/login');
    }
}
```

---

#### 6. Protect Routes dengan Middleware

**routes/api.php:**

```php
<?php

use Illuminate\Support\Facades\Route;

// Protected routes - require valid IAM token
Route::middleware(['iam.verify', 'iam.inject'])->group(function () {
    
    // All authenticated users
    Route::get('/profile', [ProfileController::class, 'show']);
    
    // Require specific permission
    Route::middleware('iam.permission:create-patient')->group(function () {
        Route::post('/patients', [PatientController::class, 'store']);
    });
    
    // Require specific role
    Route::middleware('iam.role:doctor,nurse')->group(function () {
        Route::get('/patients', [PatientController::class, 'index']);
    });
    
    // Multiple permissions (user must have at least one)
    Route::middleware('iam.permission:edit-patient,admin-override')->group(function () {
        Route::put('/patients/{id}', [PatientController::class, 'update']);
    });
});
```

---

#### 7. Access User Context dalam Controller

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        // Get IAM user info from request
        $userId = $request->get('iam_user_id');
        $userEmail = $request->get('iam_user_email');
        $userName = $request->get('iam_user_name');
        $userRoles = $request->get('iam_user_roles');
        $userPermissions = $request->get('iam_user_permissions');
        $userUnit = $request->get('iam_user_unit');

        // Or get full token object
        $token = $request->attributes->get('iam_token');

        // Use permissions for dynamic authorization
        if (in_array('read:all-patients', $userPermissions)) {
            $patients = Patient::all();
        } else {
            // Filter by user's unit
            $patients = Patient::where('unit', $userUnit)->get();
        }

        return response()->json($patients);
    }
}
```

---

#### 8. Token Refresh Implementation

Buat service untuk handle token refresh:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class IAMTokenService
{
    public function refreshAccessToken(string $refreshToken): ?array
    {
        $response = Http::post(config('iam.server_url').'/oauth/token', [
            'grant_type' => 'refresh_token',
            'app_key' => config('iam.app_key'),
            'app_secret' => config('iam.app_secret'),
            'refresh_token' => $refreshToken,
        ]);

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    public function introspectToken(string $token): ?array
    {
        $response = Http::post(config('iam.server_url').'/oauth/introspect', [
            'token' => $token,
            'app_key' => config('iam.app_key'),
            'app_secret' => config('iam.app_secret'),
        ]);

        if (!$response->successful() || !$response->json('active')) {
            return null;
        }

        return $response->json();
    }
}
```

---

## RBAC Management

### Mengelola Roles & Permissions di IAM

#### 1. Buat Roles

```php
use Spatie\Permission\Models\Role;

$admin = Role::create(['name' => 'admin']);
$doctor = Role::create(['name' => 'doctor']);
$nurse = Role::create(['name' => 'nurse']);
$receptionist = Role::create(['name' => 'receptionist']);
```

---

#### 2. Buat Permissions

```php
use Spatie\Permission\Models\Permission;

// Patient management
Permission::create(['name' => 'read:patients']);
Permission::create(['name' => 'write:patients']);
Permission::create(['name' => 'delete:patients']);

// Report management
Permission::create(['name' => 'read:reports']);
Permission::create(['name' => 'create:reports']);
Permission::create(['name' => 'approve:reports']);

// Prescription management
Permission::create(['name' => 'create:prescriptions']);
Permission::create(['name' => 'approve:prescriptions']);
```

---

#### 3. Assign Permissions ke Roles

```php
$admin = Role::findByName('admin');
$admin->givePermissionTo(Permission::all());

$doctor = Role::findByName('doctor');
$doctor->givePermissionTo([
    'read:patients',
    'write:patients',
    'read:reports',
    'create:reports',
    'create:prescriptions',
]);

$nurse = Role::findByName('nurse');
$nurse->givePermissionTo([
    'read:patients',
    'write:patients',
    'read:reports',
]);
```

---

#### 4. Assign Roles ke Users

```php
$user = User::find(1);
$user->assignRole('doctor');

// Multiple roles
$user->assignRole(['doctor', 'department_head']);

// Direct permission (without role)
$user->givePermissionTo('admin-override');
```

---

#### 5. Check Permissions

```php
// In controller
if ($user->hasPermissionTo('write:patients')) {
    // User can write patients
}

// Check role
if ($user->hasRole('admin')) {
    // User is admin
}

// Check any permission
if ($user->hasAnyPermission(['write:patients', 'admin-override'])) {
    // User has at least one permission
}
```

---

### Filament Resource untuk RBAC

Buat Filament resources untuk manage roles & permissions:

```bash
php artisan make:filament-resource Role
php artisan make:filament-resource Permission
```

**Example Role Resource:**

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Access Control';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\Select::make('permissions')
                    ->multiple()
                    ->relationship('permissions', 'name')
                    ->preload(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('permissions_count')->counts('permissions'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
```

---

## Security Considerations

### 1. Application Secret Storage

**❌ NEVER store plaintext secrets:**
```php
// BAD
$application->secret = $request->secret;
```

**✅ Always hash secrets:**
```php
// GOOD - Model automatically hashes via mutator
$application->secret = $request->secret; // Will be hashed to SHA-256
```

---

### 2. Redirect URI Validation

**Strict validation** untuk prevent open redirect attacks:

```php
public function isValidRedirectUri(string $uri): bool
{
    if (empty($this->redirect_uris)) {
        return false;
    }

    return in_array($uri, $this->redirect_uris, true); // Strict comparison
}
```

**Register multiple URIs** untuk development & production:

```json
{
  "redirect_uris": [
    "http://localhost:3000/auth/callback",
    "https://siimut.rs.id/auth/callback"
  ]
}
```

---

### 3. State Parameter

Always use `state` parameter untuk **CSRF protection**:

```php
// Client side
$state = bin2hex(random_bytes(16));
session(['oauth_state' => $state]);

// Callback validation
if ($request->state !== session('oauth_state')) {
    abort(400, 'Invalid state parameter');
}
```

---

### 4. Token Expiry

**Access tokens** should be **short-lived** (default: 1 hour):
- Minimize impact of token theft
- Forces refresh, enabling permission updates

**Refresh tokens** are **long-lived** (30 days):
- Stored in cache untuk revocation capability
- Can be revoked immediately if compromised

---

### 5. Token Signature Verification

Always verify JWT signature di client apps:

```php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

try {
    $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
} catch (\Exception $e) {
    // Invalid token
}
```

---

### 6. HTTPS Only

**CRITICAL:** All IAM endpoints **MUST** use HTTPS in production:
- Prevents token interception
- Protects client secrets in transit

---

### 7. Rate Limiting

Implement rate limiting pada token endpoint:

```php
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/oauth/token', [SSOController::class, 'token']);
});
```

---

### 8. Audit Logging

Log semua authentication & authorization events:

```php
Log::info('User Login', [
    'user_id' => $user->id,
    'app_key' => $application->app_key,
    'ip_address' => $request->ip(),
]);
```

---

## Testing

### Unit Test Example

```php
<?php

namespace Tests\Unit;

use  App\Domain\Iam\Models\Application;;
use App\Models\User;
use App\Services\JWTTokenService;
use Tests\TestCase;

class JWTTokenServiceTest extends TestCase
{
    public function test_generate_access_token()
    {
        $user = User::factory()->create();
        $user->assignRole('doctor');

        $app = Application::factory()->create([
            'app_key' => 'test.app',
            'token_expiry' => 3600,
        ]);

        $service = new JWTTokenService();
        $token = $service->generateAccessToken($user, $app);

        $this->assertNotEmpty($token);

        $decoded = $service->verifyToken($token);
        $this->assertEquals($user->id, $decoded->sub);
        $this->assertEquals('test.app', $decoded->app_key);
        $this->assertContains('doctor', $decoded->roles);
    }

    public function test_verify_invalid_token()
    {
        $this->expectException(\Exception::class);

        $service = new JWTTokenService();
        $service->verifyToken('invalid.token.here');
    }
}
```

---

### Integration Test Example

```php
<?php

namespace Tests\Feature;

use  App\Domain\Iam\Models\Application;;
use App\Models\User;
use Tests\TestCase;

class SSOFlowTest extends TestCase
{
    public function test_complete_sso_flow()
    {
        // Setup
        $user = User::factory()->create();
        $app = Application::factory()->create([
            'app_key' => 'test.app',
            'redirect_uris' => ['http://localhost/callback'],
            'secret' => 'test_secret',
        ]);

        // Step 1: Authorize
        $response = $this->actingAs($user)->get('/oauth/authorize', [
            'app_key' => 'test.app',
            'redirect_uri' => 'http://localhost/callback',
            'state' => 'test_state',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('code=', $response->headers->get('Location'));

        // Extract code
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $code = $query['code'];

        // Step 2: Exchange code for token
        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'app_key' => 'test.app',
            'app_secret' => 'test_secret',
            'code' => $code,
            'redirect_uri' => 'http://localhost/callback',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['access_token', 'refresh_token', 'token_type', 'expires_in']);
    }
}
```

---

## Troubleshooting

### Common Issues

#### 1. "Invalid or expired token"
**Cause:** Token expired atau signature invalid  
**Solution:** 
- Check token expiry time
- Verify `APP_KEY` sama di IAM & client
- Refresh token jika expired

#### 2. "Token not valid for this application"
**Cause:** `app_key` tidak match  
**Solution:**
- Verify `app_key` di token payload
- Check konfigurasi IAM_APP_KEY di client

#### 3. "Invalid redirect URI"
**Cause:** URI tidak terdaftar di aplikasi  
**Solution:**
- Tambahkan URI ke `redirect_uris` di database
- Pastikan exact match (termasuk trailing slash)

#### 4. "Application is disabled"
**Cause:** `enabled = false` di database  
**Solution:**
- Set `enabled = true` di table applications

---

## Maintenance

### Token Key Rotation

Untuk rotate JWT secret key:

1. Generate new APP_KEY
2. Update `.env` di IAM server
3. Re-deploy IAM server
4. Users harus re-login (old tokens akan invalid)

### Database Cleanup

Clean expired authorization codes (handled by cache TTL):
```bash
# Automatic via cache expiration (5 minutes)
```

Clean revoked refresh tokens:
```bash
# Handled by cache TTL (30 days)
```

---

## Additional Resources

- [Spatie Permission Documentation](https://spatie.be/docs/laravel-permission)
- [Firebase JWT Library](https://github.com/firebase/php-jwt)
- [OAuth 2.0 RFC](https://datatracker.ietf.org/doc/html/rfc6749)
- [JWT RFC](https://datatracker.ietf.org/doc/html/rfc7519)

---

## Support

Untuk pertanyaan atau issue, silakan hubungi:
- **Developer:** ahmad.ilyas@rs.id
- **Repository:** https://github.com/juniyasyos/laravel-iam

---

**Last Updated:** November 14, 2025  
**Version:** 1.0.0
