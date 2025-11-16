# IAM Admin Access Control

## Overview

Sistem kontrol akses untuk IAM Admin Panel (Filament) dan Pulse Dashboard menggunakan **email whitelist** atau **custom callback function**.

---

## Konfigurasi

### File: `config/iam.php`

```php
'admin_access' => [
    'method' => 'email',  // email | callback | both | either
    'allowed_emails' => ['admin@gmail.com'],
    'callback' => null,   // Custom function
    'denied_message' => 'Access denied...',
    'denied_redirect' => null,  // null = 403 page, '/' = redirect home
],

'pulse_access' => [
    'use_iam_admin_rules' => true,  // Use same rules as IAM admin
    'allowed_emails' => ['admin@gmail.com'],
    'callback' => null,
],
```

---

## Environment Variables

### `.env` Configuration

```bash
# IAM Admin Access - Email Whitelist
IAM_ADMIN_EMAILS=admin@gmail.com,another@example.com

# Access Method
IAM_ADMIN_ACCESS_METHOD=email

# Custom Messages (Optional)
IAM_ADMIN_DENIED_MESSAGE="Access denied. Only authorized users."
IAM_ADMIN_DENIED_REDIRECT=/

# Pulse Dashboard
PULSE_USE_IAM_ADMIN_RULES=true
# PULSE_ADMIN_EMAILS=admin@gmail.com  # Only if PULSE_USE_IAM_ADMIN_RULES=false
```

---

## Access Methods

### 1. Email Only (Default)

```bash
IAM_ADMIN_ACCESS_METHOD=email
IAM_ADMIN_EMAILS=admin@gmail.com,manager@company.com
```

**Behavior**: Only listed emails can access.

### 2. Callback Function

```php
// config/iam.php
'admin_access' => [
    'method' => 'callback',
    'callback' => function ($user) {
        // Check custom attribute
        return $user->is_super_admin === true;
    },
],
```

```bash
IAM_ADMIN_ACCESS_METHOD=callback
```

### 3. Both (Email AND Callback)

```php
'admin_access' => [
    'method' => 'both',  // Must pass BOTH checks
    'allowed_emails' => ['admin@gmail.com'],
    'callback' => function ($user) {
        return $user->email_verified_at !== null;
    },
],
```

**Behavior**: Email must be in whitelist AND callback must return true.

### 4. Either (Email OR Callback)

```php
'admin_access' => [
    'method' => 'either',  // Must pass ONE check
    'allowed_emails' => ['admin@gmail.com'],
    'callback' => function ($user) {
        return $user->hasPermissionTo('access-iam-panel');
    },
],
```

**Behavior**: Email in whitelist OR callback returns true.

---

## Usage Examples

### Basic: Single Admin

```bash
# .env
IAM_ADMIN_EMAILS=admin@gmail.com
IAM_ADMIN_ACCESS_METHOD=email
```

### Multiple Admins

```bash
# .env
IAM_ADMIN_EMAILS=admin@gmail.com,manager@company.com,superuser@domain.com
```

### Email + Verified Account

```php
// config/iam.php
'admin_access' => [
    'method' => 'both',
    'allowed_emails' => explode(',', env('IAM_ADMIN_EMAILS', 'admin@gmail.com')),
    'callback' => function ($user) {
        return $user->email_verified_at !== null;
    },
],
```

### Email OR Special Permission

```php
'admin_access' => [
    'method' => 'either',
    'allowed_emails' => ['admin@gmail.com'],
    'callback' => function ($user) {
        // Using Spatie Permission package
        return $user->hasPermissionTo('manage-iam-panel');
    },
],
```

### Custom Attribute Check

```php
'callback' => function ($user) {
    // Check database attribute
    return $user->is_iam_administrator === true;
}
```

### Role-Based (Using IAM Roles)

```php
'callback' => function ($user) {
    return $user->isIAMAdmin(); // Your custom method
}
```

---

## Middleware

### CheckIAMAdmin

Digunakan untuk Filament Panel:

```php
// app/Providers/Filament/PanelPanelProvider.php
->authMiddleware([
    \App\Http\Middleware\Authenticate::class,
    \App\Http\Middleware\CheckIAMAdmin::class,
])
```

### CheckPulseAccess

Digunakan untuk Pulse Dashboard:

```php
// config/pulse.php
'middleware' => [
    'web',
    \App\Http\Middleware\CheckPulseAccess::class,
],
```

---

## Access Denial Behavior

### Show 403 Error Page (Default)

```bash
IAM_ADMIN_DENIED_REDIRECT=
# or
# IAM_ADMIN_DENIED_REDIRECT=null
```

User akan melihat halaman 403 Forbidden.

### Redirect to Home

```bash
IAM_ADMIN_DENIED_REDIRECT=/
```

User akan di-redirect ke homepage dengan flash message.

### Custom Redirect

```bash
IAM_ADMIN_DENIED_REDIRECT=/dashboard
```

---

## Testing

### Test Access via Tinker

```php
php artisan tinker

$user = User::where('email', 'admin@gmail.com')->first();

// Check email whitelist
$allowed = in_array($user->email, config('iam.admin_access.allowed_emails'));
dump($allowed);

// Test callback
$callback = config('iam.admin_access.callback');
if (is_callable($callback)) {
    dump($callback($user));
}
```

