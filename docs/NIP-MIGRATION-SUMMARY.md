# NIP-Based Authentication - Migration Summary

## 🎯 Overview

Sistem IAM telah berhasil dioptimalkan untuk menggunakan **NIP (Nomor Induk Pegawai)** sebagai identifier utama menggantikan email. Perubahan ini mempengaruhi semua komponen autentikasi, SSO, OAuth, dan authorization berbasis token.

---

## ✅ Perubahan yang Telah Dilakukan

### 1. **Database & Model**
- ✅ Migration untuk menambahkan kolom `nip` ke tabel `users`
- ✅ NIP dibuat **unique** dan **required**
- ✅ Email diubah menjadi **nullable** (opsional)
- ✅ User model sudah include `nip` di `$fillable`

**Migration**: `database/migrations/2025_11_17_070845_add_nip_to_users_table.php`

### 2. **Configuration**
- ✅ `config/fortify.php` - Username diubah dari `email` ke `nip`
- ✅ Email field di Fortify config diubah ke `nip`
- ✅ Lowercase usernames tetap enabled

### 3. **Authentication Controllers**

#### `app/Http/Requests/Auth/LoginRequest.php`
- ✅ Validation rules menggunakan `nip` sebagai field required
- ✅ `validateCredentials()` menggunakan NIP untuk query user
- ✅ Error messages disesuaikan untuk NIP
- ✅ Throttle key menggunakan NIP

#### `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- ✅ Login attempt logging menggunakan NIP
- ✅ Context log menyertakan `login_method: 'nip'`

### 4. **SSO Controllers**

#### `app/Http/Controllers/Sso/SsoRedirectController.php`
- ✅ Logging menggunakan `user_nip` menggantikan `user_email`
- ✅ Additional context menggunakan NIP

#### `app/Http/Controllers/Sso/SsoVerifyController.php`
- ✅ Response payload menyertakan `nip` sebagai field utama
- ✅ Email tetap disertakan untuk backward compatibility
- ✅ Root level includes both `nip` dan `email`

### 5. **Token Services**

#### `app/Services/Sso/TokenService.php`
- ✅ Token payload menyertakan field `nip`
- ✅ Field `email` tetap ada untuk compatibility
- ✅ Logging menggunakan `user_nip`

#### `app/Domain/Iam/Services/TokenBuilder.php`
- ✅ `buildClaimsForUser()` menyertakan NIP dari user model
- ✅ Token claims includes `nip` field

### 6. **Token Structure**

#### `app/Domain/Iam/DataTransferObjects/TokenClaims.php`
- ✅ Constructor parameter ditambahkan `nip` (nullable string)
- ✅ `toPayload()` menyertakan `nip` dalam JWT payload
- ✅ `fromArray()` menghandle `nip` field
- ✅ Email field diubah menjadi nullable

### 7. **SSO Token Controller**

#### `app/Domain/Iam/Http/Controllers/SsoTokenController.php`
- ✅ `issueToken()` response menyertakan `nip`
- ✅ `introspect()` response menyertakan `nip`
- ✅ `userinfo()` response menyertakan `nip`

---

## 📦 Token Structure (New)

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

### Key Changes
- ⭐ **nip**: PRIMARY IDENTIFIER (new, required)
- 🔄 **email**: OPTIONAL (nullable, for compatibility)
- ✅ All other fields remain the same

---

## 🔗 API Response Format (Updated)

### SSO Verify Endpoint
**POST** `/api/sso/verify`

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
  "roles": ["mahasiswa", "admin"],
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
      "roles": ["mahasiswa", "admin"]
    }
  }
}
```

### Token Introspect Endpoint
**POST** `/api/sso/introspect`

```json
{
  "active": true,
  "sub": "123",
  "nip": "198501012010121001",
  "email": "ahmad@example.com",
  "name": "Ahmad Ilyas",
  "apps": ["portal-mahasiswa", "siakad"],
  "roles_by_app": {
    "portal-mahasiswa": ["mahasiswa", "admin"]
  },
  "iss": "https://iam.example.com",
  "iat": 1700294400,
  "exp": 1700298000
}
```

### User Info Endpoint
**GET** `/api/sso/userinfo`

```json
{
  "sub": "123",
  "nip": "198501012010121001",
  "email": "ahmad@example.com",
  "name": "Ahmad Ilyas",
  "apps": ["portal-mahasiswa", "siakad"],
  "roles_by_app": {
    "portal-mahasiswa": ["mahasiswa", "admin"]
  }
}
```

---

## 🔄 Backward Compatibility

### Email Field
- Email **tetap tersedia** di semua response
- Email **nullable** - tidak wajib diisi
- Client apps yang masih menggunakan email tetap berfungsi
- Recommended: Migrate ke NIP sebagai primary identifier

### Migration Path
1. **Phase 1** (Current): Dual support - both NIP and email available
2. **Phase 2** (Future): Deprecate email requirement
3. **Phase 3** (Later): Email completely optional

---

## 📚 Documentation

### New Documentation Files

1. **[SSO-CLIENT-NIP-INTEGRATION.md](./SSO-CLIENT-NIP-INTEGRATION.md)**
   - Panduan lengkap integrasi SSO dengan NIP
   - Authentication flow diagram
   - Client implementation examples (Laravel, React)
   - Migration guide dari email ke NIP
   - Security best practices
   - Troubleshooting guide

2. **[SSO-NIP-QUICK-START.md](./SSO-NIP-QUICK-START.md)**
   - Quick start guide (5 menit setup)
   - Login form examples
   - Token structure
   - API endpoints reference
   - Common issues & solutions
   - Example implementations

### Existing Documentation (Still Valid)
- **[IAM-SSO-RBAC-DOCUMENTATION.md](./IAM-SSO-RBAC-DOCUMENTATION.md)** - Architecture & RBAC
- **[API-RESPONSE-FORMAT.md](./API-RESPONSE-FORMAT.md)** - API response format
- **[CLIENT-INTEGRATION.md](./CLIENT-INTEGRATION.md)** - Client integration guide
- **[QUICK-REFERENCE.md](./QUICK-REFERENCE.md)** - Quick reference

---

## 🧪 Testing

### Login Test
```bash
# Via Web Form
URL: http://localhost/login
Fields:
- nip: "198501012010121001"
- password: "password"
```

### SSO Flow Test
```bash
# 1. Redirect to login with app key
http://localhost/login?app=portal-mahasiswa

