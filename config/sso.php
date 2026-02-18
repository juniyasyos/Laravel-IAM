<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SSO Issuer
    |--------------------------------------------------------------------------
    |
    | Identifies the IAM server in issued tokens. Override via the SSO_ISSUER
    | environment variable if you need a custom identifier.
    |
    */
    'issuer' => env('SSO_ISSUER', env('APP_URL', 'iam-server')),

    /*
    |--------------------------------------------------------------------------
    | Signing Secret
    |--------------------------------------------------------------------------
    |
    | HMAC secret used to sign and verify internal SSO tokens. Make sure this
    | value stays private across your infrastructure. Set in the SSO_SECRET
    | environment variable.
    |
    */
    'secret' => env('SSO_SECRET', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Token Time To Live
    |--------------------------------------------------------------------------
    |
    | The lifetime (in seconds) for issued SSO tokens. Override with SSO_TTL
    | to adjust expiry according to your security requirements.
    |
    */
    'ttl' => (int) env('SSO_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Back‑channel logout (optional)
    |--------------------------------------------------------------------------
    |
    | When enabled, IAM will attempt server‑to‑server (back‑channel) logout
    | notifications to client applications. The notification is a signed
    | POST request sent to the client's derived back‑channel URI (default:
    | <base>/iam/backchannel-logout). Clients must opt in and verify the
    | HMAC signature using the shared SSO secret.
    |
    */
    'backchannel' => [
        'enabled' => env('SSO_BACKCHANNEL_LOGOUT', false),
        'path' => env('SSO_BACKCHANNEL_LOGOUT_PATH', '/iam/backchannel-logout'),
        'signature_header' => env('SSO_BACKCHANNEL_SIGNATURE_HEADER', 'IAM-Signature'),
    ],
];
