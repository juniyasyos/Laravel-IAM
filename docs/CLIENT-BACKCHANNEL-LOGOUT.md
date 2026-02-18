# Client: Back‑channel logout (Laravel example)

Ringkas: IAM dapat mengirim notifikasi *server→server* saat user logout (back‑channel). Client harus menyediakan endpoint publik yang memverifikasi HMAC signature dan lalu meng‑invalidate session / revoke token user.

## Prasyarat

- Shared SSO secret tersedia di client (set `SSO_SECRET` atau `config('sso.secret')`).
- IAM `config('sso.backchannel.enabled')` diaktifkan.
- `redirect_uris` aplikasi sudah terdaftar di IAM sehingga IAM dapat *derive* back‑channel URI.
- TLS (HTTPS) wajib di production.

## 1) Route (public)

Tambahkan route publik di `routes/web.php` atau `routes/api.php`:

```php
use App\Http\Controllers\IamBackchannelController;

Route::post('/iam/backchannel-logout', [IamBackchannelController::class, 'backchannelLogout'])
    ->name('iam.backchannel.logout');
```

Endpoint ini **tidak** memakai auth middleware (IAM akan memanggilnya).

## 2) Middleware — verifikasi signature (disarankan)

Buat middleware `VerifyIamBackchannelSignature` di `app/Http/Middleware`:

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyIamBackchannelSignature
{
    public function handle(Request $request, Closure $next)
    {
        $header = config('sso.backchannel.signature_header', 'IAM-Signature');
        $signature = (string) $request->header($header, '');
        $body = $request->getContent() ?: '';
        $expected = hash_hmac('sha256', $body, config('sso.secret'));

        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'invalid signature'], 403);
        }

        return $next($request);
    }
}
```

Daftarkan middleware ini di `app/Http/Kernel.php` (routeMiddleware) lalu gunakan pada route jika mau.

## 3) Controller example

Buat `IamBackchannelController::backchannelLogout` (contoh minimal):

```php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IamBackchannelController extends Controller
{
    public function backchannelLogout(Request $request)
    {
        // (1) optional: validate payload structure
        $data = $request->validate([
            'event' => 'required|string|in:logout',
            'user.id' => 'required|integer',
        ]);

        $userId = data_get($data, 'user.id');

        // (2) Invalidate server sessions (database driver example)
        // NOTE: searching payload is brittle — prefer storing session->user mapping at login
        try {
            DB::table('sessions')
                ->where('payload', 'like', '%"user_id";i:'.$userId.'%')
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('backchannel_session_cleanup_failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }

        // (3) Revoke tokens (Sanctum / Passport)
        $user = User::find($userId);
        if ($user) {
            // Sanctum / Passport both expose tokens() relationship in many apps
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }
        }

        Log::info('backchannel_logout_processed', ['user_id' => $userId]);

        return response()->json(['ok' => true]);
    }
}
```

Jika aplikasi Anda menyimpan session↔user mapping saat login (recommended), gunakan mapping itu untuk menghapus session dengan aman.

## 4) Recommended: store session→user mapping at login

Saat user login via SSO, simpan session id / token id ke tabel `user_sessions`:

```php
// example at login
DB::table('user_sessions')->insert([
  'user_id' => $user->id,
  'session_id' => session()->getId(),
  'created_at' => now(),
]);
```

Lalu back‑channel simply deletes rows in `user_sessions` and removes sessions by id — jauh lebih andal daripada LIKE pada `payload`.

## 5) Example PHPUnit feature test (client side)

```php
public function test_backchannel_logout_invalidates_sessions()
{
    config(['sso.secret' => 'testing-secret']);

    $user = User::factory()->create();

    // create token/session for user (example using personal access token)
    $token = $user->createToken('test')->plainTextToken;

    $payload = [
        'event' => 'logout',
        'timestamp' => now()->toIso8601String(),
        'user' => ['id' => $user->id, 'email' => $user->email ?? null],
    ];

    $body = json_encode($payload);
    $sig = hash_hmac('sha256', $body, config('sso.secret'));

    $this->postJson('/iam/backchannel-logout', $payload, ['IAM-Signature' => $sig])
         ->assertOk();

    // assert tokens revoked or session removed
    $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
}
```

## 6) Security & operational notes

- Always require HTTPS for the back‑channel endpoint.
- Verify signature using HMAC (shared `SSO_SECRET`) — **do not** skip verification.
- Optionally restrict IP ranges to IAM service IPs / use mTLS.
- Rate‑limit the endpoint and log calls for audit.
- Make payload small and include `application.app_key` or `app_key` if you want to validate target app.
- Prefer storing session↔user mapping for deterministic invalidation.

## 7) If your clients use `juniyasyos/laravel-iam-client`

Update the plugin to expose a `GET /iam/logout` *and* a `POST /iam/backchannel-logout` handler that:
- Verifies signature header
- Clears session and tokens
- Returns 200 OK

That way every app using the plugin automatically supports back‑channel logout.

---

If you want, saya bisa: (A) buatkan `VerifyIamBackchannelSignature` middleware + example controller file di repo ini, atau (B) hanya tambahkan contoh docs seperti di atas ke `CLIENT-INTEGRATION.md` (sudah ditambahkan). Pilih A atau B.