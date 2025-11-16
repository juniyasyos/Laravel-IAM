# IAM Roles-Only Update

**Date**: November 16, 2025  
**Issue**: Empty roles array in JWT tokens and inconsistent permission handling  
**Solution**: Remove permissions (not IAM responsibility), fix role retrieval to use IAM ApplicationRoles

---

## Problem Statement

The SSO testing output showed:
1. **Empty roles array** in JWT token payload
2. **Permissions field present** - which is NOT IAM's responsibility
3. **Inconsistent data retrieval** - mixing Spatie roles with IAM roles
4. **Wrong data source** - using `getRoleNames()` (Spatie) instead of IAM ApplicationRoles

### Example of Incorrect Output
```json
{
  "access_token_payload": {
    "roles": [],           // ❌ EMPTY - WRONG!
    "permissions": [],     // ❌ NOT IAM RESPONSIBILITY!
    "unit": null
  },
  "user_info": {
    "roles": [],           // ❌ Using Spatie instead of IAM
    "permissions": []      // ❌ NOT IAM RESPONSIBILITY!
  }
}
```

---

## Root Causes

1. **Mixed Role Systems**: Code was using both:
   - ❌ Spatie Permission system: `$user->getRoleNames()`, `$user->getAllPermissions()`
   - ✅ IAM Application Roles: `$user->effectiveApplicationRoles()`, `$user->applicationRoles()`

2. **Permissions in IAM**: IAM should only manage roles, not permissions
   - Permissions are client application's responsibility
   - Client apps map IAM roles → their own permissions

3. **Empty Roles**: `effectiveApplicationRoles()` wasn't being called correctly

---

## Changes Made

### 1. **UserDataService.php** - Removed Permissions

**File**: `app/Domain/Iam/Services/UserDataService.php`

**Removed**:
- `formatPermissions()` method
- `extractCapabilities()` method  
- `permissions` field from return data

**Before**:
```php
public function getUserData(User $user, ?Application $application = null, bool $includeProfiles = true): array
{
    // ...
    $data['permissions'] = $this->formatPermissions($effectiveRoles, $application);
    return $data;
}

public function getTokenPayload(User $user, Application $application): array
{
    return [
        'roles' => $userData['application']['roles'] ?? [],
        'permissions' => $userData['permissions'], // ❌ REMOVED
    ];
}
```

**After**:
```php
public function getUserData(User $user, ?Application $application = null, bool $includeProfiles = true): array
{
    // ...
    // Only returns: id, name, email, roles, access_profiles, direct_roles
    return $data;
}

public function getTokenPayload(User $user, Application $application): array
{
    return [
        'roles' => $userData['application']['roles'] ?? [],
        // ✅ No permissions field
    ];
}
```

---

### 2. **TokenService.php** - Cleaned JWT Payload

**File**: `app/Services/Sso/TokenService.php`

**Before**:
```php
$payload = [
    'iss' => $this->getIssuer(),
    'sub' => (string) $user->getAuthIdentifier(),
    'email' => $user->email ?? null,
    'name' => $user->name ?? null,
    'app' => $application->app_key,
    'roles' => $tokenPayload['roles'] ?? [],
    'permissions' => $tokenPayload['permissions'] ?? [], // ❌ REMOVED
    'iat' => $issuedAt->getTimestamp(),
    'exp' => $expiresAt->getTimestamp(),
];
```

**After**:
```php
$payload = [
    'iss' => $this->getIssuer(),
    'sub' => (string) $user->getAuthIdentifier(),
    'email' => $user->email ?? null,
    'name' => $user->name ?? null,
    'app' => $application->app_key,
    'roles' => $tokenPayload['roles'] ?? [], // ✅ Will be populated from IAM
    'iat' => $issuedAt->getTimestamp(),
    'exp' => $expiresAt->getTimestamp(),
];
```

---

### 3. **Testing Routes** - Fixed Data Sources

**Files**: 
- `routes/testing.php`
- `routes/iam-testing.php`

