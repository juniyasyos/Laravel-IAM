# Contoh Penggunaan API

## Skenario 1: Login dan Mendapatkan Token

### Flow OAuth2 Authorization Code

```bash
# Step 1: Redirect user ke authorization endpoint
https://iam.example.com/sso/authorize?app_key=app-crm&redirect_uri=https://crm.example.com/callback&state=random123

# User login, kemudian redirect kembali dengan code
https://crm.example.com/callback?code=abc123xyz&state=random123

# Step 2: Exchange code dengan token
curl -X POST https://iam.example.com/sso/token \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "authorization_code",
    "app_key": "app-crm",
    "app_secret": "your-app-secret",
    "code": "abc123xyz",
    "redirect_uri": "https://crm.example.com/callback"
  }'
```

### Response
```json
{
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJpYW0tc2VydmVyIiwic3ViIjoiMSIsImVtYWlsIjoiam9obkBleGFtcGxlLmNvbSIsIm5hbWUiOiJKb2huIERvZSIsImFwcCI6ImFwcC1jcm0iLCJyb2xlcyI6W3siaWQiOjUsInNsdWciOiJzYWxlcy1tYW5hZ2VyIiwibmFtZSI6IlNhbGVzIE1hbmFnZXIiLCJpc19zeXN0ZW0iOmZhbHNlLCJkZXNjcmlwdGlvbiI6Ik1hbmFnZXMgc2FsZXMgdGVhbSJ9XSwicGVybWlzc2lvbnMiOnsidG90YWxfcm9sZXMiOjIsInN5c3RlbV9yb2xlcyI6MCwiY3VzdG9tX3JvbGVzIjoyLCJyb2xlX3NsdWdzIjpbInNhbGVzLW1hbmFnZXIiLCJ2aWV3ZXIiXSwiY2FwYWJpbGl0aWVzIjpbIm1hbmFnZV90ZWFtIiwidmlld19yZXBvcnRzIl19LCJpYXQiOjE3MDUzMTQ2MDAsImV4cCI6MTcwNTMxODIwMH0.signature",
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "active": true,
    "email_verified_at": "2024-01-01T00:00:00Z",
    "application": {
      "app_key": "app-crm",
      "name": "CRM System",
      "roles": [
        {
          "id": 5,
          "slug": "sales-manager",
          "name": "Sales Manager",
          "is_system": false,
          "description": "Manages sales team and views reports"
        },
        {
          "id": 3,
          "slug": "viewer",
          "name": "Viewer",
          "is_system": true,
          "description": "Read-only access"
        }
      ]
    },
    "access_profiles": [
      {
        "id": 1,
        "slug": "sales-team",
        "name": "Sales Team",
        "description": "Standard access for sales team members",
        "is_system": false,
        "roles_count": 2,
        "roles": [
          {
            "app_key": "app-crm",
            "role_slug": "viewer",
            "role_name": "Viewer"
          },
          {
            "app_key": "app-erp",
            "role_slug": "data-entry",
            "role_name": "Data Entry"
          }
        ]
      }
    ],
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
  "issued_at": "2024-01-15T10:30:00Z"
}
```

---

## Skenario 2: Verify Token di Client Application

Client application perlu verify token yang diterima:

```bash
curl -X POST https://iam.example.com/sso/verify \
  -H "Content-Type: application/json" \
  -d '{
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "include_user_data": false
  }'
```

### Response (Basic)
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

---

## Skenario 3: Get User Info Detail

Untuk mendapatkan informasi user lengkap dengan semua aplikasi:

```bash
curl -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." \
  https://iam.example.com/api/user-info
```

