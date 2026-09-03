<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Company / Tenant Information
    |--------------------------------------------------------------------------
    |
    | Basic details about the company deploying this RKAP instance.
    |
    */
    'company_name'       => env('RKAP_COMPANY_NAME', 'PT Perusahaan'),
    'company_short_name' => env('RKAP_COMPANY_SHORT_NAME', 'Company'),
    'company_url'        => env('RKAP_COMPANY_URL', 'https://example.com'),
    'company_tagline'    => env('RKAP_COMPANY_TAGLINE', 'Sistem Rencana Kerja & Anggaran Perusahaan'),
    'app_short_title'    => env('RKAP_APP_SHORT_TITLE', 'RKAP'),
    'email_domain'       => env('RKAP_EMAIL_DOMAIN', 'example.com'),

    /*
    |--------------------------------------------------------------------------
    | Branding Assets (relative to public/)
    |--------------------------------------------------------------------------
    |
    | Asset paths for company logos, heroes, and default avatar.
    |
    */
    'logo_sidebar'    => env('RKAP_LOGO_SIDEBAR', 'assets/img/brand/logo_sidebar.png'),
    'logo_navbar'     => env('RKAP_LOGO_NAVBAR', 'assets/img/brand/logo_navbar.png'),
    'logo_login'      => env('RKAP_LOGO_LOGIN', 'assets/img/brand/logo_login.png'),
    'logo_login_hero' => env('RKAP_LOGO_LOGIN_HERO', 'assets/img/brand/login_hero.png'),
    'avatar_default'  => env('RKAP_AVATAR_DEFAULT', 'assets/img/brand/default_avatar.png'),

    /*
    |--------------------------------------------------------------------------
    | Theme Colors
    |--------------------------------------------------------------------------
    |
    | Primary brand colors injected dynamically into styles and UI components.
    |
    */
    'theme_primary' => env('RKAP_THEME_PRIMARY', '#960b10'),
    'theme_dark'    => env('RKAP_THEME_DARK', '#1a1f5e'),
    'theme_accent'  => env('RKAP_THEME_ACCENT', '#b91c1c'),

    /*
    |--------------------------------------------------------------------------
    | Role Configuration & Hierarchy
    |--------------------------------------------------------------------------
    |
    | Configurable role identifiers used by authorization checks in the app.
    | Can be adapted to match each company's internal naming conventions.
    |
    */
    'roles' => [
        'admin'             => env('RKAP_ROLE_ADMIN', 'admin'),
        'user'              => env('RKAP_ROLE_USER', 'user'),
        'kepala_biro'       => env('RKAP_ROLE_KEPALA_BIRO', 'kepala_biro'),
        'kepala_departemen' => env('RKAP_ROLE_KEPALA_DEPARTEMEN', 'kepala_departemen'),
        'direksi'           => env('RKAP_ROLE_DIREKSI', 'direksi'),
        'verifikator'       => env('RKAP_ROLE_VERIFIKATOR', 'verifikator'),
        'direktur_utama'    => env('RKAP_ROLE_DIREKTUR_UTAMA', 'direktur_utama'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Display Labels
    |--------------------------------------------------------------------------
    |
    | Human-readable titles displayed in approval workflows and UI badges.
    |
    */
    'role_labels' => [
        'admin'             => env('RKAP_LABEL_ADMIN', 'Administrator'),
        'user'              => env('RKAP_LABEL_USER', 'User / Staf'),
        'kepala_biro'       => env('RKAP_LABEL_KEPALA_BIRO', 'Kepala Biro / Section Head'),
        'kepala_departemen' => env('RKAP_LABEL_KEPALA_DEPARTEMEN', 'Kepala Departemen / Dept Head'),
        'direksi'           => env('RKAP_LABEL_DIREKSI', 'Direksi / Division Head'),
        'verifikator'       => env('RKAP_LABEL_VERIFIKATOR', 'Verifikator Anggaran'),
        'direktur_utama'    => env('RKAP_LABEL_DIREKTUR_UTAMA', 'Direktur Utama / President Director'),
        'direktur_keuangan' => env('RKAP_LABEL_DIREKTUR_KEUANGAN', 'Direktur Keuangan / CFO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Organizational Hierarchy Labels
    |--------------------------------------------------------------------------
    |
    | Labels for organizational tiers (Level 1: top, Level 2: middle, Level 3: unit)
    |
    */
    'org_labels' => [
        'level_1' => env('RKAP_ORG_LEVEL_1', 'Direktorat'),
        'level_2' => env('RKAP_ORG_LEVEL_2', 'Departemen'),
        'level_3' => env('RKAP_ORG_LEVEL_3', 'Biro'),
    ],

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
    | Admin Email & Password
    |--------------------------------------------------------------------------
    |
    | Default admin user seeded into the system.
    |
    */
    'admin_email'           => env('RKAP_ADMIN_EMAIL', 'admin@rkap.com'),
    'seed_default_password' => env('RKAP_SEED_PASSWORD', 'P@ssw0rd!'),

    /*
    |--------------------------------------------------------------------------
    | Sample Data Seeding
    |--------------------------------------------------------------------------
    |
    | Set to false for clean company installations. Set to true if you want to
    | seed demo/sample data (departments, sample COA mappings, test users).
    |
    */
    'seed_sample_data' => (bool) env('RKAP_SEED_SAMPLE_DATA', false),

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
