# Client Integration Guide

Quick guide untuk mengintegrasikan aplikasi klien (SIIMUT, dll) dengan IAM Server.

---

## Prerequisites

- Laravel 10+ application
- Access ke IAM Server
- Application credentials (app_key & app_secret)

---

## Installation Steps

### 1. Install Dependencies

```bash
composer require firebase/php-jwt
```

### 2. Environment Configuration

Tambahkan ke `.env`:

```env
IAM_SERVER_URL=https://iam.rs.id
IAM_APP_KEY=siimut.app
IAM_APP_SECRET=your_client_secret_here
IAM_REDIRECT_URI=https://your-app.rs.id/auth/callback
```

### 3. Create Config File

`config/iam.php`:

```php
<?php

return [
    'server_url' => env('IAM_SERVER_URL', 'https://iam.rs.id'),
    'app_key' => env('IAM_APP_KEY'),
    'app_secret' => env('IAM_APP_SECRET'),
    'redirect_uri' => env('IAM_REDIRECT_URI'),
    'public_key' => env('IAM_PUBLIC_KEY', env('APP_KEY')), // For JWT verification
];
```

---

## Copy Required Files from IAM

Copy these files from IAM repository ke aplikasi Anda:

```
app/Services/JWTTokenService.php
app/Http/Middleware/VerifyIAMAccessToken.php
app/Http/Middleware/InjectIAMUserContext.php
app/Http/Middleware/CheckIAMPermission.php
app/Http/Middleware/CheckIAMRole.php
```

**Important:** Update `JWTTokenService.php` constructor untuk use `config('iam.public_key')` instead of `config('app.key')`.

---

## Register Middleware

Di `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'iam.verify' => \App\Http\Middleware\VerifyIAMAccessToken::class,
        'iam.inject' => \App\Http\Middleware\InjectIAMUserContext::class,
        'iam.permission' => \App\Http\Middleware\CheckIAMPermission::class,
        'iam.role' => \App\Http\Middleware\CheckIAMRole::class,
    ]);
})
```

---

## Implement SSO Auth Controller

`app/Http/Controllers/Auth/IAMAuthController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IAMAuthController extends Controller
{
    /**
     * Redirect to IAM for authentication.
     */
    public function redirectToIAM()
    {
        $state = Str::random(40);
        session(['iam_state' => $state]);

        $query = http_build_query([
            'app_key' => config('iam.app_key'),
            'redirect_uri' => config('iam.redirect_uri'),
            'state' => $state,
        ]);

        return redirect(config('iam.server_url').'/oauth/authorize?'.$query);
    }

    /**
     * Handle IAM callback.
     */
    public function handleCallback(Request $request)
    {
        // Validate state (CSRF protection)
        if ($request->state !== session('iam_state')) {
            return redirect('/login')->withErrors(['error' => 'Invalid state parameter']);
        }

        session()->forget('iam_state');

        // Exchange authorization code for tokens
        $response = Http::asForm()->post(config('iam.server_url').'/oauth/token', [
            'grant_type' => 'authorization_code',
            'app_key' => config('iam.app_key'),
            'app_secret' => config('iam.app_secret'),
            'code' => $request->code,
            'redirect_uri' => config('iam.redirect_uri'),
        ]);

        if (! $response->successful()) {
            return redirect('/login')->withErrors(['error' => 'Authentication failed']);
        }

        $data = $response->json();

        // Store tokens in session
        session([
            'iam_access_token' => $data['access_token'],
            'iam_refresh_token' => $data['refresh_token'],
            'iam_expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        // Optionally fetch user info
        $userInfo = $this->getUserInfo($data['access_token']);
        
        if ($userInfo) {
            session(['iam_user' => $userInfo]);
        }

        return redirect()->intended('/dashboard');
    }

    /**
     * Logout from application.
     */
    public function logout()
    {
        session()->forget(['iam_access_token', 'iam_refresh_token', 'iam_expires_at', 'iam_user']);
        return redirect('/login');
    }

    // Note: jika aplikasi menggunakan package `juniyasyos/iam-client`, package
    // sudah menyediakan `GET /iam/logout` sehingga method ini bersifat fallback saja.
    // Catatan penting: jika client memakai package, **tidak perlu** menambahkan
    // `logout_uri` secara manual di registrasi aplikasi IAM — server akan otomatis
    // menurunkan endpoint `/iam/logout` dari `redirect_uris`.

    /**
     * Get user info from IAM.
     */
    private function getUserInfo(string $accessToken): ?array
    {
        $response = Http::withToken($accessToken)
            ->get(config('iam.server_url').'/oauth/userinfo');

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Refresh access token.
     */
    public function refreshToken()
    {
        $refreshToken = session('iam_refresh_token');

        if (! $refreshToken) {
            return redirect('/login');
        }

        $response = Http::asForm()->post(config('iam.server_url').'/oauth/token', [
            'grant_type' => 'refresh_token',
            'app_key' => config('iam.app_key'),
            'app_secret' => config('iam.app_secret'),
            'refresh_token' => $refreshToken,
        ]);

        if (! $response->successful()) {
            return redirect('/login');
        }

        $data = $response->json();

        session([
            'iam_access_token' => $data['access_token'],
            'iam_expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return back();
    }
}
```

