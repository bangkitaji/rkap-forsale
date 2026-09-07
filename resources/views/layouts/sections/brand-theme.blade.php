@php
    $primaryColor = \App\Helpers\BrandHelper::themePrimary();
    $darkColor    = \App\Helpers\BrandHelper::themeDark();
    $accentColor  = \App\Helpers\BrandHelper::themeAccent();

    $hexToRgb = function($hex, $fallback = '150, 11, 16') {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } elseif (strlen($hex) >= 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        } else {
            return $fallback;
        }
        return "{$r}, {$g}, {$b}";
    };

    $primaryRgb = $hexToRgb($primaryColor, '150, 11, 16');
    $darkRgb    = $hexToRgb($darkColor, '26, 31, 94');
    $accentRgb  = $hexToRgb($accentColor, '185, 28, 28');
@endphp
<style>
    :root {
        --rkap-primary: {{ $primaryColor }};
        --rkap-primary-rgb: {{ $primaryRgb }};
        --rkap-dark: {{ $darkColor }};
        --rkap-dark-rgb: {{ $darkRgb }};
        --rkap-accent: {{ $accentColor }};
        --rkap-accent-rgb: {{ $accentRgb }};
        --bs-primary: {{ $primaryColor }};
        --bs-primary-rgb: {{ $primaryRgb }};
        --bs-dark: {{ $darkColor }};
        --bs-dark-rgb: {{ $darkRgb }};
        --bs-custom-link-color: {{ $primaryColor }};
    }

    /* Dynamic Brand Styling Overrides */
    .btn-primary {
        background-color: var(--rkap-primary) !important;
        border-color: var(--rkap-primary) !important;
        box-shadow: 0 4px 12px rgba({{ $primaryRgb }}, 0.35) !important;
    }
    .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
        background-color: var(--rkap-primary) !important;
        border-color: var(--rkap-primary) !important;
        filter: brightness(0.9);
    }
    .btn-signin {
        background: linear-gradient(135deg, var(--rkap-primary) 0%, var(--rkap-accent) 100%) !important;
        box-shadow: 0 4px 14px 0 rgba({{ $primaryRgb }}, 0.35) !important;
    }
    .btn-signin:hover {
        filter: brightness(0.92);
        box-shadow: 0 6px 20px 0 rgba({{ $primaryRgb }}, 0.45) !important;
    }
    .btn-outline-primary {
        color: var(--rkap-primary) !important;
        border-color: var(--rkap-primary) !important;
    }
    .btn-outline-primary:hover, .btn-outline-primary:focus, .btn-outline-primary:active, .btn-outline-primary.active {
        background-color: var(--rkap-primary) !important;
        border-color: var(--rkap-primary) !important;
        color: #ffffff !important;
    }
    .btn-check:checked + .btn-outline-primary {
        background-color: var(--rkap-primary) !important;
        border-color: var(--rkap-primary) !important;
        color: #ffffff !important;
    }
    .btn-label-primary {
        color: var(--rkap-primary) !important;
        background-color: rgba({{ $primaryRgb }}, 0.12) !important;
        border-color: rgba({{ $primaryRgb }}, 0.2) !important;
    }
    .text-primary {
        color: var(--rkap-primary) !important;
    }
    .bg-primary {
        background-color: var(--rkap-primary) !important;
    }
    .bg-label-primary {
        background-color: rgba({{ $primaryRgb }}, 0.16) !important;
        color: var(--rkap-primary) !important;
    }
    .border-primary {
        border-color: var(--rkap-primary) !important;
    }
    .rkap-bg-kcic-red, .rkap-bg-primary {
        background-color: var(--rkap-primary) !important;
        color: #ffffff !important;
    }
    .rkap-border-l-4-primary {
        border-left: 4px solid var(--rkap-primary) !important;
    }
    .rkap-card-border-shadow-primary {
        border-bottom: 3px solid var(--rkap-primary) !important;
    }
    .rkap-activity-section {
        border-left: 3px solid var(--rkap-primary) !important;
    }
    .rkap-bg-primary-solid {
        background-color: var(--rkap-primary) !important;
        color: #ffffff !important;
    }
    .rkap-text-primary-solid {
        color: var(--rkap-primary) !important;
    }
    .rkap-bg-primary-soft {
        background-color: rgba({{ $primaryRgb }}, 0.08) !important;
    }
    .rkap-bg-primary-lighter {
        background-color: rgba({{ $primaryRgb }}, 0.06) !important;
    }
    .rkap-border-dashed-primary {
        border-top: 2px dashed rgba({{ $primaryRgb }}, 0.3) !important;
    }
    .rkap-bg-gradient-primary {
        background: linear-gradient(135deg, var(--rkap-primary), var(--rkap-dark)) !important;
        color: #ffffff !important;
    }
    .menu-vertical .menu-item.active:not(.open) > .menu-link {
        background-color: rgba({{ $primaryRgb }}, 0.1) !important;
        color: var(--rkap-primary) !important;
    }
    .menu-vertical .menu-item.active > .menu-link:before {
        background-color: var(--rkap-primary) !important;
    }
    .pagination .page-item.active .page-link {
        background-color: var(--rkap-primary) !important;
        border-color: var(--rkap-primary) !important;
    }
    .nav-tabs .nav-link.active, .nav-tabs .nav-link:hover {
        border-bottom-color: var(--rkap-primary) !important;
        color: var(--rkap-primary) !important;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--rkap-primary) !important;
        box-shadow: 0 0.125rem 0.25rem 0 rgba({{ $primaryRgb }}, 0.4) !important;
    }
    .form-check-input:checked {
        background-color: var(--rkap-primary) !important;
        border-color: var(--rkap-primary) !important;
    }
    .progress-bar {
        background-color: var(--rkap-primary) !important;
    }
    a {
        color: var(--rkap-primary);
    }
    a:hover {
        filter: brightness(0.9);
    }

    /* Auth & Login Pages */
    .login-hero__title span, .form-brand__name span {
        color: var(--rkap-primary) !important;
    }
    .login-input:focus {
        border-color: var(--rkap-primary) !important;
        box-shadow: 0 0 0 3px rgba({{ $primaryRgb }}, 0.12) !important;
    }
    .login-input.is-error {
        border-color: var(--rkap-primary) !important;
        box-shadow: 0 0 0 3px rgba({{ $primaryRgb }}, 0.1) !important;
    }
    .input-error, .login-alert {
        color: var(--rkap-primary) !important;
    }
    .form-check-rkap input[type='checkbox'] {
        accent-color: var(--rkap-primary) !important;
    }

    /* Timeline Primary */
    .timeline-card-primary {
        --theme-color: var(--rkap-primary) !important;
        border-color: var(--rkap-primary) !important;
    }
    .timeline-card-primary .timeline-card-header {
        background-color: var(--rkap-primary) !important;
    }
    .timeline-node-dot.bg-primary {
        background-color: var(--rkap-primary) !important;
    }
    .timeline-step-column .border-primary {
        border-color: var(--rkap-primary) !important;
        color: var(--rkap-primary) !important;
    }
</style>
