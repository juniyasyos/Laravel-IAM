# Data Flow & Architecture Diagrams

## 1. SSO Authentication Flow

```
┌─────────────┐                                    ┌─────────────┐
│             │  1. /sso/authorize?app_key=...     │             │
│   Client    │───────────────────────────────────>│  IAM Server │
│ Application │                                    │             │
│             │<───────────────────────────────────│             │
└─────────────┘  2. Redirect to login             └─────────────┘
       │                                                  │
       │                                                  │
       │  3. User login                                   │
       └─────────────────────────────────────────────────┘
                                                          │
                                                          ▼
                                                   ┌─────────────┐
                                                   │ TokenService│
                                                   │      +      │
                                                   │UserDataSvc  │
                                                   └─────────────┘
                                                          │
                                                          ▼
┌─────────────┐                                    ┌─────────────┐
│             │  4. Redirect with code             │             │
│   Client    │<───────────────────────────────────│  IAM Server │
│ Application │                                    │             │
│             │  5. POST /sso/token                │             │
│             │───────────────────────────────────>│             │
│             │     {code, app_key, app_secret}    │             │
│             │                                    │             │
│             │  6. Return tokens + user data      │             │
│             │<───────────────────────────────────│             │
│             │     {access_token, refresh_token,  │             │
│             │      user: {...}}                  │             │
└─────────────┘                                    └─────────────┘
```

## 2. User Data Service Architecture

```
┌──────────────────────────────────────────────────────────┐
│                    UserDataService                       │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  getUserData(user, app?, includeProfiles?)              │
│     │                                                    │
│     ├─> formatRolesForApplication()                     │
│     │      └─> Filter by app_id                         │
│     │                                                    │
│     ├─> formatAllApplicationsAndRoles()                 │
│     │      └─> Group by app_key                         │
│     │                                                    │
│     ├─> formatAccessProfiles()                          │
│     │      └─> Get profiles with roles                  │
│     │                                                    │
│     ├─> formatDirectRoles()                             │
│     │      └─> Get user->applicationRoles()             │
│     │                                                    │
│     ├─> formatPermissions()                             │
│     │      ├─> Count roles                              │
│     │      └─> Extract capabilities                     │
│     │             └─> admin -> full_access              │
│     │             └─> manager -> manage_team            │
│     │             └─> viewer -> read_only               │
│     │                                                    │
│     └─> Return comprehensive array                      │
│                                                          │
│  getTokenPayload(user, app)                             │
│     └─> Simplified version for JWT                      │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

## 3. Data Structure Hierarchy

```
User
├── Basic Info
│   ├── id
│   ├── name
│   ├── email
│   ├── active
│   └── email_verified_at
│
├── Applications (if no filter)
│   └── Application[]
│       ├── app_key
│       ├── name
│       ├── description
│       ├── enabled
│       └── roles[]
│           ├── id
│           ├── slug
│           ├── name
│           ├── is_system
│           └── description
│
├── Application (if filtered by app)
│   ├── app_key
│   ├── name
│   └── roles[]
│
├── Access Profiles
│   └── AccessProfile[]
│       ├── id
│       ├── slug
│       ├── name
│       ├── description
│       ├── is_system
│       ├── roles_count
│       └── roles[]
│           ├── app_key
│           ├── role_slug
│           └── role_name
│
├── Direct Roles
│   └── DirectRole[]
│       ├── app_key
│       ├── role_id
│       ├── role_slug
│       ├── role_name
│       └── is_system
│
└── Permissions
    ├── total_roles
    ├── system_roles
    ├── custom_roles
    ├── role_slugs[]
    └── capabilities[]
```

## 4. Database Relations

```
┌──────────────┐
│     User     │
└──────────────┘
       │
       ├──────────────────────────────────────┐
       │                                      │
       │ (Many-to-Many)                       │ (Many-to-Many)
       │ via iam_user_application_roles       │ via user_access_profiles
       │                                      │
       ▼                                      ▼
┌─────────────────┐                  ┌──────────────────┐
│ ApplicationRole │                  │  AccessProfile   │
├─────────────────┤                  ├──────────────────┤
│ - id            │                  │ - id             │
│ - slug          │                  │ - slug           │
│ - name          │◄─────────────────┤ - name           │
│ - application_id│  (Many-to-Many)  │ - is_active      │
│ - is_system     │  via access_     │ - is_system      │
│ - description   │  profile_role_   └──────────────────┘
└─────────────────┘  iam_map
       │
       │ (Belongs To)
       │
       ▼
┌─────────────┐
│ Application │
├─────────────┤
│ - id        │
│ - app_key   │
│ - name      │
│ - enabled   │
│ - token_exp │
└─────────────┘

