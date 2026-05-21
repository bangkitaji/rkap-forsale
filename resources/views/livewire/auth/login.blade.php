<div>
    @section('title', 'Login — RKAP')

    @section('page-style')
    <style>
        /* ─── Reset & Base ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        html, body {
            height: 100%;
            overflow: hidden;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #0f172a;
        }

        /* ─── Wrapper ─── */
        .login-wrapper {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ─── LEFT PANEL ─── */
        .login-hero {
            flex: 0 0 55%;
            position: relative;
            overflow: hidden;
            display: none;
        }

        @media (min-width: 900px) {
            .login-hero { display: block; }
        }

        .login-hero__image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
            display: block;
        }

        /* Dark overlay for text legibility */
        .login-hero__overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                135deg,
                rgba(10, 20, 60, 0.72) 0%,
                rgba(10, 20, 60, 0.28) 60%,
                rgba(180, 20, 30, 0.30) 100%
            );
        }

        /* Branding block bottom-left */
        .login-hero__brand {
            position: absolute;
            bottom: 48px;
            left: 48px;
            right: 48px;
            color: #fff;
        }

        .login-hero__logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .login-hero__logo img {
            height: 48px;
            filter: brightness(0) invert(1);
            object-fit: contain;
        }

        .login-hero__title {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            line-height: 1.15;
            color: #fff;
            margin-bottom: 8px;
        }

        .login-hero__title span {
            color: #ED1C24;
        }

        .login-hero__subtitle {
            font-size: 0.95rem;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.75);
            letter-spacing: 0.2px;
        }

        /* Floating badge top-left */
        .login-hero__badge {
            position: absolute;
            top: 40px;
            left: 48px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.20);
            border-radius: 100px;
            padding: 8px 20px;
            color: #fff;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        /* ─── RIGHT PANEL ─── */
        .login-form-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            padding: 40px 24px;
            position: relative;
            overflow-y: auto;
        }

        /* Subtle background pattern */
        .login-form-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 80% 20%, rgba(237, 28, 36, 0.06) 0%, transparent 50%),
                radial-gradient(circle at 20% 80%, rgba(26, 31, 94, 0.05) 0%, transparent 50%);
            pointer-events: none;
        }

        .login-form-box {
            position: relative;
            width: 100%;
            max-width: 420px;
            z-index: 1;
        }

        /* Brand at top of form */
        .form-brand {
            text-align: center;
            margin-bottom: 32px;
        }

        .form-brand__logo {
            height: 40px;
            object-fit: contain;
            margin-bottom: 12px;
        }

        .form-brand__name {
            font-size: 1.4rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .form-brand__name span {
            color: #ED1C24;
        }

        .form-brand__tagline {
            font-size: 0.8rem;
            color: #94a3b8;
            font-weight: 500;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }

        /* ─── Card ─── */
        .login-card {
            background: #fff;
            border-radius: 20px;
            padding: 40px;
            box-shadow:
                0 4px 6px -1px rgba(0, 0, 0, 0.05),
                0 20px 40px -10px rgba(0, 0, 0, 0.10);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .login-card__heading {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.3px;
            margin-bottom: 6px;
        }

        .login-card__sub {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0;
        }

        .login-divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e2e8f0, transparent);
            margin: 24px 0;
        }

        /* ─── Form Fields ─── */
        .form-group-rkap {
            margin-bottom: 20px;
        }

        .form-group-rkap label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            letter-spacing: 0.1px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.1rem;
            pointer-events: none;
            display: flex;
            align-items: center;
        }

        .input-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.1rem;
            cursor: pointer;
            background: none;
            border: none;
            padding: 4px;
            display: flex;
            align-items: center;
            transition: color 0.2s;
        }

        .input-toggle:hover { color: #475569; }

        .form-input-rkap {
            width: 100%;
            height: 48px;
            padding: 0 44px 0 44px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            font-size: 0.9rem;
            font-family: inherit;
            color: #0f172a;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-input-rkap:focus {
            border-color: #ED1C24;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(237, 28, 36, 0.12);
        }

        .form-input-rkap::placeholder { color: #cbd5e1; }
        .form-input-rkap.has-toggle { padding-right: 44px; }

        .form-input-rkap.is-error {
            border-color: #ED1C24;
            box-shadow: 0 0 0 3px rgba(237, 28, 36, 0.10);
        }

        /* Error text */
        .input-error {
            font-size: 0.78rem;
            color: #ED1C24;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Alert error */
        .login-alert {
            background: #fff1f1;
            border: 1px solid #ffc8c9;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #ED1C24;
            font-weight: 500;
        }

        /* Remember row */
        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .form-check-rkap {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.84rem;
            color: #64748b;
            cursor: pointer;
        }

        .form-check-rkap input[type="checkbox"] {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            accent-color: #ED1C24;
            cursor: pointer;
        }

        /* ─── Submit Button ─── */
        .btn-signin {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, #ED1C24 0%, #b91c1c 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 0.9375rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.25s ease;
            letter-spacing: 0.2px;
            box-shadow: 0 4px 14px 0 rgba(237, 28, 36, 0.35);
        }

        .btn-signin:hover {
            background: linear-gradient(135deg, #c7161c 0%, #991b1b 100%);
            box-shadow: 0 6px 20px 0 rgba(237, 28, 36, 0.45);
            transform: translateY(-1px);
        }

        .btn-signin:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px 0 rgba(237, 28, 36, 0.30);
        }

        .btn-signin:disabled {
            opacity: 0.75;
            cursor: not-allowed;
            transform: none;
        }

        .btn-signin svg {
            transition: transform 0.2s;
        }

        .btn-signin:hover:not(:disabled) svg {
            transform: translateX(3px);
        }

        /* Spinner */
        .btn-spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ─── Footer ─── */
        .login-footer {
            text-align: center;
            margin-top: 28px;
            font-size: 0.78rem;
            color: #94a3b8;
        }

        .login-footer strong {
            color: #64748b;
        }

        /* ─── Responsive ─── */
        @media (max-width: 480px) {
            .login-card { padding: 28px 20px; }
        }
    </style>
    @endsection

    <div class="login-wrapper">

        {{-- ═══ LEFT HERO PANEL — WHOOSH Train ═══ --}}
        <div class="login-hero">
            <img
                class="login-hero__image"
                src="{{ asset('assets/img/kcic/login_hero_train.png') }}"
                alt="WHOOSH High-Speed Train"
            />
            <div class="login-hero__overlay"></div>

            <div class="login-hero__badge">PT. Kereta Cepat Indonesia China</div>

            <div class="login-hero__brand">
                <div class="login-hero__logo">
                    <img src="{{ asset('assets/img/kcic/logo_whoosh.png') }}" alt="WHOOSH Logo" />
                </div>
                <h1 class="login-hero__title">
                    RKAP<span>.</span>
                </h1>
                <p class="login-hero__subtitle">Sistem Rencana Kerja &amp; Anggaran Perusahaan</p>
            </div>
        </div>

        {{-- ═══ RIGHT FORM PANEL ═══ --}}
        <div class="login-form-panel">
            <div class="login-form-box">

                {{-- Brand header --}}
                <div class="form-brand">
                    <img
                        class="form-brand__logo"
                        src="{{ asset('assets/img/kcic/logo_whoosh.png') }}"
                        alt="WHOOSH"
                    />
                    <div class="form-brand__name">
                        RKAP<span>.</span>
                    </div>
                    <div class="form-brand__tagline">Rencana Kerja &amp; Anggaran Perusahaan</div>
                </div>

                {{-- Card --}}
                <div class="login-card">
                    <h2 class="login-card__heading">Selamat datang! 👋</h2>
                    <p class="login-card__sub">Silakan masuk ke akun Anda untuk melanjutkan.</p>

                    <div class="login-divider"></div>

                    <form wire:submit.prevent="login" novalidate>

                        {{-- General error --}}
                        @if (session()->has('error'))
                            <div class="login-alert">
                                <i class="bx bx-error-circle" style="font-size:1.1rem;"></i>
                                {{ session('error') }}
                            </div>
                        @endif

                        {{-- Email --}}
                        <div class="form-group-rkap">
                            <label for="email">Alamat Email</label>
                            <div class="input-wrapper">
                                <span class="input-icon">
                                    <i class="bx bx-envelope"></i>
                                </span>
                                <input
                                    type="email"
                                    id="email"
                                    wire:model="email"
                                    class="form-input-rkap @error('email') is-error @enderror"
                                    placeholder="nama@kcic.co.id"
                                    autofocus
                                    autocomplete="email"
                                />
                            </div>
                            @error('email')
                                <div class="input-error">
                                    <i class="bx bx-error-circle"></i> {{ $message }}
                                </div>
                            @enderror
                        </div>

                        {{-- Password --}}
                        <div class="form-group-rkap">
                            <label for="password">Password</label>
                            <div class="input-wrapper">
                                <span class="input-icon">
                                    <i class="bx bx-lock-alt"></i>
                                </span>
                                <input
                                    type="password"
                                    id="password"
                                    wire:model="password"
                                    class="form-input-rkap has-toggle @error('password') is-error @enderror"
                                    placeholder="••••••••••••"
                                    autocomplete="current-password"
                                />
                                <button
                                    type="button"
                                    class="input-toggle"
                                    id="togglePassword"
                                    aria-label="Toggle password visibility"
                                >
                                    <i class="bx bx-hide" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                            @error('password')
                                <div class="input-error">
                                    <i class="bx bx-error-circle"></i> {{ $message }}
                                </div>
                            @enderror
                        </div>

                        {{-- Remember Me --}}
                        <div class="form-options">
                            <label class="form-check-rkap">
                                <input type="checkbox" wire:model="remember" id="remember-me" />
                                Ingat saya
                            </label>
                        </div>

                        {{-- Submit --}}
                        <button type="submit" class="btn-signin" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="login">
                                Masuk
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                    <polyline points="12 5 19 12 12 19"/>
                                </svg>
                            </span>
                            <span wire:loading wire:target="login">
                                <span class="btn-spinner"></span>
                                Memproses...
                            </span>
                        </button>

                    </form>
                </div>

                {{-- Footer --}}
                <div class="login-footer">
                    &copy; {{ date('Y') }} <strong>PT. Kereta Cepat Indonesia China</strong>. All rights reserved.
                </div>
            </div>
        </div>

    </div>

    <script>
        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function () {
            const input = document.getElementById('password');
            const icon  = document.getElementById('togglePasswordIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bx-hide', 'bx-show');
            } else {
                input.type = 'password';
                icon.classList.replace('bx-show', 'bx-hide');
            }
        });
    </script>
</div>
