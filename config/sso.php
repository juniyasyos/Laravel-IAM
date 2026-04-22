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
    | Backchannel configuration
    |--------------------------------------------------------------------------
    |
    | Shared configuration values for SSO token verification and integration
    | with external client applications.
    |
    */
    'backchannel' => [
        'signature_header' => env('SSO_BACKCHANNEL_SIGNATURE_HEADER', 'IAM-Signature'),
    ],
];