---

## Add Routes

`routes/web.php`:

```php
use App\Http\Controllers\Auth\IAMAuthController;

Route::get('/login/iam', [IAMAuthController::class, 'redirectToIAM'])
    ->name('login.iam');

Route::get('/auth/callback', [IAMAuthController::class, 'handleCallback'])
    ->name('auth.callback');

Route::post('/logout', [IAMAuthController::class, 'logout'])
    ->name('logout');

Route::get('/auth/refresh', [IAMAuthController::class, 'refreshToken'])
    ->name('auth.refresh');
```

---

## Protect Routes

`routes/api.php`:

```php
use App\Http\Controllers\PatientController;

// Protected routes - require valid IAM token
Route::middleware(['iam.verify', 'iam.inject'])->group(function () {
    
    // Basic authenticated endpoint
    Route::get('/profile', function (Request $request) {
        return response()->json([
            'user_id' => $request->get('iam_user_id'),
            'name' => $request->get('iam_user_name'),
            'email' => $request->get('iam_user_email'),
            'roles' => $request->get('iam_user_roles'),
            'permissions' => $request->get('iam_user_permissions'),
            'unit' => $request->get('iam_user_unit'),
        ]);
    });
    
    // Require specific permission
    Route::middleware('iam.permission:read:patients')->group(function () {
        Route::get('/patients', [PatientController::class, 'index']);
    });
    
    // Require specific role
    Route::middleware('iam.role:doctor,nurse')->group(function () {
        Route::get('/patients/{id}', [PatientController::class, 'show']);
    });
    
    // Multiple permissions (user needs at least one)
    Route::middleware('iam.permission:write:patients,admin-override')->group(function () {
        Route::post('/patients', [PatientController::class, 'store']);
    });
});
```

---

## Access User Context in Controllers

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        // Get user info from IAM token
        $userId = $request->get('iam_user_id');
        $userRoles = $request->get('iam_user_roles');
        $userPermissions = $request->get('iam_user_permissions');
        $userUnit = $request->get('iam_user_unit');

        // Or get full token payload
        $token = $request->attributes->get('iam_token');

        // Use permissions for authorization
        if (in_array('read:all-patients', $userPermissions)) {
            // User can read all patients
            $patients = Patient::all();
        } else {
            // Filter by user's unit
            $patients = Patient::where('unit', $userUnit)->get();
        }

        return response()->json($patients);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'age' => 'required|integer',
            // ...
        ]);

        // Track who created the patient
        $patient = Patient::create([
            ...$validated,
            'created_by_iam_user_id' => $request->get('iam_user_id'),
            'unit' => $request->get('iam_user_unit'),
        ]);

        return response()->json($patient, 201);
    }
}
```

---

## Frontend Integration (Vue/React)

### Store Token in Frontend

**Option 1: Session-based (Recommended)**

Login button redirects to `/login/iam`, tokens stored in Laravel session. Frontend receives session cookie.

**Frontend:**
```javascript
// Login
window.location.href = '/login/iam';

// API calls use session cookie automatically
fetch('/api/patients', {
    credentials: 'include' // Send cookies
})
```

---

**Option 2: Token-based (SPA)**

Frontend stores token in localStorage/sessionStorage.

**Frontend:**
```javascript
// After callback, store token
const token = new URLSearchParams(window.location.search).get('token');
localStorage.setItem('iam_access_token', token);

// API calls include token
fetch('/api/patients', {
    headers: {
        'Authorization': `Bearer ${localStorage.getItem('iam_access_token')}`
    }
})
```

**Backend adjustment** for SPA (return token in callback):

```php
public function handleCallback(Request $request)
{
    // ... existing code ...

    // For SPA, return token instead of storing in session
    return redirect('/dashboard?token='.$data['access_token']);
}
```

---

### Auto Token Refresh

```javascript
// Check if token is expiring soon
function isTokenExpiring() {
    const expiresAt = localStorage.getItem('iam_expires_at');
    if (!expiresAt) return true;
    
    const expiresAtDate = new Date(expiresAt);
    const now = new Date();
    const minutesUntilExpiry = (expiresAtDate - now) / 1000 / 60;
    
    return minutesUntilExpiry < 5; // Refresh if < 5 minutes left
}

