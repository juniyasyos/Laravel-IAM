# SSO Controllers Update - Summary

## Perubahan Yang Dilakukan

### 1. **UserDataService Baru** (`app/Domain/Iam/Services/UserDataService.php`)

Service ini bertanggung jawab untuk mengumpulkan data user yang komprehensif:

**Methods:**
- `getUserData()`: Mengambil semua data user (roles, profiles, permissions, applications)
- `formatRolesForApplication()`: Format roles untuk aplikasi tertentu
- `formatAllApplicationsAndRoles()`: Format semua aplikasi dan roles
- `formatAccessProfiles()`: Format access profiles dengan detail roles
- `formatDirectRoles()`: Format role assignments langsung (bukan via profiles)
- `formatPermissions()`: Format summary permissions dan capabilities
- `extractCapabilities()`: Extract capabilities berdasarkan role slugs
- `getTokenPayload()`: Generate payload untuk JWT token

**Fitur:**
- ✅ Mendukung filter per application
- ✅ Include/exclude access profiles
- ✅ Menggabungkan direct roles + roles via profiles (effective roles)
- ✅ Menghitung statistics (total roles, system roles, custom roles)
- ✅ Extract capabilities otomatis (admin → full_access, manager → manage_team, dll)

---

### 2. **UserInfoController** - Updated

**Endpoint:** `GET /api/user-info`

**Perubahan:**
- ❌ Removed: `AppRegistryContract` dependency
- ✅ Added: `UserDataService` untuk data kompleks
- ✅ Added: Query parameter `include_profiles` (default: true)
- ✅ Response structure jauh lebih detail

**Response Structure:**
```json
{
  "sub": "1",
  "user": {
    "id": 1,
    "name": "...",
    "email": "...",
    "active": true,
    "applications": [...],  // atau "application" jika filter by app
    "access_profiles": [...],
    "direct_roles": [...],
  },
  "timestamp": "..."
}
```

---

### 3. **SSOController** - Enhanced

**Updated Methods:**

#### `handleAuthorizationCodeGrant()`
- ✅ Menambahkan `user` object di response token
- ✅ Include comprehensive user data setelah token generation
- ✅ Response sekarang: `{access_token, refresh_token, token_type, expires_in, user, issued_at}`

#### `introspect()`
- ✅ Mengambil user dari database
- ✅ Menambahkan `permissions` object dengan capabilities
- ✅ Response detail: `{active, sub, name, email, roles[], permissions{}, exp, iat}`

#### `userInfo()`
- ✅ Menggunakan `UserDataService` untuk data lengkap
- ✅ Response: `{sub, user{...}, token_info{issued_at, expires_at, app_key}}`
- ✅ Validate token type (harus `access` token)

**Constructor:**
```php
public function __construct(
    private JWTTokenService $jwtService,
    private UserDataService $userDataService  // NEW
) {}
```

---

### 4. **SsoVerifyController** - Enhanced

**Endpoint:** `POST /sso/verify`

**Perubahan:**
- ✅ Added: `include_user_data` parameter (boolean)
- ✅ Response struktur baru dengan `token_info` nested object
- ✅ Menampilkan `roles` dan `permissions` dari token payload
- ✅ Optional: fetch comprehensive user data jika diminta

**Response (tanpa user data):**
```json
{
  "email": "...",
  "token_info": {
    "sub": "1",
    "app": "app-crm",
    "issuer": "iam-server",
    "issued_at": "...",
    "expires_at": "..."
  },
  "roles": [...],
}
```

**Response (dengan user data):**
```json
{
  // ... same as above
  "user": {
    "id": 1,
    "name": "...",
    "applications": [...],
    "access_profiles": [...],
    "direct_roles": [...],
  }
}
```

---

### 5. **TokenService** - Enhanced

**Perubahan:**
- ✅ Added: `UserDataService` dependency
- ✅ Token payload sekarang include `roles` dan `permissions`
- ✅ Menggunakan `Application->getTokenExpirySeconds()` bukan global TTL
- ✅ Logging diperkaya dengan roles_count dan permissions_count

**Old Payload:**
```json
{
  "iss": "iam-server",
  "sub": "1",
  "email": "...",
  "app": "app-crm",
  "iat": 1234567890,
  "exp": 1234571490
}
```

**New Payload:**
```json
{
  "iss": "iam-server",
  "sub": "1",
  "email": "...",
  "name": "...",
  "app": "app-crm",
  "roles": [
    {
      "id": 5,
      "slug": "sales-manager",
      "name": "Sales Manager",
      "is_system": false,
      "description": "..."
    }
  ],
  "iat": 1234567890,
  "exp": 1234571490
}
```

---

## Data Structure Dijelaskan

### Effective Roles
Kombinasi dari:
1. **Direct roles**: User → ApplicationRole (via `iam_user_application_roles`)
2. **Profile roles**: User → AccessProfile → ApplicationRole (via `user_access_profiles` + `access_profile_role_iam_map`)

Method: `User->effectiveApplicationRoles()`

### Access Profiles
Group of roles yang bisa di-assign ke user untuk mempermudah management.

**Benefits:**
- Assign banyak roles sekaligus
- Centralized role management
- Easier onboarding (assign profile bukan individual roles)

### Direct Roles
Role assignments langsung tanpa melalui access profile.

**Use case:**
- Special permissions
- Temporary access
- Override profile roles

### Permissions/Capabilities
Auto-extracted dari role slugs:

