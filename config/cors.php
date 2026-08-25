<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    /*
     * `storage/*` is here for deep zoom: OpenSeadragon fetches the .dzi
     * descriptor over XHR, which is subject to CORS even though the tiles
     * themselves are plain <img> requests that are not. Without it the viewer
     * fails with "HTTP 0" when the site and the API are on different origins.
     */
    'paths' => ['api/*', 'storage/*'],

    'allowed_methods' => ['*'],

    // The portfolio is the only browser client. Listed explicitly rather than
    // '*' so the public write endpoint cannot be driven from arbitrary sites.
    'allowed_origins' => array_filter(
        array_map('trim', explode(',', (string) env('FRONTEND_ORIGINS', 'http://localhost:5173,http://localhost:5174')))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
