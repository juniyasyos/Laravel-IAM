<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IAM Issuer
    |--------------------------------------------------------------------------
    |
    | The issuer identifier for JWT tokens. This should be the URL of your
    | IAM service and will be included in the 'iss' claim of all tokens.
    |
    */

    'issuer' => env('IAM_ISSUER', env('APP_URL', 'https://iam.local')),

    /*
    |--------------------------------------------------------------------------
    | Token Time-to-Live (TTL)
    |--------------------------------------------------------------------------
    |
    | The default lifetime in seconds for access tokens issued by the IAM.
    | Default is 3600 seconds (1 hour). Individual applications can override
    | this via their token_expiry field.
    |
    */

    'token_ttl' => env('IAM_TOKEN_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | JWT Signing Key
    |--------------------------------------------------------------------------
    |
    | The secret key used to sign JWT tokens. Falls back to APP_KEY if not set.
    | For production, consider using a separate dedicated signing key.
    |
    */

    'signing_key' => env('IAM_SIGNING_KEY', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | JWT Algorithm
    |--------------------------------------------------------------------------
    |
    | The algorithm used to sign JWT tokens. Default is HS256 (HMAC SHA-256).
    | Supported algorithms: HS256, HS384, HS512, RS256, RS384, RS512, etc.
    |
    */

    'algorithm' => env('IAM_JWT_ALGORITHM', 'HS256'),

    /*
    |--------------------------------------------------------------------------
    | Refresh Token TTL
    |--------------------------------------------------------------------------
    |
    | The lifetime in seconds for refresh tokens. Default is 30 days.
    |
    */

    'refresh_token_ttl' => env('IAM_REFRESH_TOKEN_TTL', 86400 * 30),

    /*
    |--------------------------------------------------------------------------
    | Authorization Code TTL
    |--------------------------------------------------------------------------
    |
    | The lifetime in seconds for authorization codes. These should be short-
    | lived. Default is 5 minutes (300 seconds).
    |
    */

    'auth_code_ttl' => env('IAM_AUTH_CODE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Token Audience
    |--------------------------------------------------------------------------
    |
    | Optional audience claim for JWT tokens. If set, this will be included
    | in the 'aud' claim of all tokens. Can be a string or array.
    |
    */

    'audience' => env('IAM_TOKEN_AUDIENCE', null),

    /*
    |--------------------------------------------------------------------------
    | System Roles Protection
    |--------------------------------------------------------------------------
    |
    | Whether to enforce protection on system roles (prevent deletion, slug
    | changes, etc.). Default is true for production safety.
    |
    */

    'protect_system_roles' => env('IAM_PROTECT_SYSTEM_ROLES', true),

    /*
    |--------------------------------------------------------------------------
    | Default User Roles
    |--------------------------------------------------------------------------
    |
    | Optional configuration for automatically assigning default roles to new
    | users for specific applications.
    |
    | Example: ['siimut' => ['viewer'], 'incident' => ['reporter']]
    |
    */

    'default_user_roles' => [
        // 'siimut' => ['viewer'],
    ],

    /*
    |--------------------------------------------------------------------------
    | IAM Admin Access Control
    |--------------------------------------------------------------------------
    |
    | Configure who can access IAM admin panel (Filament) and monitoring tools (Pulse).
    | You can use email whitelist, custom callback function, or both.
    |
    */

    'admin_access' => [

        /*
        |--------------------------------------------------------------------------
        | Access Method
        |--------------------------------------------------------------------------
        |
        | Determine how to check admin access:
        | - 'email': Only check email whitelist
        | - 'callback': Use custom callback function
        | - 'both': Check email AND callback (both must pass)
        | - 'either': Check email OR callback (one must pass)
        |
        */
        'method' => env('IAM_ADMIN_ACCESS_METHOD', 'email'),

        /*
        |--------------------------------------------------------------------------
        | Email Whitelist
        |--------------------------------------------------------------------------
        |
        | List of email addresses that are allowed to access IAM admin panel.
        | Add emails to .env as comma-separated: IAM_ADMIN_EMAILS="admin@gmail.com,user@example.com"
        |
        */
        'allowed_emails' => array_filter(
            array_map('trim', explode(',', env('IAM_ADMIN_EMAILS', 'admin@gmail.com')))
        ),

        /*
        |--------------------------------------------------------------------------
        | Custom Access Callback
        |--------------------------------------------------------------------------
        |
        | Define custom logic to determine admin access.
        | The callback receives the authenticated User model.
        | Return true to grant access, false to deny.
        |
        | Example:
        | 'callback' => function ($user) {
        |     return $user->is_super_admin || $user->hasPermissionTo('access-iam-panel');
        | }
        |
        */
        'callback' => null,

        /*
        |--------------------------------------------------------------------------
        | Access Denied Message
        |--------------------------------------------------------------------------
        |
        | Message to display when access is denied.
        |
        */
        'denied_message' => env(
            'IAM_ADMIN_DENIED_MESSAGE',
            'Access denied. Only authorized IAM administrators can access this area.'
        ),

        /*
        |--------------------------------------------------------------------------
        | Redirect After Denial
        |--------------------------------------------------------------------------
        |
        | Where to redirect users when access is denied.
        | Set to null to show 403 error page instead.
        |
        */
        'denied_redirect' => env('IAM_ADMIN_DENIED_REDIRECT', null), // null = show 403, or '/' for home
    ],

];
