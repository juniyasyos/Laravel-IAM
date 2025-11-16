# API Response Format Documentation

## Overview

Semua SSO controllers telah diupdate untuk mengembalikan data yang lebih kompleks dan komprehensif menggunakan `UserDataService`.

## Response Structure

### 1. UserInfoController

**Endpoint:** `GET /api/user-info`

**Query Parameters:**
- `app` (optional): Filter roles by specific application key
- `include_profiles` (optional, default: true): Include access profile information

**Response:**

```json
{
  "sub": "1",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "active": true,
    "email_verified_at": "2024-01-01T00:00:00Z",
    
    // If no specific app is requested
    "applications": [
      {
        "app_key": "app-crm",
        "name": "CRM System",
        "description": "Customer Relationship Management",
        "enabled": true,
        "roles": [
          {
            "id": 5,
            "slug": "sales-manager",
            "name": "Sales Manager",
            "is_system": false,
            "description": "Manages sales team"
          }
        ]
      }
    ],
    "accessible_apps": ["app-crm", "app-erp"],
    
    // If specific app is requested
    "application": {
      "app_key": "app-crm",
      "name": "CRM System",
      "roles": [...]
    },
    
    // Access profiles (if include_profiles=true)
    "access_profiles": [
      {
        "id": 1,
        "slug": "sales-team",
        "name": "Sales Team",
        "description": "Standard sales team access",
        "is_system": false,
        "roles_count": 3,
        "roles": [
          {
            "app_key": "app-crm",
            "role_slug": "viewer",
            "role_name": "Viewer"
          }
        ]
      }
    ],
    
    // Direct role assignments (not via profiles)
    "direct_roles": [
      {
        "app_key": "app-crm",
        "role_id": 5,
        "role_slug": "sales-manager",
        "role_name": "Sales Manager",
        "is_system": false
      }
    ],
  },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

---

### 2. SSOController - Token Endpoint

**Endpoint:** `POST /sso/token`

**Grant Type:** `authorization_code`

**Response:**

```json
{
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "user": {
    // Same structure as UserInfoController response
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "active": true,
    "application": {
      "app_key": "app-crm",
      "name": "CRM System",
      "roles": [...]
    },
    "access_profiles": [...],
    "direct_roles": [...],
  },
  "issued_at": "2024-01-15T10:30:00Z"
}
```

---

### 3. SSOController - Introspect Endpoint

**Endpoint:** `POST /sso/introspect`

**Response:**

```json
{
  "active": true,
  "sub": "1",
  "name": "John Doe",
  "email": "john@example.com",
  "roles": [
    {
      "id": 5,
      "slug": "sales-manager",
      "name": "Sales Manager",
      "is_system": false,
      "description": "Manages sales team"
    }
  ],
  "exp": 1705318200,
  "iat": 1705314600
}
```

---

### 4. SSOController - UserInfo Endpoint

**Endpoint:** `GET /sso/userinfo`

**Headers:** `Authorization: Bearer {access_token}`

**Response:**

```json
{
  "sub": "1",
  "user": {
    // Complete user data structure
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "active": true,
    "application": {...},
    "access_profiles": [...],
    "direct_roles": [...],
  },
  "token_info": {
    "issued_at": 1705314600,
    "expires_at": 1705318200,
    "app_key": "app-crm"
  }
}
```

---

### 5. SsoVerifyController

**Endpoint:** `POST /sso/verify`

**Request:**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "include_user_data": true  // optional, default: false
}
```

**Response (without user data):**

```json
{
  "email": "john@example.com",
  "token_info": {
    "sub": "1",
    "app": "app-crm",
    "issuer": "iam-server",
    "issued_at": "2024-01-15T10:30:00Z",
    "expires_at": "2024-01-15T11:30:00Z"
  },
  "roles": [
    {
      "id": 5,
      "slug": "sales-manager",
      "name": "Sales Manager",
      "is_system": false,
      "description": "Manages sales team"
    }
  ],
}
```

**Response (with user data):**

```json
{
  "email": "john@example.com",
  "token_info": {...},
  "roles": [...],
  "user": {
    // Complete user data from UserDataService
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "active": true,
    "application": {...},
    "access_profiles": [...],
    "direct_roles": [...],
  }
}
```

---

## JWT Token Payload

Token sekarang menyertakan roles dan permissions:

```json
{
  "iss": "iam-server",
  "sub": "1",
  "email": "john@example.com",
  "name": "John Doe",
  "app": "app-crm",
  "roles": [
    {
      "id": 5,
      "slug": "sales-manager",
      "name": "Sales Manager",
      "is_system": false,
      "description": "Manages sales team"
    }
  ],
  "iat": 1705314600,
  "exp": 1705318200
}
```

---

## Capabilities System

Capabilities otomatis diekstrak berdasarkan role slugs:

| Role Slug | Capabilities |
|-----------|-------------|
| `admin` | `full_access`, `manage_users`, `manage_settings` |
| `manager` | `manage_team`, `view_reports` |
| `viewer` | `read_only` |

Custom capabilities dapat ditambahkan dengan meng-extend method `extractCapabilities()` di `UserDataService`.

---

## Migration Guide

### Untuk Client Applications

1. **Token Response**: Token endpoint sekarang mengembalikan `user` object dengan data lengkap
2. **Introspect**: Response lebih detail dengan `roles` array dan `permissions` object
3. **Verify**: Tambahkan `include_user_data=true` untuk mendapatkan data user lengkap
4. **UserInfo**: Structure berubah, `claims` diganti dengan nested objects

### Breaking Changes

- `UserInfoController` tidak lagi menggunakan `AppRegistryContract`
- Response structure berubah dari flat ke nested
- `claims` property dihapus, diganti dengan structured data

### Backward Compatibility

- `SsoVerifyController` masih mengembalikan `email` di root level
- Token masih kompatibel dengan versi sebelumnya
- Semua endpoint existing masih berfungsi

---

## Examples

### Get User Info for Specific App

```bash
curl -H "Authorization: Bearer {token}" \
  "https://iam.example.com/api/user-info?app=app-crm"
```

### Get User Info Without Profiles

```bash
curl -H "Authorization: Bearer {token}" \
  "https://iam.example.com/api/user-info?include_profiles=false"
```

### Verify Token with User Data

```bash
curl -X POST https://iam.example.com/sso/verify \
  -H "Content-Type: application/json" \
  -d '{"token": "...", "include_user_data": true}'
```

### Introspect Token

```bash
curl -X POST https://iam.example.com/sso/introspect \
  -H "Content-Type: application/json" \
  -d '{
    "token": "...",
    "app_key": "app-crm",
    "app_secret": "secret123"
  }'
```