### Test Access via Browser

1. Login sebagai `admin@gmail.com` → Access granted ✓
2. Login sebagai `doctor@gmail.com` → Access denied ✗
3. Try `/panel` route
4. Try `/pulse` route

---

## Pulse Dashboard Configuration

### Use Same Rules as IAM Admin (Default)

```bash
PULSE_USE_IAM_ADMIN_RULES=true
```

Pulse akan menggunakan rules yang sama dengan IAM admin panel.

### Separate Configuration

```bash
PULSE_USE_IAM_ADMIN_RULES=false
PULSE_ADMIN_EMAILS=admin@gmail.com,devops@company.com
```

Pulse memiliki whitelist email terpisah.

```php
// config/iam.php
'pulse_access' => [
    'use_iam_admin_rules' => false,
    'allowed_emails' => ['admin@gmail.com', 'devops@company.com'],
    'callback' => function ($user) {
        return $user->hasRole('devops');
    },
],
```

---

## Security Best Practices

### 1. Use Environment Variables

```bash
# ✓ GOOD - Easy to change per environment
IAM_ADMIN_EMAILS=admin@gmail.com

# ✗ BAD - Hardcoded in config
```

### 2. Multiple Admins

```bash
# Production
IAM_ADMIN_EMAILS=cto@company.com,admin@company.com

# Staging
IAM_ADMIN_EMAILS=developer@company.com,qa@company.com

# Local Development
IAM_ADMIN_EMAILS=admin@gmail.com,test@local
```

### 3. Combine with Email Verification

```php
'callback' => function ($user) {
    return $user->email_verified_at !== null;
}
```

### 4. IP Whitelist (Advanced)

```php
'callback' => function ($user) {
    $allowedIPs = ['192.168.1.100', '10.0.0.50'];
    return in_array(request()->ip(), $allowedIPs);
}
```

### 5. Time-Based Access

```php
'callback' => function ($user) {
    // Only allow during business hours
    $hour = now()->hour;
    return $hour >= 8 && $hour <= 18;
}
```

---

## Migration from Role-Based

If you previously used role-based access:

### Old System (Role-Based)

```php
// ❌ OLD
$user->isIAMAdmin(); // Check IAM admin role
```

### New System (Email-Based)

```bash
# ✓ NEW - Configuration
IAM_ADMIN_EMAILS=admin@gmail.com,manager@company.com
```

Or keep role-based with callback:

```php
// ✓ NEW - Callback with role check
'callback' => function ($user) {
    return $user->isIAMAdmin();
}
```

---

## Troubleshooting

### Issue: Access Denied for Admin Email

**Check 1**: Verify email in config
```php
php artisan tinker
config('iam.admin_access.allowed_emails')
```

**Check 2**: Clear config cache
```bash
php artisan config:clear
```

**Check 3**: Check .env file
```bash
grep IAM_ADMIN_EMAILS .env
```

### Issue: Callback Not Working

**Check**: Is callback callable?
```php
php artisan tinker
$callback = config('iam.admin_access.callback');
var_dump(is_callable($callback));
```

### Issue: Multiple Emails Not Working

**Check**: Proper comma separation without spaces
```bash
# ✓ GOOD
IAM_ADMIN_EMAILS=admin@gmail.com,user@example.com

# ✗ BAD - Spaces will be included
IAM_ADMIN_EMAILS=admin@gmail.com, user@example.com
```

The middleware trims spaces, but it's better to avoid them.

---

## API Reference

### Config Keys

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `iam.admin_access.method` | string | `'email'` | Access check method |
| `iam.admin_access.allowed_emails` | array | `['admin@gmail.com']` | Email whitelist |
| `iam.admin_access.callback` | callable\|null | `null` | Custom check function |
| `iam.admin_access.denied_message` | string | `'Access denied...'` | Error message |
| `iam.admin_access.denied_redirect` | string\|null | `null` | Redirect URL or null for 403 |
| `iam.pulse_access.use_iam_admin_rules` | bool | `true` | Use IAM admin rules |
| `iam.pulse_access.allowed_emails` | array | `['admin@gmail.com']` | Pulse email whitelist |
| `iam.pulse_access.callback` | callable\|null | `null` | Pulse custom check |

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `IAM_ADMIN_EMAILS` | `admin@gmail.com` | Comma-separated emails |
| `IAM_ADMIN_ACCESS_METHOD` | `email` | Access method |
| `IAM_ADMIN_DENIED_MESSAGE` | `'Access denied...'` | Custom message |
| `IAM_ADMIN_DENIED_REDIRECT` | `null` | Redirect URL |
| `PULSE_USE_IAM_ADMIN_RULES` | `true` | Use IAM rules for Pulse |
| `PULSE_ADMIN_EMAILS` | `admin@gmail.com` | Pulse-specific emails |

---

## Summary

- ✅ Email-based access control (default)
- ✅ Custom callback support
- ✅ Flexible combination (both/either)
- ✅ Separate Pulse configuration
- ✅ Environment variable configuration
- ✅ Configurable denial behavior
- ✅ Easy to manage in production

**Default Configuration**: Only `admin@gmail.com` can access IAM panel and Pulse.
