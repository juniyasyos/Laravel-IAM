# Quick Test Reference

## Current Status
✅ Permissions removed from IAM (not our responsibility)  
✅ Roles now use IAM ApplicationRoles only  
✅ Data structure standardized across all endpoints

---

## Test Endpoints

### 1. Complete SSO Flow Test
```bash
curl "http://localhost/test-sso-complete-flow?user=doctor@gmail.com&app=siimut"
```

**Expected roles array**:
```json
{
  "access_token_payload": {
    "sub": 2,
    "name": "Dr. John Doe",
    "email": "doctor@gmail.com",
    "roles": [
      {
        "id": 5,
        "slug": "doctor",
        "name": "Doctor",
        "is_system": true,
        "description": "Medical doctor"
      }
    ],
    "unit": "Cardiology",
    "app_key": "siimut"
  },
  "user_info": {
    "roles": [
      {
        "id": 5,
        "slug": "doctor",
        "name": "Doctor"
      }
    ]
  }
}
```

### 2. Token Generation Test
```bash
curl "http://localhost/sso-test/token/doctor@gmail.com/siimut"
```

**What to check**:
- ✅ `roles` array is populated
- ✅ No `permissions` field
- ✅ Token payload includes role objects

### 3. User Data Test
```bash
curl "http://localhost/sso-test/user-data/doctor@gmail.com/siimut"
```

**What to check**:
- ✅ `application.roles[]` populated
- ✅ `access_profiles[]` populated (if user has profiles)
- ✅ `direct_roles[]` populated (if user has direct assignments)
- ✅ No `permissions` field

### 4. Token Verification Test
```bash
# First get a token
TOKEN=$(curl -s "http://localhost/sso-test/token/doctor@gmail.com/siimut" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)

# Then verify it
curl -X POST "http://localhost/sso/verify" \
  -H "Content-Type: application/json" \
  -d "{\"token\":\"$TOKEN\"}"
```

**What to check**:
- ✅ `roles[]` present
- ✅ No `permissions` field

---

## Database Checks

### Check if user has roles assigned
```sql
-- Direct role assignments
SELECT u.email, a.app_key, r.slug as role_slug, r.name as role_name
FROM users u
JOIN iam_user_application_roles uar ON u.id = uar.user_id
JOIN iam_roles r ON uar.role_id = r.id
JOIN applications a ON r.application_id = a.id
WHERE u.email = 'doctor@gmail.com';

-- Roles via access profiles
SELECT u.email, ap.name as profile_name, a.app_key, r.slug as role_slug
FROM users u
JOIN user_access_profiles uap ON u.id = uap.user_id
JOIN access_profiles ap ON uap.access_profile_id = ap.id
JOIN access_profile_role_iam_map aprm ON ap.id = aprm.access_profile_id
JOIN iam_roles r ON aprm.role_id = r.id
JOIN applications a ON r.application_id = a.id
WHERE u.email = 'doctor@gmail.com';
```

### If roles are empty, assign one
```sql
-- Find role ID
SELECT id, slug, name, application_id FROM iam_roles WHERE slug = 'doctor' LIMIT 1;

-- Assign to user (replace IDs as needed)
INSERT INTO iam_user_application_roles (user_id, role_id, assigned_by, created_at, updated_at)
VALUES (2, 5, 1, NOW(), NOW());
```

---

## Common Issues & Solutions

### Issue: `roles: []` (empty array)

**Cause**: User has no IAM roles assigned

**Solution**:
1. Check database with SQL above
2. Assign roles via Filament UI or SQL
3. Or assign access profile with roles

### Issue: Server returns 404

**Cause**: Laravel app not running

**Solution**:
```bash
cd /home/juni/skripsi-ahmad-ilyas/application/Laravel-IAM
php artisan serve
```

### Issue: Token verification fails

**Cause**: Token expired or invalid

**Solution**:
- Generate fresh token
- Check `config/sso.php` for secret
- Verify app_key matches

---

## What Changed

### ❌ Removed (Not IAM Responsibility)
```json
{
  "permissions": {
    "total_roles": 1,
    "capabilities": ["read", "write"],
    "role_slugs": ["doctor"]
  }
}
```

### ✅ Now Returns (IAM Roles Only)
```json
{
  "roles": [
    {
      "id": 5,
      "slug": "doctor",
      "name": "Doctor",
      "is_system": true,
      "description": "Medical doctor with patient care access"
    }
  ]
}
```

---

## JWT Token Structure

### Access Token Payload
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
      "is_system": true
    }
  ],
  "iat": 1763265205,
  "exp": 1763268805
}
```

### Refresh Token Payload
```json
{
  "iss": "http://localhost",
  "sub": "2",
  "app": "siimut",
  "type": "refresh",
  "iat": 1763265205,
  "exp": 1765857205
}
```

---

## Client Application Example

### How to use roles in your app
```javascript
// Parse JWT token
const token = parseJWT(accessToken);

// Define your permission mapping
const ROLE_PERMISSIONS = {
  'admin': ['*'],
  'doctor': [
    'patient.read',
    'patient.write', 
    'patient.create',
    'prescription.create',
    'report.view'
  ],
  'nurse': [
    'patient.read',
    'report.view'
  ],
  'receptionist': [
    'patient.read',
    'patient.create',
    'appointment.manage'
  ]
};

// Calculate user permissions
function getUserPermissions(roles) {
  let permissions = new Set();
  
  roles.forEach(role => {
    const rolePerms = ROLE_PERMISSIONS[role.slug] || [];
    rolePerms.forEach(perm => permissions.add(perm));
  });
  
  return Array.from(permissions);
}

// Check permission
function hasPermission(permission) {
  const perms = getUserPermissions(token.roles);
  return perms.includes('*') || perms.includes(permission);
}

// Usage
if (hasPermission('patient.write')) {
  // Show edit button
}
```

---

## Verification Checklist

After running tests, verify:

- [ ] `roles` array is populated in JWT token
- [ ] `roles` array contains role objects with: id, slug, name, is_system
- [ ] No `permissions` field anywhere
- [ ] User data shows correct roles for application
- [ ] Access profiles correctly map to roles
- [ ] Direct role assignments work
- [ ] Token verification returns roles
- [ ] All test endpoints work

---

## Documentation Files

1. **IAM-ROLES-ONLY-UPDATE.md** - Complete technical documentation
2. **QUICK-TEST-REFERENCE.md** - This file (quick tests)
3. **API-RESPONSE-FORMAT.md** - Updated API response structures
4. **CLIENT-INTEGRATION.md** - Client integration guide

---

**Last Updated**: November 16, 2025  
**Status**: Ready for Testing
