# SSO Client Integration Guide - NIP-Based Authentication

## Overview

Sistem IAM (Identity and Access Management) telah dioptimalkan untuk menggunakan **NIP (Nomor Induk Pegawai)** sebagai identifier utama menggantikan email. Dokumentasi ini memberikan panduan lengkap integrasi SSO untuk aplikasi client.

## 📋 Table of Contents

1. [Perubahan Utama](#perubahan-utama)
2. [Authentication Flow](#authentication-flow)
3. [Login Endpoint](#login-endpoint)
4. [SSO Token Flow](#sso-token-flow)
5. [OAuth2-like Flow](#oauth2-like-flow)
6. [Token Verification](#token-verification)
7. [User Info Retrieval](#user-info-retrieval)
8. [Token Structure](#token-structure)
9. [Client Implementation Examples](#client-implementation-examples)
10. [Migration Guide](#migration-guide)

---

## Perubahan Utama

### ✅ Yang Berubah:
- **Field Login**: `email` → `nip`
- **Token Payload**: Sekarang termasuk `nip` sebagai field utama
- **User Identification**: Menggunakan NIP sebagai primary identifier
- **Fortify Config**: Username field diubah ke `nip`

### ⚠️ Backward Compatibility:
- Field `email` tetap tersedia di token dan response untuk kompatibilitas
- Email bersifat **nullable** - tidak wajib diisi
- NIP adalah field **required** dan **unique**

---

## Authentication Flow

### Flow Diagram

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│   Client    │         │  IAM Server  │         │  User       │
│   App       │         │              │         │             │
└──────┬──────┘         └──────┬───────┘         └──────┬──────┘
       │                       │                        │
       │ 1. Redirect to Login  │                        │
       ├──────────────────────>│                        │
       │   ?app=client-app     │                        │
       │                       │                        │
       │                       │  2. Show Login Page    │
       │                       ├───────────────────────>│
       │                       │                        │
       │                       │  3. Submit NIP & Pass  │
       │                       │<───────────────────────┤
       │                       │                        │
       │                       │  4. Validate & Generate│
       │                       │     SSO Token          │
       │                       │                        │
       │ 5. Redirect with Token│                        │
       │<──────────────────────┤                        │
       │                       │                        │
       │ 6. Verify Token       │                        │
       ├──────────────────────>│                        │
       │                       │                        │
       │ 7. Token Valid + Data │                        │
       │<──────────────────────┤                        │
       │                       │                        │
```

---

## Login Endpoint

### 1. Login Page

**URL**: `GET https://iam.example.com/login`

**Query Parameters**:
```
app=your-app-key
```

**Example**:
```
https://iam.example.com/login?app=portal-mahasiswa
```

### 2. Login Credentials

User akan login menggunakan:
- **NIP** (required)
- **Password** (required)

**Form Fields**:
```json
{
  "nip": "198501012010121001",
  "password": "user_password",
  "remember": false
}
```

**Validation Rules**:
```php
'nip' => 'required|string',
'password' => 'required|string'
```

---

## SSO Token Flow

### Step 1: Redirect User ke IAM Login

Dari aplikasi client, redirect user ke:

```
https://iam.example.com/login?app={your_app_key}
```

**Example (Laravel)**:
```php
public function redirectToSSO()
{
    $iamUrl = config('services.iam.url');
    $appKey = config('services.iam.app_key');
    
    return redirect("{$iamUrl}/login?app={$appKey}");
}
```

**Example (JavaScript)**:
```javascript
function redirectToSSO() {
    const iamUrl = process.env.IAM_URL;
    const appKey = process.env.IAM_APP_KEY;
    
    window.location.href = `${iamUrl}/login?app=${appKey}`;
}
```

### Step 2: User Login dengan NIP

User akan diminta untuk login di IAM server menggunakan:
- NIP
- Password

### Step 3: Callback dengan Token

Setelah login sukses, user akan di-redirect kembali ke aplikasi client dengan token:

```
https://your-app.com/auth/callback?token={sso_token}
```

### Step 4: Verify Token

Client app harus memverifikasi token yang diterima:

**Endpoint**: `POST https://iam.example.com/api/sso/verify`

**Request**:
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "include_user_data": true
}
```

**Response**:
```json
{
  "nip": "198501012010121001",
  "email": "user@example.com",
  "token_info": {
    "sub": "123",
    "app": "portal-mahasiswa",
    "issuer": "https://iam.example.com",
    "issued_at": "2024-11-18T10:00:00+07:00",
    "expires_at": "2024-11-18T10:05:00+07:00"
  },
  "roles": ["mahasiswa", "pengurus-ukm"],
  "user": {
    "basic": {
      "id": 123,
      "nip": "198501012010121001",
      "name": "Ahmad Ilyas",
      "email": "ahmad@example.com",
      "active": true
    },
    "application": {
      "app_key": "portal-mahasiswa",
      "roles": ["mahasiswa", "pengurus-ukm"]
    },
    "access_profiles": [
      {
        "id": 1,
        "name": "Mahasiswa Aktif",
        "description": "Profile untuk mahasiswa aktif"
      }
    ]
  }
}
```

---

## OAuth2-like Flow

Untuk integrasi yang lebih robust, gunakan OAuth2-like flow:

### Step 1: Authorization Request

**Endpoint**: `GET https://iam.example.com/oauth/authorize`

**Parameters**:
```
client_id={app_key}
redirect_uri={your_callback_url}
response_type=code
state={random_state}
```

**Example**:
```
https://iam.example.com/oauth/authorize?
  client_id=portal-mahasiswa&
  redirect_uri=https://portal.example.com/auth/callback&
  response_type=code&
  state=xyz123
```

### Step 2: User Login dengan NIP

User login menggunakan NIP dan password.

### Step 3: Authorization Code Callback

IAM redirect dengan authorization code:
```
https://portal.example.com/auth/callback?code={auth_code}&state=xyz123
```

### Step 4: Exchange Code for Token

**Endpoint**: `POST https://iam.example.com/api/sso/token`

**Request**:
```json
{
  "grant_type": "authorization_code",
  "client_id": "portal-mahasiswa",
  "client_secret": "your_app_secret",
  "code": "authorization_code_here",
  "redirect_uri": "https://portal.example.com/auth/callback"
}
```

**Response**:
```json
{
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "user": {
    "id": 123,
    "nip": "198501012010121001",
    "name": "Ahmad Ilyas",
    "email": "ahmad@example.com"
  },
  "apps": ["portal-mahasiswa", "siakad"],
  "roles_by_app": {
    "portal-mahasiswa": ["mahasiswa", "pengurus-ukm"],
    "siakad": ["mahasiswa"]
  }
}
```

---

## Token Verification

### Introspect Token

**Endpoint**: `POST https://iam.example.com/api/sso/introspect`

**Request**:
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Response**:
```json
{
  "active": true,
  "sub": "123",
  "nip": "198501012010121001",
  "email": "ahmad@example.com",
  "name": "Ahmad Ilyas",
  "apps": ["portal-mahasiswa", "siakad"],
  "roles_by_app": {
    "portal-mahasiswa": ["mahasiswa", "pengurus-ukm"]
  },
  "iss": "https://iam.example.com",
  "iat": 1700294400,
  "exp": 1700298000
}
```

---

## User Info Retrieval

### Get User Info from Token

**Endpoint**: `GET https://iam.example.com/api/sso/userinfo`

**Headers**:
```
Authorization: Bearer {access_token}
```

**Response**:
```json
{
  "sub": "123",
  "nip": "198501012010121001",
  "email": "ahmad@example.com",
  "name": "Ahmad Ilyas",
  "apps": ["portal-mahasiswa", "siakad"],
  "roles_by_app": {
    "portal-mahasiswa": ["mahasiswa", "pengurus-ukm"],
    "siakad": ["mahasiswa"]
  }
}
```

---

## Token Structure

### JWT Token Payload

```json
{
  "iss": "https://iam.example.com",
  "sub": "123",
  "nip": "198501012010121001",
  "email": "ahmad@example.com",
  "name": "Ahmad Ilyas",
  "app": "portal-mahasiswa",
  "roles": ["mahasiswa", "pengurus-ukm"],
  "iat": 1700294400,
  "exp": 1700298000
}
```

### Token Claims:
- **iss**: Issuer (IAM server URL)
- **sub**: Subject (User ID)
- **nip**: Nomor Induk Pegawai (PRIMARY IDENTIFIER)
- **email**: Email address (nullable, for compatibility)
- **name**: User's full name
- **app**: Application key
- **roles**: Array of role slugs for the application
- **iat**: Issued at (Unix timestamp)
- **exp**: Expires at (Unix timestamp)

---

## Client Implementation Examples

### Laravel Client Implementation

#### 1. Configuration

**config/services.php**:
```php
'iam' => [
    'url' => env('IAM_URL', 'https://iam.example.com'),
    'app_key' => env('IAM_APP_KEY'),
    'app_secret' => env('IAM_APP_SECRET'),
    'callback_url' => env('IAM_CALLBACK_URL', env('APP_URL') . '/auth/callback'),
],
```

**.env**:
```env
IAM_URL=https://iam.example.com
IAM_APP_KEY=portal-mahasiswa
IAM_APP_SECRET=your_secret_here
IAM_CALLBACK_URL=https://portal.example.com/auth/callback
```

#### 2. SSO Controller

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class SSOController extends Controller
{
    /**
     * Redirect to IAM login
     */
    public function redirectToIAM()
    {
        $iamUrl = config('services.iam.url');
        $appKey = config('services.iam.app_key');
        
        return redirect("{$iamUrl}/login?app={$appKey}");
    }
    
    /**
     * Handle callback from IAM
     */
    public function handleCallback(Request $request)
    {
        $token = $request->query('token');
        
        if (!$token) {
            return redirect('/login')->with('error', 'Invalid SSO token');
        }
        
        // Verify token dengan IAM
        $response = Http::post(config('services.iam.url') . '/api/sso/verify', [
            'token' => $token,
            'include_user_data' => true,
        ]);
        
        if (!$response->successful()) {
            return redirect('/login')->with('error', 'Token verification failed');
        }
        
        $data = $response->json();
        
        // Get or create user berdasarkan NIP
        $user = User::firstOrCreate(
            ['nip' => $data['nip']],
            [
                'name' => $data['user']['basic']['name'],
                'email' => $data['user']['basic']['email'],
            ]
        );
        
        // Sync roles
        $roles = $data['user']['application']['roles'] ?? [];
        $user->syncRoles($roles);
        
        // Login user
        Auth::login($user);
        
        // Store token di session untuk API calls
        session(['iam_token' => $token]);
        
        return redirect('/dashboard');
    }
    
    /**
     * Logout and revoke token
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/');
    }
}
```

#### 3. Routes

**routes/web.php**:
```php
use App\Http\Controllers\Auth\SSOController;

Route::get('/auth/sso', [SSOController::class, 'redirectToIAM'])
    ->name('auth.sso');

Route::get('/auth/callback', [SSOController::class, 'handleCallback'])
    ->name('auth.callback');

Route::post('/logout', [SSOController::class, 'logout'])
    ->name('logout');
```

#### 4. Middleware untuk API Calls

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VerifyIAMToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = session('iam_token');
        
        if (!$token) {
            return redirect('/auth/sso');
        }
        
        // Verify token masih valid
        $response = Http::post(config('services.iam.url') . '/api/sso/introspect', [
            'token' => $token,
        ]);
        
        if (!$response->successful() || !$response->json('active')) {
            session()->forget('iam_token');
            return redirect('/auth/sso');
        }
        
        return $next($request);
    }
}
```

### JavaScript/React Client Implementation

```javascript
// services/ssoService.js
import axios from 'axios';

const IAM_URL = process.env.REACT_APP_IAM_URL;
const APP_KEY = process.env.REACT_APP_IAM_APP_KEY;

export const ssoService = {
  /**
   * Redirect to IAM login
   */
  redirectToLogin() {
    window.location.href = `${IAM_URL}/login?app=${APP_KEY}`;
  },

  /**
   * Verify SSO token
   */
  async verifyToken(token) {
    try {
      const response = await axios.post(`${IAM_URL}/api/sso/verify`, {
        token: token,
        include_user_data: true,
      });
      
      return response.data;
    } catch (error) {
      throw new Error('Token verification failed');
    }
  },

  /**
   * Get user info from token
   */
  async getUserInfo(token) {
    try {
      const response = await axios.get(`${IAM_URL}/api/sso/userinfo`, {
        headers: {
          'Authorization': `Bearer ${token}`,
        },
      });
      
      return response.data;
    } catch (error) {
      throw new Error('Failed to get user info');
    }
  },

  /**
   * Store token in localStorage
   */
  saveToken(token) {
    localStorage.setItem('iam_token', token);
  },

  /**
   * Get token from localStorage
   */
  getToken() {
    return localStorage.getItem('iam_token');
  },

  /**
   * Remove token
   */
  clearToken() {
    localStorage.removeItem('iam_token');
  },
};
```

```javascript
// components/AuthCallback.jsx
import React, { useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { ssoService } from '../services/ssoService';

function AuthCallback() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  useEffect(() => {
    const handleCallback = async () => {
      const token = searchParams.get('token');

      if (!token) {
        navigate('/login');
        return;
      }

      try {
        // Verify token
        const userData = await ssoService.verifyToken(token);

        // Save token
        ssoService.saveToken(token);

        // Save user data
        localStorage.setItem('user', JSON.stringify(userData.user));

        // Redirect to dashboard
        navigate('/dashboard');
      } catch (error) {
        console.error('SSO callback error:', error);
        navigate('/login');
      }
    };

    handleCallback();
  }, [searchParams, navigate]);

  return <div>Authenticating...</div>;
}

export default AuthCallback;
```

```javascript
// App.jsx routing
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import AuthCallback from './components/AuthCallback';
import Dashboard from './components/Dashboard';
import ProtectedRoute from './components/ProtectedRoute';

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/auth/callback" element={<AuthCallback />} />
        <Route 
          path="/dashboard" 
          element={
            <ProtectedRoute>
              <Dashboard />
            </ProtectedRoute>
          } 
        />
      </Routes>
    </BrowserRouter>
  );
}
```

---

## Migration Guide

### Untuk Client Apps yang Sudah Ada

#### 1. Update Login Form

**Sebelum**:
```html
<input type="email" name="email" placeholder="Email" />
<input type="password" name="password" placeholder="Password" />
```

**Sesudah**:
```html
<input type="text" name="nip" placeholder="NIP" />
<input type="password" name="password" placeholder="Password" />
```

#### 2. Update API Calls

**Sebelum**:
```javascript
// Mencari user berdasarkan email
const user = await User.findOne({ email: userData.email });
```

**Sesudah**:
```javascript
// Mencari user berdasarkan NIP
const user = await User.findOne({ nip: userData.nip });

// Email tetap tersedia jika diperlukan
if (userData.email) {
  // Handle email
}
```

#### 3. Update Database Schema

```sql
-- Tambahkan kolom NIP
ALTER TABLE users ADD COLUMN nip VARCHAR(255);

-- Buat index untuk NIP
CREATE UNIQUE INDEX users_nip_unique ON users(nip);

-- Ubah email menjadi nullable
ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NULL;
```

#### 4. Update User Model

**Laravel**:
```php
protected $fillable = [
    'nip',      // Tambahkan ini
    'name',
    'email',
    'password',
];

// Tambahkan username untuk login
public function username()
{
    return 'nip';
}
```

---

## Testing

### Test dengan cURL

#### 1. Test SSO Verify

```bash
curl -X POST https://iam.example.com/api/sso/verify \
  -H "Content-Type: application/json" \
  -d '{
    "token": "your_sso_token_here",
    "include_user_data": true
  }'
```

#### 2. Test Token Introspect

```bash
curl -X POST https://iam.example.com/api/sso/introspect \
  -H "Content-Type: application/json" \
  -d '{
    "token": "your_token_here"
  }'
```

#### 3. Test User Info

```bash
curl -X GET https://iam.example.com/api/sso/userinfo \
  -H "Authorization: Bearer your_token_here"
```

---

## Security Best Practices

### 1. Token Storage
- **Web Apps**: Gunakan HTTP-only cookies atau session storage
- **Mobile Apps**: Gunakan secure storage (Keychain/Keystore)
- **SPA**: Gunakan localStorage dengan XSS protection

### 2. Token Validation
- Selalu verify token di server-side
- Jangan trust token dari client tanpa verification
- Check expiration time

### 3. HTTPS Only
- Selalu gunakan HTTPS untuk semua komunikasi
- Jangan kirim token via URL query params kecuali untuk callback

### 4. State Parameter
- Gunakan random state parameter untuk prevent CSRF
- Verify state parameter di callback

---

## Troubleshooting

### Issue: Token Verification Failed

**Kemungkinan Penyebab**:
1. Token expired
2. Invalid token signature
3. Wrong application key
4. Network error

**Solution**:
- Check token expiration time
- Verify IAM_URL configuration
- Check network connectivity

### Issue: User Not Found

**Kemungkinan Penyebab**:
1. NIP tidak ada di database IAM
2. User not assigned to application

**Solution**:
- Verify user exists di IAM
- Check application role assignments

### Issue: Invalid Redirect URI

**Kemungkinan Penyebab**:
1. Callback URL tidak terdaftar di IAM
2. URL mismatch (http vs https)

**Solution**:
- Register callback URL di IAM application settings
- Ensure URL protocol matches

---

## Support & Contact

Untuk pertanyaan atau bantuan lebih lanjut:

- **Documentation**: `/docs`
- **API Reference**: `/docs/API-RESPONSE-FORMAT.md`
- **Repository**: Check project README

---

## Changelog

### Version 2.0 (Current)
- ✅ NIP sebagai primary identifier
- ✅ Email menjadi nullable
- ✅ Backward compatibility untuk email
- ✅ Updated token structure

### Version 1.0 (Legacy)
- ❌ Email sebagai primary identifier
- ❌ Email required

---

**Last Updated**: November 18, 2024
