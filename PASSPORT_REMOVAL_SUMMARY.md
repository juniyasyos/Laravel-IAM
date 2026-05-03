# Passport OAuth2 Removal Summary

## Overview
Successfully removed Laravel Passport OAuth2 authentication system from the IAM backend. This was necessary because the system is backend-only (no frontend), making Passport unnecessary. The system now uses only:
- **SSO JWT**: Firebase\JWT with TokenBuilder service (TTL 3600s)
- **Custom API Key**: integration_keys table for service-to-service authentication

## Removed Components

### 1. **Controllers**
- `app/Http/Controllers/Api/AuthController.php` - Passport auth endpoints
- `app/Http/Controllers/Auth/SessionFromTokenController.php` - Passport token exchange

### 2. **Middleware**
- `app/Http/Middleware/AuthenticateFromJWT.php` - JWT validation for Passport tokens
- Removed from `bootstrap/app.php` web middleware stack
- Removed from `app/Providers/Filament/PanelPanelProvider.php`

### 3. **Configuration**
- `config/passport.php` - Passport configuration file
- Removed Passport::routes() from `app/Providers/AuthServiceProvider.php`
- Removed Passport token expiry configurations
- Changed `config/auth.php` api guard driver from 'passport' to 'session'

### 4. **Models & Files**
- Removed `HasApiTokens` trait from `app/Models/User.php`
- Renamed `findForPassport()` to `findForAuth()` in User model
- Removed `app/Passport/AccessToken.php` custom access token class

### 5. **Database Migrations**
- Disabled (renamed with SKIP_ prefix) OAuth migrations:
  - `SKIP_2025_09_27_102342_create_oauth_auth_codes_table.php`
  - `SKIP_2025_09_27_102343_create_oauth_access_tokens_table.php`
  - `SKIP_2025_09_27_102344_create_oauth_refresh_tokens_table.php`
  - `SKIP_2025_09_27_102345_create_oauth_clients_table.php`
  - `SKIP_2025_09_27_102346_create_oauth_personal_access_clients_table.php`

### 6. **Routes**
- Removed all auth:api protected routes from `routes/api.php`
- Kept only SSO and TTD URL endpoints

### 7. **Database Seeding**
- Removed `PassportSeeder::class` from `database/seeders/DatabaseSeeder.php`
- Removed Passport Client count from seeding summary

## API Routes Changes

### Before
```php
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});
```

### After
All removed. Only TTD endpoint and SSO routes remain.

## Authentication Flow

### For API Calls
1. **API Key Authentication**: Include `X-API-Key` header (integration_keys table)
2. **JWT Authentication**: Include `Authorization: Bearer <jwt>` header (SSO JWT)

### For Admin Panel
Uses standard Laravel session-based authentication via Fortify/Filament.

## Verification

✅ All Passport references removed (except unrelated "oauth" strings in Slack config)
✅ TTD URL API tests: 5/5 passing
✅ User model: HasApiTokens removed, findForAuth() working
✅ Config: api guard now uses session driver
✅ Routes: auth:api routes removed, only SSO/TTD remain
✅ Cache: Cleared and rebuilt to remove Passport service providers

## Migration Path

If database already has oauth tables (from previous Passport setup):
1. OAuth tables will remain in database (SKIP migrations won't touch them)
2. To clean up: Run `php artisan migrate:rollback` to revert those specific migrations (or manually drop tables)
3. For fresh installs: SKIP migrations prevent oauth tables from being created

## System Dependencies

The system still maintains:
- Laravel Fortify (password reset, two-factor auth)
- Filament Admin Panel (uses session guard)
- Spatie Laravel Permission (role/permission management)
- Custom SSO JWT system (Firebase\JWT)
- API Key validation middleware
