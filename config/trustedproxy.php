<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Reverse proxies allowed to set the X-Forwarded-* headers (HTTPS, host,
    | client IP). Read by App\Http\Middleware\TrustProxies.
    |
    | Arena (no proxy in front): leave TRUSTED_PROXIES empty.
    | Behind nginx on a VPS with the app port bound to 127.0.0.1: TRUSTED_PROXIES=*
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
