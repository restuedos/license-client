<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Verify endpoint (license-server)
    |--------------------------------------------------------------------------
    */
    'verify_url' => env('LICENSE_VERIFY_URL'),

    /*
    |--------------------------------------------------------------------------
    | HTTP enforcement (EDZero middleware)
    |--------------------------------------------------------------------------
    |
    | When LICENSE_VERIFY_URL is empty, licensing is not enforced (app runs without
    | activation). When the URL is set, enforcement defaults to true; set
    | LICENSE_ENFORCEMENT_ENABLED=false only for local debugging — never in production.
    |
    */
    'enforce' => filter_var(
        (($enforce = env('LICENSE_ENFORCEMENT_ENABLED')) === null || $enforce === '') ? 'true' : $enforce,
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    | Must match license-server LICENSE_PRODUCTION_ENVIRONMENTS (activation buckets).
    */
    'production_environment_names' => array_values(array_unique(array_filter(array_map(
        static fn (string $s): string => strtolower(trim($s)),
        explode(',', (string) env('LICENSE_PRODUCTION_ENVIRONMENTS', 'production,prod,prd'))
    )))),

    /*
    |--------------------------------------------------------------------------
    | Transport security
    |--------------------------------------------------------------------------
    |
    | When true, LICENSE_VERIFY_URL must use https:// and LICENSE_ALLOWED_HOSTS
    | must list at least one hostname (exact match to the URL host). This pins
    | the client to your license API and avoids a silent "any host" allowlist.
    |
    */
    'require_https' => (bool) env('LICENSE_REQUIRE_HTTPS', true),

    'allowed_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('LICENSE_ALLOWED_HOSTS', ''))
    ))),

    'encryption_public_key_path' => env('LICENSE_ENCRYPTION_PUBLIC_KEY_PATH', storage_path('app/private/license-encryption-public.rsa')),

    'verification_public_key_path' => env('LICENSE_VERIFICATION_PUBLIC_KEY_PATH', storage_path('app/private/license-verification-public.rsa')),

    'status_file' => env('LICENSE_STATUS_FILE', storage_path('app/private/license.json')),

    'request_timeout' => (int) env('LICENSE_VERIFY_TIMEOUT', 10),

    'clock_skew_seconds' => (int) env('LICENSE_CLOCK_SKEW_SECONDS', 120),

    'request_hmac_secret' => env('LICENSE_REQUEST_HMAC_SECRET'),

    'local_hmac_secret' => env('LICENSE_LOCAL_HMAC_SECRET', env('APP_KEY')),

    'device_fingerprint' => env('LICENSE_DEVICE_FINGERPRINT'),

];
