# SSO Quick Start - Autentikasi Berbasis NIP

## 🚀 Quick Integration (5 Menit)

### 1. Redirect User ke IAM Login

```javascript
// JavaScript
window.location.href = 'https://iam.example.com/login?app=YOUR_APP_KEY';
```

```php
// Laravel
return redirect('https://iam.example.com/login?app=' . config('iam.app_key'));
```

### 2. Terima Token di Callback

IAM akan redirect ke:
```
https://your-app.com/callback?token=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

### 3. Verify Token

```bash
POST https://iam.example.com/api/sso/verify
Content-Type: application/json

{
  "token": "your_token_here",
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
    "expires_at": "2024-11-18T10:05:00+07:00"
  },
  "roles": ["mahasiswa", "admin"],
  "user": {
    "basic": {
      "id": 123,
      "nip": "198501012010121001",
      "name": "Ahmad Ilyas",
      "email": "ahmad@example.com"
    },
    "application": {
      "roles": ["mahasiswa", "admin"]
    }
  }
}
```

### 4. Login User di Client App

```php
// Laravel Example
$user = User::firstOrCreate(
    ['nip' => $data['nip']],
    [
        'name' => $data['user']['basic']['name'],
        'email' => $data['user']['basic']['email'],
    ]
);

Auth::login($user);
```

```javascript
// JavaScript Example
localStorage.setItem('iam_token', token);
localStorage.setItem('user', JSON.stringify(userData.user));
```

---

## 📝 Login Credentials

**Field yang digunakan**:
- ✅ **NIP** (Nomor Induk Pegawai) - **REQUIRED**
- ✅ **Password** - **REQUIRED**
- ❌ ~~Email~~ - Optional (nullable)

### Login Form Example

```html
<form action="/login" method="POST">
  <input type="text" name="nip" placeholder="NIP" required />
  <input type="password" name="password" placeholder="Password" required />
  <button type="submit">Login</button>
</form>
```

---

## 🔑 Token Structure

### JWT Payload
```json
{
  "iss": "https://iam.example.com",
  "sub": "123",
  "nip": "198501012010121001",
  "email": "user@example.com",
  "name": "Ahmad Ilyas",
  "app": "portal-mahasiswa",
  "roles": ["mahasiswa", "admin"],
  "iat": 1700294400,
  "exp": 1700298000
}
```

### Key Fields
- **sub**: User ID
- **nip**: Nomor Induk Pegawai (PRIMARY IDENTIFIER) ⭐
- **email**: Email (optional, for compatibility)
- **roles**: Array of role slugs untuk aplikasi
- **exp**: Token expiration

---

## 🔗 API Endpoints

### 1. SSO Verify (Recommended)
```
POST /api/sso/verify
```
Verify token dan dapatkan user data lengkap.

### 2. Token Introspect
```
POST /api/sso/introspect
```
Check token validity dan claims.

### 3. User Info
```
GET /api/sso/userinfo
Authorization: Bearer {token}
```
Get user information dari token.

### 4. Token Issue (Authenticated)
```
POST /api/sso/token/issue
Authorization: Bearer {token}
```
Issue new token untuk authenticated user.

---

## ⚙️ Configuration

### Environment Variables

```env
# Client App Configuration
IAM_URL=https://iam.example.com
IAM_APP_KEY=your-app-key
IAM_APP_SECRET=your-app-secret
IAM_CALLBACK_URL=https://your-app.com/auth/callback
```

### Application Registration

Registrasi aplikasi di IAM server dengan:
- **App Key**: Unique identifier aplikasi
- **App Secret**: Secret key untuk API calls
- **Callback URL**: URL untuk menerima token
- **Token TTL**: Expiration time (default: 300 seconds)

---

## 🔒 Security Notes

1. **Always use HTTPS** untuk semua komunikasi
2. **Verify token di server-side** - jangan trust client
3. **Store token securely**:
   - Web: HTTP-only cookies atau session
   - Mobile: Secure storage (Keychain/Keystore)
4. **Check token expiration** sebelum digunakan
5. **Regenerate token** jika expired

---

## 🐛 Common Issues

### Token Verification Failed
- ✅ Check token belum expired
- ✅ Verify IAM_URL configuration
- ✅ Check network connectivity

### User Login Failed
- ✅ Pastikan menggunakan field `nip`, bukan `email`
- ✅ Verify NIP format (string)
- ✅ Check password correctness

### Invalid Redirect URI
- ✅ Register callback URL di IAM
- ✅ Match protocol (http/https)

---

## 📚 Full Documentation

Lihat dokumentasi lengkap di:
- **[SSO Client Integration Guide](./SSO-CLIENT-NIP-INTEGRATION.md)** - Panduan lengkap integrasi
- **[API Response Format](./API-RESPONSE-FORMAT.md)** - Format response API
- **[Quick Reference](./QUICK-REFERENCE.md)** - Reference cepat

---

## 💡 Migration dari Email ke NIP

### Database Update
```sql
-- Tambahkan kolom NIP
ALTER TABLE users ADD COLUMN nip VARCHAR(255) NOT NULL;
CREATE UNIQUE INDEX users_nip_unique ON users(nip);

-- Ubah email menjadi nullable
ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NULL;
```

### Code Update
```php
// Sebelum (Email-based)
User::where('email', $data['email'])->first();

// Sesudah (NIP-based)
User::where('nip', $data['nip'])->first();
```

### Form Update
```html
<!-- Sebelum -->
<input type="email" name="email" />

<!-- Sesudah -->
<input type="text" name="nip" placeholder="Nomor Induk Pegawai" />
```

---

## 🎯 Example Implementation

### Complete Laravel Controller

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
    public function redirectToIAM()
    {
        return redirect(
            config('services.iam.url') . '/login?app=' . config('services.iam.app_key')
        );
    }
    
    public function handleCallback(Request $request)
    {
        $token = $request->query('token');
        
        if (!$token) {
            return redirect('/login')->with('error', 'Invalid token');
        }
        
        // Verify token
        $response = Http::post(config('services.iam.url') . '/api/sso/verify', [
            'token' => $token,
            'include_user_data' => true,
        ]);
        
        if (!$response->successful()) {
            return redirect('/login')->with('error', 'Token verification failed');
        }
        
        $data = $response->json();
        
        // Get or create user dengan NIP
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
        
        // Login
        Auth::login($user);
        session(['iam_token' => $token]);
        
        return redirect('/dashboard');
    }
}
```

### Complete React Component

```javascript
// SSOService.js
export const handleSSOCallback = async (token) => {
  try {
    const response = await axios.post(`${IAM_URL}/api/sso/verify`, {
      token: token,
      include_user_data: true,
    });
    
    const userData = response.data;
    
    // Store token dan user data
    localStorage.setItem('iam_token', token);
    localStorage.setItem('user', JSON.stringify(userData.user));
    
    return userData;
  } catch (error) {
    throw new Error('SSO verification failed');
  }
};

// AuthCallback.jsx
import { useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { handleSSOCallback } from './SSOService';

function AuthCallback() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  useEffect(() => {
    const token = searchParams.get('token');
    
    if (!token) {
      navigate('/login');
      return;
    }

    handleSSOCallback(token)
      .then(() => navigate('/dashboard'))
      .catch(() => navigate('/login'));
  }, []);

  return <div>Authenticating...</div>;
}
```

---

**Last Updated**: November 18, 2024  
**Version**: 2.0 (NIP-based Authentication)