Effective Roles = Direct Roles ∪ Profile Roles
```

## 5. Token Flow

```
┌─────────────────────────────────────────────────────┐
│              TokenService::issue()                  │
└─────────────────────────────────────────────────────┘
                       │
                       ▼
          ┌────────────────────────┐
          │  UserDataService       │
          │  getTokenPayload()     │
          └────────────────────────┘
                       │
                       ▼
          ┌────────────────────────┐
          │  Get effective roles   │
          │  for application       │
          └────────────────────────┘
                       │
                       ▼
          ┌────────────────────────┐
          │  Format permissions    │
          │  Extract capabilities  │
          └────────────────────────┘
                       │
                       ▼
          ┌────────────────────────┐
          │  Build JWT payload     │
          │  {iss, sub, email,     │
          │   name, app, roles,    │
          │   permissions, iat,    │
          │   exp}                 │
          └────────────────────────┘
                       │
                       ▼
          ┌────────────────────────┐
          │  Sign with HMAC-SHA256 │
          └────────────────────────┘
                       │
                       ▼
          ┌────────────────────────┐
          │  Return JWT token      │
          │  (header.payload.sig)  │
          └────────────────────────┘
```

## 6. API Endpoints Overview

```
┌─────────────────────────────────────────────────────────┐
│                    IAM Server API                        │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  Authentication & Authorization                          │
│  ├── GET  /sso/authorize                                │
│  │        → Initiate OAuth2 flow                        │
│  │                                                       │
│  ├── POST /sso/token                                    │
│  │        → Exchange code/refresh token                 │
│  │        → Returns: access_token + user data           │
│  │                                                       │
│  └── POST /sso/revoke                                   │
│           → Revoke refresh token                        │
│                                                          │
│  Token Validation                                        │
│  ├── POST /sso/verify                                   │
│  │        → Verify token validity                       │
│  │        → Optional: include_user_data                 │
│  │                                                       │
│  └── POST /sso/introspect                               │
│           → Backend token validation                    │
│           → Returns: roles + permissions                │
│                                                          │
│  User Information                                        │
│  ├── GET  /api/user-info                                │
│  │        → Comprehensive user data                     │
│  │        → Query: app, include_profiles                │
│  │                                                       │
│  └── GET  /sso/userinfo                                 │
│           → OAuth2 standard userinfo                    │
│           → From Bearer token                           │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

## 7. Role Resolution Logic

```
Input: User ID + Application Key

                ┌─────────────────┐
                │  Get User       │
                └────────┬────────┘
                         │
         ┌───────────────┼───────────────┐
         │                               │
         ▼                               ▼
┌─────────────────┐           ┌──────────────────┐
│  Direct Roles   │           │ Profile Roles    │
│                 │           │                  │
│ User            │           │ User             │
│   ↓             │           │   ↓              │
│ ApplicationRole │           │ AccessProfile    │
│   ↓             │           │   ↓              │
│ Filter by app   │           │ ApplicationRole  │
│                 │           │   ↓              │
│ Result: Role[]  │           │ Filter by app    │
└────────┬────────┘           │                  │
         │                    │ Result: Role[]   │
         │                    └────────┬─────────┘
         │                             │
         └──────────┬──────────────────┘
                    │
                    ▼
           ┌─────────────────┐
           │  Merge Roles    │
           │  (Union)        │
           └────────┬────────┘
                    │
                    ▼
           ┌─────────────────┐
           │ Deduplicate     │
           │ by role_id      │
           └────────┬────────┘
                    │
                    ▼
           ┌─────────────────┐
           │ Effective Roles │
           │ (Final Result)  │
           └─────────────────┘
```

## 8. Capabilities Extraction

```
Input: Collection of Roles

┌─────────────────────────────────────┐
│  Extract role slugs                 │
│  ['admin', 'viewer', 'manager']     │
└──────────────┬──────────────────────┘
               │
               ▼
      ┌────────────────┐
      │ Check patterns │
      └────────────────┘
               │
               ├─> admin?
               │   └─> Add: full_access, manage_users, manage_settings
               │
               ├─> manager?
               │   └─> Add: manage_team, view_reports
               │
               ├─> viewer?
               │   └─> Add: read_only
               │
               └─> custom pattern?
                   └─> Add custom capabilities
                       │
                       ▼
              ┌────────────────┐
              │ Deduplicate    │
              └────────┬───────┘
                       │
                       ▼
              ┌────────────────┐
              │ Final Result   │
              │ [full_access,  │
              │  manage_users, │
              │  manage_team,  │
              │  view_reports, │
              │  read_only]    │
              └────────────────┘
```

## 9. Controller Dependencies