# 2. Login with NIP
nip: "198501012010121001"
password: "password"

# 3. Check callback URL receives token
http://your-app/callback?token=eyJhbGc...
```

### API Test
```bash
# Verify token
curl -X POST http://localhost/api/sso/verify \
  -H "Content-Type: application/json" \
  -d '{
    "token": "your_token_here",
    "include_user_data": true
  }'

# Check response includes nip field
```

---

## 🔒 Security Notes

### NIP Format
- Type: String
- Format: Flexible (alphanumeric)
- Validation: Required, unique
- Case: Configurable (lowercase recommended)

### Login Security
- Rate limiting: 5 attempts per NIP per IP
- Throttle key: `{nip}|{ip}`
- Lockout: 60 seconds after rate limit
- Session: Regenerated after login

### Token Security
- Algorithm: HS256 (HMAC SHA-256)
- TTL: Configurable (default: 300 seconds)
- Issuer: Validated on verification
- Signature: Verified on every request

---

## 🚀 Client App Migration Guide

### Step 1: Update Database
```sql
ALTER TABLE users ADD COLUMN nip VARCHAR(255) NOT NULL;
CREATE UNIQUE INDEX users_nip_unique ON users(nip);
ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NULL;
```

### Step 2: Update Login Form
```html
<!-- Before -->
<input type="email" name="email" required />

<!-- After -->
<input type="text" name="nip" placeholder="NIP" required />
```

### Step 3: Update User Lookup
```php
// Before
$user = User::where('email', $data['email'])->first();

// After
$user = User::where('nip', $data['nip'])->first();
```

### Step 4: Update SSO Callback
```php
// Handle both nip and email for compatibility
$user = User::firstOrCreate(
    ['nip' => $data['nip']],
    [
        'name' => $data['user']['basic']['name'],
        'email' => $data['user']['basic']['email'] ?? null,
    ]
);
```

---

## 📊 Impact Summary

### Files Modified: 9
1. `config/fortify.php`
2. `app/Http/Controllers/Sso/SsoRedirectController.php`
3. `app/Http/Controllers/Sso/SsoVerifyController.php`
4. `app/Services/Sso/TokenService.php`
5. `app/Domain/Iam/Services/TokenBuilder.php`
6. `app/Domain/Iam/DataTransferObjects/TokenClaims.php`
7. `app/Domain/Iam/Http/Controllers/SsoTokenController.php`
8. `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
9. `app/Http/Requests/Auth/LoginRequest.php`

### Files Created: 2
1. `docs/SSO-CLIENT-NIP-INTEGRATION.md`
2. `docs/SSO-NIP-QUICK-START.md`

### Database Changes: 1
- Migration: `add_nip_to_users_table.php` (already exists)

---

## ✅ Checklist Post-Migration

- [x] Database migration executed
- [x] Configuration updated
- [x] Authentication controllers updated
- [x] SSO controllers updated
- [x] Token services updated
- [x] Token structure updated
- [x] API responses updated
- [x] Documentation created
- [ ] Client apps migrated (in progress)
- [ ] Testing completed
- [ ] Production deployment

---

## 🆘 Support

### Common Issues

**Issue**: Login form masih menggunakan email
- **Fix**: Update form field dari `email` ke `nip`

**Issue**: Token verification gagal
- **Fix**: Check token structure includes `nip` field

**Issue**: User tidak bisa login
- **Fix**: Verify user memiliki NIP di database

### Contact
- Documentation: `/docs/`
- API Reference: Check individual doc files
- Repository: Project root

---

## 📅 Timeline

- **November 17, 2024**: Migration planning
- **November 18, 2024**: Implementation complete ✅
- **Next**: Client app migration
- **Future**: Production rollout

---

**Version**: 2.0.0  
**Status**: ✅ Complete  
**Last Updated**: November 18, 2024
