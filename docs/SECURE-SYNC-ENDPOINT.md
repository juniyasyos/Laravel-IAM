# Secure Sync Endpoint (client application)

When a client application exposes a `/api/iam/sync-users` (or `/sync-roles`)
endpoint it is accepting a potentially sensitive batch of user/role data from the
IAM server.  **Do not treat this as a public API.**

Below are recommended protections before you deploy the route.

## 1. Transport security

* **HTTPS only** – enforce `Scheme::https` and redirect or reject plain HTTP.
* Consider **mutual TLS** if you need cryptographic assurance of the IAM server
  certificate.

## 2. Shared secret / HMAC signature

The simplest model is an HMAC of the request body using a shared secret stored
in both the client and IAM configuration.  The IAM service already uses this
pattern for back‑channel logout notifications, so you can reuse the same
mechanism.

### Request example (IAM → client)

```http
POST /api/iam/sync-users?app_key=siimut HTTP/1.1
Host: example.com
Content-Type: application/json
Accept: application/json
IAM-Signature: <hmac sha256 of body using secret>
X-IAM-Request-Id: iam_req_5f3b8a

{
    "users": [
        {"nip":"12345678","email":"user@example.com","name":"John Doe","active":true,"roles":["editor","reviewer"]},
        …
    ]
}
```

On the client side verify the signature before processing:

```php
public function handleSync(Request $request)
{
    $body = (string) $request->getContent();
    $sigHeader = config('sso.backchannel.signature_header', 'IAM-Signature');
    $signature = (string) $request->header($sigHeader);
    $expected = hash_hmac('sha256', $body, config('sso.secret'));

    if (! hash_equals($expected, $signature)) {
        abort(401, 'Invalid signature.');
    }

    // optional: check timestamp header or nonce to prevent replay attacks
    $appKey = $request->query('app_key');
    // ... validate $appKey if necessary

    $data = $request->json('users', []);
    // process users
}
```

<p align="center"><em>Laravel route with middleware</em></p>

```php
// routes/api.php
Route::post('iam/sync-users', [SyncUsersController::class, 'handle'])
     ->middleware(['throttle:10,1', 'iam.sync']);
```

```php
// app/Http/Middleware/VerifyIamSignature.php
public function handle($request, Closure $next)
{
    $sigHeader = config('sso.backchannel.signature_header', 'IAM-Signature');
    $signature = $request->header($sigHeader);
    $expected = hash_hmac('sha256', $request->getContent(), config('sso.secret'));

    if (! hash_equals($expected, $signature)) {
        return response('Invalid IAM signature', 401);
    }

    return $next($request);
}
```

## 3. Additional safeguards

* **IP whitelisting** – accept requests only from the known IAM host(s).
* **Rate limiting** – prevent abuse with standard throttling middleware.
* **Audit logging** – record incoming syncs with timestamps, request IDs, and
  source IPs.
* **Version or signature rotation** – support key changes without downtime by
  accepting multiple secrets.
* **Timestamp/nonce** – add a short-lived header to stop replay attacks.
* **Authentication header** – aside from HMAC you may require a bearer token
  issued by the IAM server.

## 4. Client response contract

Return a simple `200` with JSON or `4xx/5xx` on failure.  Example:

```json
{ "success": true }
```

Errors should not disclose sensitive information.

---

Treat the sync endpoints as you would any internal integration: assume the
presence of a malicious actor, validate everything, and log aggressively.
Failure to harden this endpoint can expose your entire user base and their
application privileges.
