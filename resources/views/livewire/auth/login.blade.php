<div>
    @section('title', 'Login — RKAP')

    <div class="login-wrapper">

        {{-- ═══ LEFT HERO PANEL — WHOOSH Train ═══ --}}
        <div class="login-hero">
            <img
                class="login-hero__image"
                src="{{ asset('assets/img/kcic/login_hero_train.png') }}"
                alt="WHOOSH High-Speed Train" />
            <div class="login-hero__overlay"></div>

            <div class="login-hero__badge">PT. Kereta Cepat Indonesia China</div>

            <div class="login-hero__brand">
                <div class="d-flex align-items-center gap-12px">
                    <!-- <div class="login-hero__logo">
                        <img src="{{ asset('assets/img/kcic/logo_whoosh.png') }}" alt="WHOOSH Logo" />
                    </div> -->
                    <!-- <h1 class="login-hero__title">
                        RKAP<span>.</span>
                    </h1> -->
                </div>
                <p class="login-hero__subtitle">Sistem Rencana Kerja &amp; Anggaran Perusahaan</p>
            </div>
        </div>

        {{-- ═══ RIGHT FORM PANEL ═══ --}}
        <div class="login-form-panel">
            <div class="login-form-box">

                {{-- Brand header --}}
                <div class="form-brand">
                    <div class="form-brand__header">
                        <!-- <img
                            class="form-brand__logo"
                            src="{{ asset('assets/img/kcic/logo_kbudgeting.png') }}"
                            alt="WHOOSH" /> -->
                        <!-- <div class="form-brand__name">
                            Budgeting
                        </div> -->
                    </div>
                    <!-- <div class="form-brand__tagline">Rencana Kerja &amp; Anggaran Perusahaan</div> -->
                </div>

                {{-- Card --}}
                <div class="login-card">
                    <div class="form-brand__header">
                        <img
                            class="form-brand__logo"
                            src="{{ asset('assets/img/kcic/logo_kbudgeting.png') }}"
                            alt="WHOOSH" />
                        <!-- <div class="form-brand__name">
                            Budgeting
                        </div> -->
                    </div>
                    <h2 class="login-card__heading text-center">Selamat datang!</h2>
                    <p class="login-card__sub text-center">Silakan masuk ke akun Anda untuk melanjutkan.</p>

                    <div class="login-divider"></div>

                    <form wire:submit.prevent="login" novalidate>

                        {{-- General error --}}
                        @if (session()->has('error'))
                        <div class="login-alert">
                            <i class="bx bx-error-circle fs-1-1rem"></i>
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
                                    autocomplete="email" />
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
                                    autocomplete="current-password" />
                                <button
                                    type="button"
                                    class="input-toggle"
                                    id="togglePassword"
                                    aria-label="Toggle password visibility">
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
                                    <line x1="5" y1="12" x2="19" y2="12" />
                                    <polyline points="12 5 19 12 12 19" />
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
        document.getElementById('togglePassword').addEventListener('click', function() {
            const input = document.getElementById('password');
            const icon = document.getElementById('togglePasswordIcon');
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