```
┌─────────────────────────────────────────────────┐
│            UserInfoController                    │
├─────────────────────────────────────────────────┤
│ Dependencies:                                    │
│ - UserDataService                               │
└─────────────┬───────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────────────┐
│             SSOController                        │
├─────────────────────────────────────────────────┤
│ Dependencies:                                    │
│ - JWTTokenService                               │
│ - UserDataService                               │
└─────────────┬───────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────────────┐
│          SsoVerifyController                     │
├─────────────────────────────────────────────────┤
│ Dependencies:                                    │
│ - TokenService                                  │
│ - SsoLogger                                     │
│ - UserDataService                               │
└─────────────┬───────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────────────┐
│            TokenService                          │
├─────────────────────────────────────────────────┤
│ Dependencies:                                    │
│ - SsoLogger                                     │
│ - UserDataService                               │
└─────────────────────────────────────────────────┘
                      │
                      ▼
         ┌────────────────────────┐
         │   UserDataService      │
         ├────────────────────────┤
         │ No dependencies        │
         │ (Pure service)         │
         └────────────────────────┘
```

## 10. Response Transformation Pipeline

```
Raw Database Data
      │
      ▼
┌──────────────────────┐
│ User Model           │
│ - effectiveRoles()   │
│ - accessProfiles()   │
│ - directRoles()      │
└──────┬───────────────┘
       │
       ▼
┌──────────────────────┐
│ UserDataService      │
│ - Format roles       │
│ - Format profiles    │
│ - Calculate perms    │
└──────┬───────────────┘
       │
       ▼
┌──────────────────────┐
│ Structured Array     │
│ {                    │
│   id, name, email,   │
│   applications: [],  │
│   access_profiles,   │
│   direct_roles,      │
│   permissions        │
│ }                    │
└──────┬───────────────┘
       │
       ▼
┌──────────────────────┐
│ Controller Response  │
│ JSON Encoding        │
└──────┬───────────────┘
       │
       ▼
┌──────────────────────┐
│ HTTP Response        │
│ Status: 200          │
│ Content-Type: JSON   │
└──────────────────────┘
```

## 11. Client Integration Flow

```
┌─────────────────────────────────────────────────┐
│            Client Application                    │
└─────────────────────────────────────────────────┘
                    │
                    │ 1. Redirect to IAM
                    ▼
┌─────────────────────────────────────────────────┐
│  IAM: /sso/authorize?app_key=...                │
└─────────────────────────────────────────────────┘
                    │
                    │ 2. User authenticates
                    │ 3. Callback with code
                    ▼
┌─────────────────────────────────────────────────┐
│  Client: /callback?code=...                     │
│  Exchange code for token                        │
└─────────────────────────────────────────────────┘
                    │
                    │ 4. Store tokens
                    ▼
┌─────────────────────────────────────────────────┐
│  localStorage.setItem('access_token', ...)      │
│  localStorage.setItem('refresh_token', ...)     │
│  Store user data (optional)                     │
└─────────────────────────────────────────────────┘
                    │
                    │ 5. Initialize app
                    ▼
┌─────────────────────────────────────────────────┐
│  Fetch user info (optional, if not from token)  │
│  GET /api/user-info                             │
└─────────────────────────────────────────────────┘
                    │
                    │ 6. Setup authorization
                    ▼
┌─────────────────────────────────────────────────┐
│  Check roles/capabilities                       │
│  - hasRole(slug)                                │
│  - hasCapability(name)                          │
│  - canAccessFeature(feature)                    │
└─────────────────────────────────────────────────┘
                    │
                    │ 7. Render UI
                    ▼
┌─────────────────────────────────────────────────┐
│  Conditional rendering based on permissions     │
│  - Show/hide menu items                         │
│  - Enable/disable buttons                       │
│  - Route guards                                 │
└─────────────────────────────────────────────────┘
```

## 12. Error Handling Flow

```
┌─────────────────────┐
│  API Request        │
└──────────┬──────────┘
           │
           ▼
    ┌──────────────┐
    │ Validate     │
    │ Input        │
    └──────┬───────┘
           │
           ├─> Invalid? ──> 400 Bad Request
           │                 {error, error_description}
           │
           ▼
    ┌──────────────┐
    │ Authenticate │
    │ User/App     │
    └──────┬───────┘
           │
           ├─> Not authenticated? ──> 401 Unauthorized
           │                           {error, error_description}
           │
           ▼
    ┌──────────────┐
    │ Authorize    │
    │ Access       │
    └──────┬───────┘
           │
           ├─> Not authorized? ──> 403 Forbidden
           │                        {error, error_description}
           │
           ▼
    ┌──────────────┐
    │ Process      │
    │ Request      │
    └──────┬───────┘
           │
           ├─> Resource not found? ──> 404 Not Found
           │                             {error, error_description}
           │
           ├─> Validation failed? ──> 422 Unprocessable Entity
           │                           {message, errors}
           │
           ├─> Server error? ──> 500 Internal Server Error
           │                      {message}
           │
           ▼
    ┌──────────────┐
    │ Success      │
    │ 200 OK       │
    └──────────────┘
```
