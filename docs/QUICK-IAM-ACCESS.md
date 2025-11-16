# Quick Reference: IAM Admin Access

## Default Configuration

Only `admin@gmail.com` can access:
- ✅ Filament IAM Panel (`/panel`)
- ✅ Pulse Dashboard (`/pulse`)

---

## Change Allowed Users

### .env
```bash
# Single user
IAM_ADMIN_EMAILS=admin@gmail.com

# Multiple users
IAM_ADMIN_EMAILS=admin@gmail.com,manager@company.com,superuser@domain.com
```

After changing, run:
```bash
php artisan config:clear
```

---

## Access Methods

### 1. Email Only (Default)
```bash
IAM_ADMIN_ACCESS_METHOD=email
IAM_ADMIN_EMAILS=admin@gmail.com
```

### 2. Custom Function
```php
// config/iam.php
'admin_access' => [
    'method' => 'callback',
    'callback' => function ($user) {
        return $user->is_super_admin === true;
    },
],
```

### 3. Email AND Custom Function
```bash
IAM_ADMIN_ACCESS_METHOD=both
```
Must pass BOTH email whitelist AND callback.

### 4. Email OR Custom Function
```bash
IAM_ADMIN_ACCESS_METHOD=either
```
Must pass ONE of: email whitelist OR callback.

---

## Common Examples

### Add New Admin
```bash
# Change from:
IAM_ADMIN_EMAILS=admin@gmail.com

# To:
IAM_ADMIN_EMAILS=admin@gmail.com,newadmin@company.com

# Then:
php artisan config:clear
```

### Require Email Verification
```php
// config/iam.php
'admin_access' => [
    'method' => 'both',
    'allowed_emails' => explode(',', env('IAM_ADMIN_EMAILS')),
    'callback' => function ($user) {
        return $user->email_verified_at !== null;
    },
],
```

### Use IAM Role (If you want role-based)
```php
'admin_access' => [
    'method' => 'callback',
    'callback' => function ($user) {
        return $user->isIAMAdmin(); // Your custom method
    },
],
```

---

## Pulse Dashboard

### Use Same Rules as IAM Admin (Default)
```bash
PULSE_USE_IAM_ADMIN_RULES=true
```

### Separate Configuration
```bash
PULSE_USE_IAM_ADMIN_RULES=false
PULSE_ADMIN_EMAILS=admin@gmail.com,devops@company.com
```

---

## Testing

```php
php artisan tinker

// Check configuration
config('iam.admin_access.allowed_emails');
// Output: ['admin@gmail.com']

// Test user access
$user = User::where('email', 'admin@gmail.com')->first();
in_array($user->email, config('iam.admin_access.allowed_emails'));
// Output: true
```

---

## Access Denied Behavior

### Show 403 Error (Default)
```bash
IAM_ADMIN_DENIED_REDIRECT=
```

### Redirect to Home
```bash
IAM_ADMIN_DENIED_REDIRECT=/
```

---

## Files Modified

1. ✅ `config/iam.php` - Main configuration
2. ✅ `app/Http/Middleware/CheckIAMAdmin.php` - Filament panel check
3. ✅ `app/Http/Middleware/CheckPulseAccess.php` - Pulse check
4. ✅ `config/pulse.php` - Pulse middleware
5. ✅ `app/Providers/Filament/PanelPanelProvider.php` - Filament middleware
6. ✅ `.env.example` - Environment variables template

---

## Summary

- **Default**: Only `admin@gmail.com` has access
- **Change**: Update `IAM_ADMIN_EMAILS` in `.env`
- **Multiple**: Comma-separated emails
- **Advanced**: Use callback function for custom logic
- **Pulse**: Uses same rules by default

See full documentation: `docs/IAM-ADMIN-ACCESS-CONTROL.md`