### Response
```json
{
  "sub": "1",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "active": true,
    "email_verified_at": "2024-01-01T00:00:00Z",
    "applications": [
      {
        "app_key": "app-crm",
        "name": "CRM System",
        "description": "Customer Relationship Management System",
        "enabled": true,
        "roles": [
          {
            "id": 5,
            "slug": "sales-manager",
            "name": "Sales Manager",
            "is_system": false,
            "description": "Manages sales team"
          },
          {
            "id": 3,
            "slug": "viewer",
            "name": "Viewer",
            "is_system": true,
            "description": "Read-only access"
          }
        ]
      },
      {
        "app_key": "app-erp",
        "name": "ERP System",
        "description": "Enterprise Resource Planning",
        "enabled": true,
        "roles": [
          {
            "id": 12,
            "slug": "data-entry",
            "name": "Data Entry",
            "is_system": false,
            "description": "Can input data"
          }
        ]
      }
    ],
    "accessible_apps": ["app-crm", "app-erp"],
    "access_profiles": [...],
    "direct_roles": [...],
  },
  "timestamp": "2024-01-15T10:35:00Z"
}
```

---

## Skenario 4: Get User Info untuk Aplikasi Tertentu

Filter hanya untuk satu aplikasi:

```bash
curl -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." \
  "https://iam.example.com/api/user-info?app=app-crm"
```

### Response
```json
{
  "sub": "1",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "active": true,
    "email_verified_at": "2024-01-01T00:00:00Z",
    "application": {
      "app_key": "app-crm",
      "name": "CRM System",
      "roles": [
        {
          "id": 5,
          "slug": "sales-manager",
          "name": "Sales Manager",
          "is_system": false,
          "description": "Manages sales team"
        },
        {
          "id": 3,
          "slug": "viewer",
          "name": "Viewer",
          "is_system": true,
          "description": "Read-only access"
        }
      ]
    },
    "access_profiles": [...],
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
  "timestamp": "2024-01-15T10:35:00Z"
}
```

---

## Skenario 5: Authorization Check di Client

Contoh implementasi di client application (JavaScript):

```javascript
class AuthService {
  constructor(token) {
    this.token = token;
    this.userData = null;
  }

  async initialize() {
    const response = await fetch('https://iam.example.com/api/user-info?app=app-crm', {
      headers: {
        'Authorization': `Bearer ${this.token}`
      }
    });
    
    const data = await response.json();
    this.userData = data.user;
  }

  hasRole(roleSlug) {
    if (!this.userData?.application?.roles) return false;
    
    return this.userData.application.roles.some(
      role => role.slug === roleSlug
    );
  }

  hasCapability(capability) {
    if (!this.userData?.permissions?.capabilities) return false;
    
    return this.userData.permissions.capabilities.includes(capability);
  }

  canAccessFeature(feature) {
    const featurePermissions = {
      'create_customer': ['sales-manager', 'admin'],
      'delete_customer': ['admin'],
      'view_reports': ['sales-manager', 'manager', 'admin'],
      'manage_team': ['manager', 'admin']
    };

    const requiredRoles = featurePermissions[feature] || [];
    
    return requiredRoles.some(role => this.hasRole(role));
  }

  get userInfo() {
    return {
      id: this.userData?.id,
      name: this.userData?.name,
      email: this.userData?.email,
      roles: this.userData?.application?.roles || [],
      capabilities: this.userData?.permissions?.capabilities || []
    };
  }
}

// Usage
const auth = new AuthService(localStorage.getItem('access_token'));
await auth.initialize();

console.log('User:', auth.userInfo);
console.log('Has sales-manager role?', auth.hasRole('sales-manager'));
console.log('Has manage_team capability?', auth.hasCapability('manage_team'));
console.log('Can create customer?', auth.canAccessFeature('create_customer'));

// UI rendering example
if (auth.canAccessFeature('view_reports')) {
  document.getElementById('reports-menu').style.display = 'block';
}

if (auth.hasRole('admin')) {
  document.getElementById('admin-panel').style.display = 'block';
}
```

---

## Skenario 6: Token Refresh

Refresh access token menggunakan refresh token:

```bash
curl -X POST https://iam.example.com/sso/token \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "refresh_token",
    "app_key": "app-crm",
    "app_secret": "your-app-secret",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
  }'
```

