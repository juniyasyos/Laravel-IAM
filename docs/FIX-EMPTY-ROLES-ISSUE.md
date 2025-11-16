# Fix Empty Roles & Permission Middleware Issue

**Date**: November 16, 2025  
**Issue**: Roles array kosong dalam JWT token + Middleware masih menggunakan permissions  
**Status**: ✅ RESOLVED

---

## Masalah yang Ditemukan

### 1. ❌ Roles Kosong dalam Token
```json
{
  "roles": [],  // KOSONG!
  "permissions": []
}
```

**Root Cause**: `JWTTokenService` menggunakan **Spatie Permission** (`getRoleNames()`) bukan **IAM ApplicationRoles**

### 2. ❌ Middleware Masih Gunakan Permissions
- `CheckIAMPermission` middleware mengecek `iam_user_permissions`
- `VerifyIAMAccessToken` menyimpan `iam_user_permissions` di request
- Permissions bukan tanggung jawab IAM!

---

## Solusi yang Diterapkan

### 1. ✅ Fix JWTTokenService - Gunakan IAM Roles

**File**: `app/Services/JWTTokenService.php`

**Before** (SALAH - Gunakan Spatie):
```php
public function generateAccessToken(User $user, Application $application): string
{
    $payload = [
        'roles' => $this->getUserRoles($user),      // ❌ Spatie getRoleNames()
        'permissions' => $this->getUserPermissions($user), // ❌ Not IAM!
    ];
}

private function getUserRoles(User $user): array
{
    return $user->getRoleNames()->toArray(); // ❌ Spatie!
}
```

**After** (BENAR - Gunakan IAM):
```php
public function generateAccessToken(User $user, Application $application): string
{
    $payload = [
        'roles' => $this->getUserRolesForApplication($user, $application), // ✅ IAM!
        // No permissions field
    ];
}

private function getUserRolesForApplication(User $user, Application $application): array
{
    // Get effective roles (direct + via access profiles) for this application
    $roles = $user->effectiveApplicationRoles()
        ->with('application')
        ->whereHas('application', function ($query) use ($application) {
            $query->where('id', $application->id);
        })
        ->get();

    return $roles->map(function ($role) {
        return [
            'id' => $role->id,
            'slug' => $role->slug,
            'name' => $role->name,
            'is_system' => $role->is_system,
            'description' => $role->description,
        ];
    })->toArray();
}
```

**Key Changes**:
- ✅ Menggunakan `effectiveApplicationRoles()` (IAM)
- ✅ Filter berdasarkan application_id
- ✅ Return array of role objects (bukan string slugs)
- ✅ Include: id, slug, name, is_system, description
- ✅ Hapus permissions sepenuhnya

---

### 2. ✅ Fix Middleware - Roles Only

#### A. VerifyIAMAccessToken.php

**Before**:
```php
'iam_user_permissions' => $decoded->permissions ?? [],
```

**After**:
```php
'iam_user_roles' => $decoded->roles ?? [],
```

#### B. CheckIAMPermission.php

**Before** (Cek Permissions):
```php
/**
 * Middleware untuk validasi permission berdasarkan IAM token.
 * Gunakan: ->middleware('iam.permission:create-post,edit-post')
 */
class CheckIAMPermission
{
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $userPermissions = $request->get('iam_user_permissions', []);
        
        if (empty($userPermissions)) {
            return response()->json(['message' => 'No permissions found'], 403);
        }
        
        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions)) {
                $hasPermission = true;
            }
        }
        // ...
    }
}
```

**After** (Cek Roles):
```php
/**
 * Middleware untuk validasi role berdasarkan IAM token.
 * Gunakan: ->middleware('iam.role:admin,doctor')
 * 
 * Note: Permissions adalah tanggung jawab client application.
 * Middleware ini hanya validasi roles dari IAM.
 */
class CheckIAMPermission
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $userRoles = $request->get('iam_user_roles', []);
        
        if (empty($userRoles)) {
            return response()->json(['message' => 'No roles found'], 403);
        }
        
        // Extract role slugs from role objects
        $userRoleSlugs = collect($userRoles)->pluck('slug')->toArray();
        
        foreach ($roles as $role) {
            if (in_array($role, $userRoleSlugs)) {
                $hasRole = true;
            }
        }
        // ...
    }
}
```

**Key Changes**:
- ✅ Rename parameter dari `$permissions` → `$roles`
- ✅ Ambil `iam_user_roles` bukan `iam_user_permissions`
- ✅ Extract slug dari role objects
- ✅ Check role slugs (admin, doctor, dll)
- ✅ Update error messages

---

## Hasil Setelah Fix

### ✅ Token Sekarang Berisi Roles