| Role | Capabilities |
|------|-------------|
| `admin` | `full_access`, `manage_users`, `manage_settings` |
| `manager` | `manage_team`, `view_reports` |
| `viewer` | `read_only` |

**Extensible**: Tambahkan logic di `UserDataService->extractCapabilities()`

---

## Breaking Changes

### 1. UserInfoController
- ❌ Tidak lagi menggunakan `AppRegistryContract`
- ❌ Response structure berubah total
- ❌ `claims` property dihapus

**Migration:**
```javascript
// Old
response.claims.apps
response.claims.roles

// New
response.user.accessible_apps
response.user.applications[0].roles
```

### 2. Token Payload
- ✅ Backward compatible (masih ada iss, sub, email, app, iat, exp)
- ✅ Added: name, roles, permissions

**Migration:**
Clients tidak perlu update, tapi bisa memanfaatkan data baru.

### 3. Verify Response
- ✅ Mostly backward compatible
- ✅ `email` masih di root level
- ✅ Added: token_info, roles, permissions

---

## Testing

### Manual Test Scenarios

1. **Test User Info**
```bash
curl -H "Authorization: Bearer {token}" \
  "http://localhost/api/user-info"
```

2. **Test User Info with App Filter**
```bash
curl -H "Authorization: Bearer {token}" \
  "http://localhost/api/user-info?app=app-crm"
```

3. **Test User Info without Profiles**
```bash
curl -H "Authorization: Bearer {token}" \
  "http://localhost/api/user-info?include_profiles=false"
```

4. **Test Token Verify with User Data**
```bash
curl -X POST http://localhost/sso/verify \
  -H "Content-Type: application/json" \
  -d '{"token": "...", "include_user_data": true}'
```

5. **Test OAuth Flow**
```bash
# 1. Authorize
GET /sso/authorize?app_key=app-crm&redirect_uri=https://...&state=xyz

# 2. Exchange code for token
POST /sso/token
{
  "grant_type": "authorization_code",
  "app_key": "app-crm",
  "app_secret": "...",
  "code": "...",
  "redirect_uri": "..."
}

# Response now includes user object!
```

---

## Files Modified

1. ✅ `app/Domain/Iam/Services/UserDataService.php` - **CREATED**
2. ✅ `app/Http/Controllers/UserInfoController.php` - **UPDATED**
3. ✅ `app/Http/Controllers/SSOController.php` - **UPDATED**
4. ✅ `app/Http/Controllers/Sso/SsoVerifyController.php` - **UPDATED**
5. ✅ `app/Services/Sso/TokenService.php` - **UPDATED**
6. ✅ `docs/API-RESPONSE-FORMAT.md` - **CREATED**

---

## Next Steps

### Recommended Actions:

1. **Update Client Applications**
   - Update token parsing untuk handle roles & permissions
   - Leverage capabilities untuk authorization logic
   - Test backward compatibility

2. **Add Tests**
   ```php
   // tests/Feature/UserDataServiceTest.php
   test('can get user data with all roles');
   test('can filter by application');
   test('can get direct roles only');
   test('can get effective roles');
   
   // tests/Feature/SsoControllerTest.php
   test('token response includes user data');
   test('introspect returns permissions');
   test('verify with user data works');
   ```

3. **Extend Capabilities**
   Edit `UserDataService->extractCapabilities()` untuk tambah logic:
   ```php
   if ($slugs->contains('editor')) {
       $capabilities[] = 'create_content';
       $capabilities[] = 'edit_content';
       $capabilities[] = 'delete_content';
   }
   ```

4. **Add Caching** (Optional)
   ```php
   public function getUserData(User $user, ...): array
   {
       return Cache::remember(
           "user_data:{$user->id}:{$application?->id}",
           300, // 5 minutes
           fn() => $this->buildUserData($user, $application, ...)
       );
   }
   ```

5. **Add Rate Limiting**
   ```php
   Route::middleware(['throttle:60,1'])->group(function () {
       Route::get('/api/user-info', ...);
       Route::post('/sso/verify', ...);
   });
   ```

---

## Performance Considerations

### Database Queries

**Before:** ~2-3 queries
- Get user
- Get basic info

**After:** ~5-7 queries
- Get user
- Get effective roles (with eager loading)
- Get access profiles (with roles)
- Get applications

**Optimizations Applied:**
- ✅ Eager loading relations (`->with('application')`)
- ✅ Query filtering in database (`->where('is_active', true)`)
- ✅ Collection operations (avoid N+1)

**Recommended:**
- Add caching layer
- Add database indexes on foreign keys
- Consider Redis for session data

---

## Security Notes

1. **Token Payload Size**: Increased due to roles array
   - Monitor JWT size (max ~4KB for headers)
   - Consider using token references instead

2. **Data Exposure**: More data in tokens
   - Review what's safe to include in JWT
   - Consider separate endpoints for sensitive data

3. **Validation**: All controllers validate input
   - Application existence
   - Application enabled status
   - Token validity
   - User existence

---

## Conclusion

✅ **All SSO controllers updated**
✅ **Response data much more comprehensive**
✅ **Uses DDD structure (Domain models)**
✅ **Backward compatible where possible**
✅ **Well documented**
✅ **No syntax errors**
✅ **Ready for production** (after testing)

**Complexity Level:** ⭐⭐⭐⭐⭐ (Very Complex)
- Multiple nested objects
- Effective roles calculation
- Profile inheritance
- Capabilities extraction
- Multiple use cases supported