### Response
```json
{
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

---

## Skenario 7: Token Introspection (dari Backend)

Backend-to-backend validation:

```bash
curl -X POST https://iam.example.com/sso/introspect \
  -H "Content-Type: application/json" \
  -d '{
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "app_key": "app-crm",
    "app_secret": "your-app-secret"
  }'
```

### Response (Active Token)
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

### Response (Invalid Token)
```json
{
  "active": false
}
```

---

## Skenario 8: Get UserInfo dari Access Token

Endpoint standar OAuth2 untuk mendapatkan user info:

```bash
curl -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." \
  https://iam.example.com/sso/userinfo
```

### Response
```json
{
  "sub": "1",
  "user": {
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
  "token_info": {
    "issued_at": 1705314600,
    "expires_at": 1705318200,
    "app_key": "app-crm"
  }
}
```

---

## Skenario 9: Revoke Token

Revoke refresh token:

```bash
curl -X POST https://iam.example.com/sso/revoke \
  -H "Content-Type: application/json" \
  -d '{
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "app_key": "app-crm",
    "app_secret": "your-app-secret"
  }'
```

### Response
```json
{
  "message": "Token revoked successfully"
}
```

---

## Skenario 10: Error Handling

### Token Expired
```json
{
  "message": "Invalid or expired token."
}
```
Status: 422

### Invalid Application
```json
{
  "error": "invalid_client",
  "error_description": "Application not found"
}
```
Status: 404

### Invalid Token
```json
{
  "error": "invalid_token",
  "error_description": "Token is not an access token"
}
```
Status: 401

### Missing Bearer Token
```json
{
  "error": "invalid_request",
  "error_description": "Access token is required"
}
```
Status: 400

---

## Best Practices

### 1. Token Storage (Client-side)
```javascript
// Store tokens securely
localStorage.setItem('access_token', response.access_token);
localStorage.setItem('refresh_token', response.refresh_token);

// Better: Use httpOnly cookies for refresh token
// Let backend handle refresh token
```

### 2. Token Refresh Strategy
```javascript
async function fetchWithAuth(url, options = {}) {
  const token = localStorage.getItem('access_token');
  
  const response = await fetch(url, {
    ...options,
    headers: {
      ...options.headers,
      'Authorization': `Bearer ${token}`
    }
  });

  // If 401, try refresh
  if (response.status === 401) {
    const newToken = await refreshToken();
    if (newToken) {
      // Retry original request
      return fetchWithAuth(url, options);
    } else {
      // Redirect to login
      window.location.href = '/login';
    }
  }

  return response;
}
```

### 3. Cache User Data
```javascript
// Cache user data to reduce API calls
const CACHE_KEY = 'user_data';
const CACHE_TTL = 5 * 60 * 1000; // 5 minutes

function getCachedUserData() {
  const cached = localStorage.getItem(CACHE_KEY);
  if (!cached) return null;
  
  const { data, timestamp } = JSON.parse(cached);
  
  if (Date.now() - timestamp > CACHE_TTL) {
    localStorage.removeItem(CACHE_KEY);
    return null;
  }
  
  return data;
}

function setCachedUserData(data) {
  localStorage.setItem(CACHE_KEY, JSON.stringify({
    data,
    timestamp: Date.now()
  }));
}
```

### 4. Role-based UI Rendering
```vue
<!-- Vue.js example -->
<template>
  <div>
    <button v-if="hasRole('sales-manager')" @click="createCustomer">
      Create Customer
    </button>
    
    <div v-if="hasCapability('view_reports')">
      <ReportsPanel />
    </div>
    
    <AdminPanel v-if="hasRole('admin')" />
  </div>
</template>

<script>
export default {
  computed: {
    userRoles() {
      return this.$store.state.user?.application?.roles || [];
    },
    userCapabilities() {
      return this.$store.state.user?.permissions?.capabilities || [];
    }
  },
  methods: {
    hasRole(slug) {
      return this.userRoles.some(r => r.slug === slug);
    },
    hasCapability(cap) {
      return this.userCapabilities.includes(cap);
    }
  }
}
</script>
```
