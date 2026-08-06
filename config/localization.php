<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    |
    | Every translatable field stores one entry per locale key below. Arabic is
    | wired up from day one even though content is authored in English for now,
    | so enabling it later is a content task rather than a migration.
    |
    */

    'supported' => [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'dir' => 'ltr',
        ],
        'ar' => [
            'name' => 'Arabic',
            'native' => 'العربية',
            'dir' => 'rtl',
        ],
    ],

    /*
    | Locale used when a translation is missing for the requested one.
    */
    'fallback' => 'en',

];
