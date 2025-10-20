<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SSO Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi untuk sistem logging SSO dengan 4 kriteria utama
    |
    */

    // Authentication Flow Logging
    'auth_flow' => [
        'enabled' => env('SSO_LOG_AUTH_FLOW', true),
        'level' => env('SSO_LOG_AUTH_FLOW_LEVEL', 'info'),
        'include_user_data' => env('SSO_LOG_INCLUDE_USER_DATA', true),
        'include_request_details' => env('SSO_LOG_INCLUDE_REQUEST_DETAILS', true),
    ],

    // Token Management Logging
    'token_management' => [
        'enabled' => env('SSO_LOG_TOKEN_MGMT', true),
        'level' => env('SSO_LOG_TOKEN_MGMT_LEVEL', 'info'),
        'log_token_preview_length' => env('SSO_LOG_TOKEN_PREVIEW_LENGTH', 20),
        'include_payload_details' => env('SSO_LOG_INCLUDE_PAYLOAD_DETAILS', true),
    ],

    // Security Events Logging
    'security_events' => [
        'enabled' => env('SSO_LOG_SECURITY_EVENTS', true),
        'level' => env('SSO_LOG_SECURITY_EVENTS_LEVEL', 'warning'),
        'rate_limit_threshold' => env('SSO_RATE_LIMIT_THRESHOLD', 30), // requests per minute
        'suspicious_user_agent_detection' => env('SSO_SUSPICIOUS_UA_DETECTION', true),
        'log_failed_attempts' => env('SSO_LOG_FAILED_ATTEMPTS', true),
    ],

    // Performance Metrics Logging
    'performance_metrics' => [
        'enabled' => env('SSO_LOG_PERFORMANCE', true),
        'level' => env('SSO_LOG_PERFORMANCE_LEVEL', 'info'),
        'slow_threshold_ms' => env('SSO_SLOW_THRESHOLD_MS', 2000), // 2 seconds
        'track_memory_usage' => env('SSO_TRACK_MEMORY_USAGE', true),
        'track_database_queries' => env('SSO_TRACK_DB_QUERIES', true),
    ],

    // General Logging Settings
    'general' => [
        'channel' => env('SSO_LOG_CHANNEL', 'single'), // Log channel to use
        'include_stack_trace' => env('SSO_LOG_INCLUDE_STACK_TRACE', false),
        'sanitize_sensitive_data' => env('SSO_LOG_SANITIZE_SENSITIVE', true),
        'max_context_size' => env('SSO_LOG_MAX_CONTEXT_SIZE', 10000), // Max bytes for context
    ],

    // Alert Thresholds
    'alerts' => [
        'error_rate_threshold' => env('SSO_ALERT_ERROR_RATE_THRESHOLD', 10), // % per hour
        'failed_login_threshold' => env('SSO_ALERT_FAILED_LOGIN_THRESHOLD', 5), // attempts per IP per hour
        'performance_degradation_threshold' => env('SSO_ALERT_PERF_THRESHOLD', 5000), // ms
        'security_event_threshold' => env('SSO_ALERT_SECURITY_THRESHOLD', 3), // events per IP per hour
    ],

    // Data Retention
    'retention' => [
        'auth_flow_days' => env('SSO_LOG_AUTH_FLOW_RETENTION', 30),
        'token_mgmt_days' => env('SSO_LOG_TOKEN_MGMT_RETENTION', 90),
        'security_events_days' => env('SSO_LOG_SECURITY_RETENTION', 365),
        'performance_days' => env('SSO_LOG_PERFORMANCE_RETENTION', 7),
    ],

    // Export and Analysis
    'export' => [
        'enabled' => env('SSO_LOG_EXPORT_ENABLED', true),
        'formats' => ['json', 'csv', 'excel'], // Supported export formats
        'max_records_per_export' => env('SSO_LOG_MAX_EXPORT_RECORDS', 10000),
    ],

    // Development and Debugging
    'debug' => [
        'enabled' => env('SSO_LOG_DEBUG', false),
        'verbose_errors' => env('SSO_LOG_VERBOSE_ERRORS', false),
        'include_full_request' => env('SSO_LOG_INCLUDE_FULL_REQUEST', false),
        'include_full_response' => env('SSO_LOG_INCLUDE_FULL_RESPONSE', false),
    ],
];
