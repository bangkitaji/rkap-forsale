<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Finance Directorate Code
    |--------------------------------------------------------------------------
    |
    | The directorate code used to identify the Finance Director.
    | This is checked on User::isDirekturFinance().
    |
    */
    'finance_directorate_code' => env('RKAP_FINANCE_DIRECTORATE_CODE', 'HF'),

    /*
    |--------------------------------------------------------------------------
    | Admin Email
    |--------------------------------------------------------------------------
    |
    | Default admin email address used in seeders and system references.
    |
    */
    'admin_email' => env('RKAP_ADMIN_EMAIL', 'admin@rkap.com'),

    /*
    |--------------------------------------------------------------------------
    | Seed Default Password
    |--------------------------------------------------------------------------
    |
    | Default password used for seeded users. Should be overridden via .env
    | in non-development environments.
    |
    */
    'seed_default_password' => env('RKAP_SEED_PASSWORD', 'P@ssw0rd!'),

    /*
    |--------------------------------------------------------------------------
    | COA Group Fallback Code
    |--------------------------------------------------------------------------
    |
    | Fallback COA group code when a budget item has no matching COA group.
    |
    */
    'coa_group_fallback_code' => env('RKAP_COA_GROUP_FALLBACK', '999'),
];