// Refresh token
async function refreshToken() {
    const response = await fetch('/auth/refresh', {
        method: 'GET',
        credentials: 'include'
    });
    
    if (response.ok) {
        console.log('Token refreshed');
    } else {
        // Redirect to login
        window.location.href = '/login/iam';
    }
}

// Setup axios interceptor (if using axios)
axios.interceptors.response.use(
    response => response,
    async error => {
        if (error.response?.status === 401) {
            // Token expired, try refresh
            await refreshToken();
            // Retry original request
            return axios(error.config);
        }
        return Promise.reject(error);
    }
);
```

---

## Testing

### Test Login Flow

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class IAMAuthTest extends TestCase
{
    public function test_redirect_to_iam()
    {
        $response = $this->get('/login/iam');

        $response->assertRedirect();
        $this->assertStringContainsString(
            config('iam.server_url').'/oauth/authorize',
            $response->headers->get('Location')
        );
    }

    public function test_protected_route_without_token()
    {
        $response = $this->getJson('/api/patients');

        $response->assertStatus(401);
        $response->assertJson(['error' => 'unauthorized']);
    }

    public function test_protected_route_with_valid_token()
    {
        // Mock valid token
        $token = 'valid.jwt.token';

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/patients');

        // Will fail without proper token setup, but demonstrates structure
        $response->assertStatus(200);
    }
}
```

---

## Troubleshooting

### Common Issues

**1. "Invalid state parameter"**
- Check session storage configuration
- Ensure cookies are enabled
- Verify session lifetime is sufficient

**2. "Authentication failed"**
- Verify IAM_APP_KEY and IAM_APP_SECRET are correct
- Check IAM server is reachable
- Verify redirect_uri matches exactly with registered URI

**3. "Invalid or expired token"**
- Token may have expired (default: 1 hour)
- Implement auto token refresh
- Check APP_KEY matches between IAM & client

**4. "Forbidden - Insufficient permissions"**
- User doesn't have required permission
- Check permission name matches exactly
- Verify user has role with permission in IAM

---

## Best Practices

1. **Always use HTTPS** in production
2. **Implement token refresh** before expiry
3. **Validate state parameter** for CSRF protection
4. **Handle token expiration** gracefully
5. **Log authentication events** for audit
6. **Use permission-based authorization** instead of role-based when possible
7. **Cache user info** to reduce API calls to IAM
8. **Implement logout** to clear session/tokens

---

## Back‑channel logout (server → server) — recommended

If your application receives SSO sessions from IAM, implement a public back‑channel endpoint so IAM can notify the client to invalidate the user's session **without requiring the user to reload other tabs**.

Why: front‑channel redirects only affect the browser tab that follows the logout chain. Back‑channel lets IAM proactively tell clients (server→server) to clear sessions/tokens immediately.

Quick summary (copy‑paste):

- Add a public `POST /iam/backchannel-logout` route in the client.
- Verify HMAC signature header (default: `IAM-Signature`) using the shared `SSO_SECRET`.
- Invalidate server session(s) or revoke tokens for the supplied `user.id`.
- Return 200 OK.

Full implementation examples and tests: see `docs/CLIENT-BACKCHANNEL-LOGOUT.md`.

---

## Example API Request

```bash
# Get access token (after SSO login)
ACCESS_TOKEN="your_access_token_here"

# Make authenticated API call
curl -X GET https://your-app.rs.id/api/patients \
  -H "Authorization: Bearer $ACCESS_TOKEN" \
  -H "Content-Type: application/json"

# Response
{
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "age": 45,
      "unit": "ICU"
    }
  ]
}
```

---

## Next Steps

1. Register your application in IAM admin panel
2. Obtain app_key and app_secret
3. Add redirect_uris to application settings
4. Test SSO flow in development
5. Configure RBAC (roles & permissions) for your app
6. Deploy to production with HTTPS

---

## Support

For issues or questions:
- Check main documentation: `docs/IAM-SSO-RBAC-DOCUMENTATION.md`
- IAM Server repository: https://github.com/juniyasyos/laravel-iam
- Contact: ahmad.ilyas@rs.id
