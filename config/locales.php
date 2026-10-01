<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported API / app locales
    |--------------------------------------------------------------------------
    |
    | Used by locale middleware and user-facing language settings. Add new
    | keys here when you introduce more languages, then seed translations
    | for each translatable model field.
    |
    */

    'supported' => ['en', 'ar', 'ur'],

    /*
    |--------------------------------------------------------------------------
    | Fallback locale
    |--------------------------------------------------------------------------
    |
    | Used when Accept-Language / lang / user preference does not match, or
    | when a translation is missing for a given field (Spatie translatable).
    |
    */

    'fallback' => env('APP_FALLBACK_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Right-to-left locales
    |--------------------------------------------------------------------------
    */

    'rtl' => ['ar', 'ur'],

];