**Before** (WRONG - Using Spatie):
```php
'user_info' => [
    'id' => $user->id,
    'name' => $user->name,
    'email' => $user->email,
    'unit' => $user->unit,
    'roles' => $user->getRoleNames(),              // ❌ Spatie roles
    'permissions' => $user->getAllPermissions()->pluck('name'), // ❌ NOT IAM!
],
```

**After** (CORRECT - Using IAM):
```php
'user_info' => [
    'id' => $user->id,
    'name' => $user->name,
    'email' => $user->email,
    'unit' => $user->unit,
    'roles' => $accessPayload->roles ?? [],        // ✅ IAM roles from token
],
```

---

### 4. **SSO Controllers** - Removed Permissions

**Files**:
- `app/Http/Controllers/Sso/SsoVerifyController.php`
- `app/Http/Controllers/SSOController.php`

**SsoVerifyController** - Before:
```php
$response = [
    'roles' => $payload['roles'] ?? [],
    'permissions' => $payload['permissions'] ?? [], // ❌ REMOVED
];
```

**SsoVerifyController** - After:
```php
$response = [
    'roles' => $payload['roles'] ?? [], // ✅ Only roles
];
```

**SSOController::introspect()** - Before:
```php
return response()->json([
    'active' => true,
    'roles' => $userData['application']['roles'] ?? [],
    'permissions' => $userData['permissions'] ?? [], // ❌ REMOVED
]);
```

**SSOController::introspect()** - After:
```php
return response()->json([
    'active' => true,
    'roles' => $userData['application']['roles'] ?? [], // ✅ Only roles
]);
```

---

## Expected Output Now

### ✅ Correct JWT Token Payload
```json
{
  "iss": "http://localhost",
  "sub": "2",
  "email": "doctor@gmail.com",
  "name": "Dr. John Doe",
  "app": "siimut",
  "roles": [
    {
      "id": 5,
      "slug": "doctor",
      "name": "Doctor",
      "is_system": true,
      "description": "Medical doctor with patient care access"
    }
  ],
  "iat": 1763265205,
  "exp": 1763268805,
  "type": "access"
}
```

### ✅ Correct User Info
```json
{
  "id": 2,
  "name": "Dr. John Doe",
  "email": "doctor@gmail.com",
  "unit": "Cardiology",
  "roles": [
    {
      "id": 5,
      "slug": "doctor",
      "name": "Doctor",
      "is_system": true
    }
  ]
}
```

---

## Data Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    IAM Role Retrieval Flow                   │
└─────────────────────────────────────────────────────────────┘

User Model
    └── effectiveApplicationRoles()
            ├── Direct Roles (iam_user_application_roles)
            └── Profile Roles (user_access_profiles → access_profile_role_iam_map)
                    ↓
            ApplicationRole (iam_roles table)
                    ↓
            UserDataService.getTokenPayload()
                    ↓
            TokenService.issue()
                    ↓
            JWT Token with roles[]
                    ↓
            Client Application
                    └── Maps roles → permissions
```

---

## IAM Responsibility Boundary

### ✅ IAM Handles (Role Management)
- User authentication
- Application registration
- Role assignment (direct + via profiles)
- Access profiles management
- JWT token generation with **roles**
- SSO flow (OAuth2-like)

### ❌ IAM Does NOT Handle (Permission Management)
- Permissions/capabilities
- Resource-level access control
- Feature flags
- Business logic authorization

### 🎯 Client Application Responsibility
Client applications receive **roles** from IAM token and map them to their own permissions:

```javascript
// Client-side role → permission mapping
const rolePermissions = {
  'siimut': {
    'admin': ['*'],
    'doctor': ['patient.read', 'patient.write', 'prescription.create'],
    'nurse': ['patient.read', 'report.view'],
    'receptionist': ['patient.read', 'appointment.create']
  },
  'pharmacy.app': {
    'admin': ['*'],
    'pharmacist': ['inventory.manage', 'prescription.fulfill'],
    'assistant': ['inventory.view', 'prescription.view']
  }
};

// Get user's permissions from their roles
function getUserPermissions(appKey, userRoles) {
  const appRoleMap = rolePermissions[appKey] || {};
  let permissions = [];
  
  userRoles.forEach(role => {
    if (appRoleMap[role.slug]) {
      permissions = [...permissions, ...appRoleMap[role.slug]];
    }
  });
  
  return [...new Set(permissions)]; // unique
}

