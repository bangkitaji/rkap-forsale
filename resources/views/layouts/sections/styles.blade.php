<!-- BEGIN: Theme CSS-->
<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

<!-- Fonts Icons -->
@vite(['resources/assets/vendor/fonts/iconify/iconify.css'])

<!-- Core CSS -->
@vite(['resources/assets/vendor/scss/core.scss', 'resources/assets/css/demo.css'])

<!-- Vendor Styles -->
@vite('resources/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.scss')
@yield('vendor-style')

<!-- Page Styles -->
@yield('page-style')

<!-- app CSS -->
@vite(['resources/css/app.css'])
<!-- END: app CSS-->

<!-- ═══ KCIC / WHOOSH Brand Theme Override ═══ -->
<!-- Primary: #ED1C24 (KCIC Red) | Sidebar: #1a1f5e (KCIC Navy) -->
<style>
  :root {
    --bs-primary: #ED1C24;
    --bs-primary-rgb: 237, 28, 36;
    --bs-primary-bg-subtle: #fde8e9;
    --bs-primary-border-subtle: #f9b4b7;
    --bs-dark: #1a1f5e;
    --bs-dark-rgb: 26, 31, 94;
    --bs-custom-link-color: #ED1C24;
  }

  /* ── Sidebar / Vertical Menu overrides removed for white theme ── */

  /* ── Primary buttons ── */
  .btn-primary {
    background-color: #ED1C24 !important;
    border-color: #ED1C24 !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(237, 28, 36, .35) !important;
  }

  .btn-primary:hover,
  .btn-primary:focus,
  .btn-primary:active {
    background-color: #c7161c !important;
    border-color: #c7161c !important;
  }

  .btn-outline-primary {
    color: #ED1C24 !important;
    border-color: #ED1C24 !important;
  }

  .btn-outline-primary:hover {
    background-color: #ED1C24 !important;
    color: #fff !important;
  }

  .btn-label-primary {
    color: #ED1C24 !important;
    border-color: rgba(237, 28, 36, .2) !important;
    background-color: rgba(237, 28, 36, .12) !important;
  }

  /* ── Badges / labels ── */
  .bg-primary {
    background-color: #ED1C24 !important;
  }

  .bg-label-primary {
    background-color: rgba(237, 28, 36, 0.16) !important;
    color: #ED1C24 !important;
  }

  .text-primary {
    color: #ED1C24 !important;
  }

  .border-primary {
    border-color: #ED1C24 !important;
  }

  /* ── Nav tabs active ── */
  .nav-tabs .nav-link.active {
    border-bottom-color: #ED1C24 !important;
    color: #ED1C24 !important;
  }

  .nav-link:hover {
    color: #ED1C24 !important;
  }

  /* ── Form controls focus ── */
  .form-control:focus,
  .form-select:focus {
    border-color: #ED1C24 !important;
    box-shadow: 0 0.125rem 0.25rem 0 rgba(237, 28, 36, .4) !important;
  }

  .form-check-input:checked {
    background-color: #ED1C24 !important;
    border-color: #ED1C24 !important;
  }

  /* ── Pagination active ── */
  .page-item.active .page-link {
    background-color: #ED1C24 !important;
    border-color: #ED1C24 !important;
  }

  /* ── Links ── */
  a {
    color: #ED1C24;
  }

  a:hover {
    color: #c7161c;
  }

  /* ── Progress bar ── */
  .progress-bar {
    background-color: #ED1C24 !important;
  }

  /* ── Spinner / loader ── */
  .spinner-border.text-primary,
  .spinner-grow.text-primary {
    color: #ED1C24 !important;
  }
</style>