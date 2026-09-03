@php
    $primaryColor = \App\Helpers\BrandHelper::themePrimary();
    $darkColor    = \App\Helpers\BrandHelper::themeDark();
    $accentColor  = config('rkap.theme_accent', '#b91c1c');

    $hexToRgb = function($hex) {
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
            return '150, 11, 16';
        }
        return "{$r}, {$g}, {$b}";
    };

    $primaryRgb = $hexToRgb($primaryColor);
@endphp
<style>
    :root {
        --rkap-primary: {{ $primaryColor }};
        --rkap-dark: {{ $darkColor }};
        --rkap-accent: {{ $accentColor }};
        --bs-primary: {{ $primaryColor }};
        --bs-primary-rgb: {{ $primaryRgb }};
    }

    /* Dynamic Brand Styling Overrides */
    .btn-primary, .btn-signin {
        background-color: var(--rkap-primary) !important;
        border-color: var(--rkap-primary) !important;
    }
    .btn-primary:hover, .btn-signin:hover {
        filter: brightness(0.9);
    }
    .btn-outline-primary {
        color: var(--rkap-primary) !important;
        border-color: var(--rkap-primary) !important;
    }
    .btn-outline-primary:hover {
        background-color: var(--rkap-primary) !important;
        color: #ffffff !important;
    }
    .text-primary {
        color: var(--rkap-primary) !important;
    }
    .bg-primary {
        background-color: var(--rkap-primary) !important;
    }
    .rkap-bg-kcic-red, .rkap-bg-primary {
        background-color: var(--rkap-primary) !important;
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
</style>