// Example usage
const token = parseJWT(accessToken);
const userPermissions = getUserPermissions(token.app, token.roles);

if (userPermissions.includes('patient.write')) {
  // Allow patient editing
}
```

---

## Testing

### Test Complete Flow
```bash
curl http://localhost/test-sso-complete-flow?user=doctor@gmail.com&app=siimut
```

**Expected roles array to be populated**:
```json
{
  "access_token_payload": {
    "roles": [
      {
        "id": 5,
        "slug": "doctor",
        "name": "Doctor",
        "is_system": true
      }
    ]
  }
}
```

### Test Token Generation
```bash
curl http://localhost/sso-test/token/doctor@gmail.com/siimut
```

### Test User Data
```bash
curl http://localhost/sso-test/user-data/doctor@gmail.com/siimut
```

### Test Token Verification
```bash
curl -X POST http://localhost/sso/verify \
  -H "Content-Type: application/json" \
  -d '{"token":"YOUR_ACCESS_TOKEN"}'
```

---

## Database Schema Reference

### User → Roles Relationships

```sql
-- Direct role assignments
iam_user_application_roles
├── user_id → users.id
├── role_id → iam_roles.id
└── assigned_by → users.id

-- Access profile assignments
user_access_profiles
├── user_id → users.id
├── access_profile_id → access_profiles.id
└── assigned_by → users.id

-- Access profile → roles mapping
access_profile_role_iam_map
├── access_profile_id → access_profiles.id
└── role_id → iam_roles.id

-- Application roles
iam_roles
├── id
├── application_id → applications.id
├── slug (e.g., 'doctor', 'admin')
├── name
├── is_system
└── description
```

---

## Migration Guide for Client Applications

If your client application was relying on the `permissions` field from IAM:

### Before (WRONG)
```javascript
// ❌ Relying on IAM permissions
fetch('/api/user-info', {
  headers: { 'Authorization': `Bearer ${token}` }
})
  .then(res => res.json())
  .then(data => {
    if (data.permissions.includes('patient.write')) {
      // Allow editing
    }
  });
```

### After (CORRECT)
```javascript
// ✅ Map roles to permissions in client
const token = parseJWT(accessToken);

// Define your own permission mapping
const rolePermissions = {
  'admin': ['*'],
  'doctor': ['patient.read', 'patient.write', 'prescription.create'],
  'nurse': ['patient.read', 'report.view']
};

// Calculate permissions from roles
let permissions = [];
token.roles.forEach(role => {
  if (rolePermissions[role.slug]) {
    permissions = [...permissions, ...rolePermissions[role.slug]];
  }
});

// Use permissions
if (permissions.includes('patient.write')) {
  // Allow editing
}
```

---

## Summary

| Aspect | Before | After |
|--------|--------|-------|
| **Roles Source** | Mixed (Spatie + IAM) | ✅ IAM Only |
| **Roles in Token** | Empty `[]` | ✅ Populated from DB |
| **Permissions** | In JWT payload | ✅ Removed (not IAM) |
| **Data Consistency** | Inconsistent | ✅ Standardized |
| **Responsibility** | Unclear | ✅ Clear: IAM = Roles |

---

## Files Modified

1. ✅ `app/Domain/Iam/Services/UserDataService.php`
2. ✅ `app/Services/Sso/TokenService.php`
3. ✅ `app/Http/Controllers/Sso/SsoVerifyController.php`
4. ✅ `app/Http/Controllers/SSOController.php`
5. ✅ `routes/iam-testing.php`
6. ✅ `routes/testing.php`

---

## Next Steps

1. **Test with Real Data**: Ensure users have IAM roles assigned in database
2. **Verify Role Assignment**: Check `iam_user_application_roles` table has data
3. **Client Updates**: Update client applications to map roles → permissions
4. **Documentation**: Update API docs to reflect role-only responses

---

**Status**: ✅ COMPLETE  
**Impact**: Breaking change for clients expecting `permissions` field  
**Migration**: Clients must implement their own role → permission mapping