```json
{
  "access_token_payload": {
    "sub": 2,
    "name": "Dr. John Doe",
    "email": "doctor@gmail.com",
    "app_key": "siimut",
    "roles": [
      {
        "id": 3,
        "slug": "admin",
        "name": "Administrator SIIMUT",
        "is_system": true,
        "description": "Full administrative access to SIIMUT system"
      },
      {
        "id": 4,
        "slug": "doctor",
        "name": "Doctor",
        "is_system": false,
        "description": "Medical doctor with patient and prescription access"
      },
      {
        "id": 7,
        "slug": "viewer",
        "name": "Viewer",
        "is_system": false,
        "description": "Read-only access to patient records"
      }
    ],
    "type": "access"
  },
  "user_info": {
    "roles": [
      {
        "id": 3,
        "slug": "admin",
        "name": "Administrator SIIMUT"
      },
      {
        "id": 4,
        "slug": "doctor",
        "name": "Doctor"
      },
      {
        "id": 7,
        "slug": "viewer",
        "name": "Viewer"
      }
    ]
  }
}
```

**Tidak ada field `permissions` lagi!** ✅

---

## Database Structure

### Roles untuk User `doctor@gmail.com` di App `siimut`:

```
Direct Roles (iam_user_application_roles): 4 roles
└── user_id: 2
    ├── role_id: 3 → admin
    ├── role_id: 4 → doctor
    └── ... (2 more)

Access Profile Roles (via user_access_profiles): 2 roles
└── user_id: 2 → access_profile_id → roles
    ├── role_id: 7 → viewer
    └── ... (1 more)

Total Effective Roles: 6 roles (direct + profile)
Filter by app_key='siimut': 3 roles (admin, doctor, viewer)
```

**Metode yang Digunakan**:
```php
$user->effectiveApplicationRoles()  // Gabungan direct + profile roles
    ->whereHas('application', fn($q) => $q->where('id', $app->id))
    ->get();
```

---

## Penggunaan Middleware

### Before (SALAH)
```php
Route::get('/api/posts', function () {
    // ...
})->middleware('iam.permission:create-post,edit-post');  // ❌ Permission!
```

### After (BENAR)
```php
Route::get('/api/posts', function () {
    // Check role dari IAM
    $userRoles = request()->get('iam_user_roles', []);
    $roleSlugs = collect($userRoles)->pluck('slug')->toArray();
    
    // Client application mapping role → permissions
    $permissions = [];
    if (in_array('admin', $roleSlugs)) {
        $permissions = ['*'];  // All permissions
    } elseif (in_array('doctor', $roleSlugs)) {
        $permissions = ['patient.read', 'patient.write', 'prescription.create'];
    }
    
    // Client logic checks permissions
    if (!in_array('patient.write', $permissions)) {
        abort(403);
    }
    
    // ...
})->middleware('iam.role:admin,doctor');  // ✅ Role check!
```

**Atau lebih simple**:
```php
Route::get('/api/admin', function () {
    // Only admin role
})->middleware('iam.role:admin');

Route::get('/api/medical', function () {
    // Admin or doctor role
})->middleware('iam.role:admin,doctor');
```

---

## Testing

### Test Token Generation
```bash
curl "http://localhost:8000/test-sso-complete-flow?user=doctor@gmail.com&app=siimut"
```

**Expected Output**:
- ✅ `access_token_payload.roles` array populated (3 roles)
- ✅ `user_info.roles` array populated (3 roles)
- ✅ No `permissions` field anywhere

### Test via Tinker
```php
php artisan tinker

$user = User::where('email', 'doctor@gmail.com')->first();
$app = Application::where('app_key', 'siimut')->first();
$service = new \App\Services\JWTTokenService();
$token = $service->generateAccessToken($user, $app);
$decoded = $service->verifyToken($token);

// Should show 3 roles
print_r($decoded->roles);
```

---

## Migration Notes

### Files Modified

1. ✅ `app/Services/JWTTokenService.php`
   - Changed: `getUserRoles()` → `getUserRolesForApplication()`
   - Removed: `getUserPermissions()`
   - Uses: IAM `effectiveApplicationRoles()`

2. ✅ `app/Http/Middleware/VerifyIAMAccessToken.php`
   - Changed: `iam_user_permissions` → `iam_user_roles`

3. ✅ `app/Http/Middleware/CheckIAMPermission.php`
   - Changed: Check permissions → Check roles
   - Updated: Error messages and logic

### Breaking Changes

If you have routes using `iam.permission:xxx`, you must update to:
```php
// Before
->middleware('iam.permission:create-post')

// After - middleware checks role
->middleware('iam.role:admin')

// Then in controller, map role to permission
```

---

## Summary

| Aspect | Before | After |
|--------|--------|-------|
| **Roles in Token** | Empty `[]` | ✅ Populated (3 roles) |
| **Data Source** | Spatie Permission | ✅ IAM ApplicationRoles |
| **Permissions in Token** | Present (wrong!) | ✅ Removed |
| **Middleware** | Check permissions | ✅ Check roles |
| **Role Objects** | String slugs only | ✅ Full objects (id, slug, name) |

---

**Status**: ✅ COMPLETE  
**Tested**: Token generation works, roles populated correctly  
**Impact**: Breaking change for middleware usage
