<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold py-1 mb-1">{{ __('Pengaturan Brand & Logo') }}</h4>
            <p class="text-muted mb-0">{{ __('Kelola logo, identitas perusahaan, dan warna tema aplikasi. Dapat dikonfigurasi melalui UI ini atau file .env.') }}</p>
        </div>
        <div>
            <button wire:click="resetAllToEnv" wire:confirm="{{ __('Apakah Anda yakin ingin mengembalikan SEMUA logo dan warna ke konfigurasi default (.env)?') }}" class="btn btn-outline-danger">
                <i class="bx bx-reset me-1"></i> {{ __('Reset Semua ke .env') }}
            </button>
        </div>
    </div>

    @if (session()->has('success_details'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bx bx-check-circle me-1"></i> {{ session('success_details') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        {{-- ═══ 1. PENGATURAN LOGO APLIKASI ═══ --}}
        <div class="col-12 col-xl-7">
            <div class="card mb-4 shadow-sm">
                <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bx bx-image me-1 text-primary"></i> {{ __('Logo & Aset Visual') }}
                    </h5>
                    <span class="badge bg-label-info">{{ __('Format: PNG, SVG, JPG, WebP (Maks 2MB)') }}</span>
                </div>
                <div class="card-body pt-4">

                    {{-- ── 1A. Logo Sidebar ── --}}
                    <div class="p-3 mb-4 rounded border bg-light bg-opacity-25">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1 fw-bold">{{ __('Logo Sidebar Menu') }}</h6>
                                <small class="text-muted">{{ __('Tampil di pojok kiri atas menu navigasi samping (Rekomendasi: 180-220px x 40-60px, latar transparan).') }}</small>
                            </div>
                            @if($isCustomSidebar)
                                <span class="badge bg-success">{{ __('Kustom (UI)') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('Default (.env)') }}</span>
                            @endif
                        </div>

                        <div class="row align-items-center mt-3">
                            <div class="col-auto">
                                <div class="border rounded p-2 bg-white d-flex align-items-center justify-content-center" style="width: 140px; height: 70px;">
                                    <img src="{{ $activeSidebar }}" alt="Sidebar Logo" style="max-width: 120px; max-height: 50px; object-fit: contain;">
                                </div>
                            </div>
                            <div class="col">
                                <form wire:submit.prevent="uploadLogo('sidebar')" class="d-flex gap-2 align-items-center">
                                    <input type="file" wire:model="fileSidebar" class="form-control form-control-sm" accept="image/*">
                                    <button type="submit" class="btn btn-sm btn-primary text-nowrap" wire:loading.attr="disabled" wire:target="fileSidebar, uploadLogo">
                                        <i class="bx bx-upload me-1"></i> {{ __('Upload') }}
                                    </button>
                                    @if($isCustomSidebar)
                                        <button type="button" wire:click="resetLogo('sidebar')" class="btn btn-sm btn-outline-secondary" title="{{ __('Kembalikan ke .env') }}">
                                            <i class="bx bx-undo"></i>
                                        </button>
                                    @endif
                                </form>
                                @error('fileSidebar') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                                @if (session()->has('success_sidebar'))
                                    <small class="text-success d-block mt-1"><i class="bx bx-check"></i> {{ session('success_sidebar') }}</small>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ── 1B. Logo Navbar ── --}}
                    <div class="p-3 mb-4 rounded border bg-light bg-opacity-25">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1 fw-bold">{{ __('Logo Navbar Atas') }}</h6>
                                <small class="text-muted">{{ __('Tampil pada bar navigasi atas di samping judul (Rekomendasi: 120-160px x 35-45px).') }}</small>
                            </div>
                            @if($isCustomNavbar)
                                <span class="badge bg-success">{{ __('Kustom (UI)') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('Default (.env)') }}</span>
                            @endif
                        </div>

                        <div class="row align-items-center mt-3">
                            <div class="col-auto">
                                <div class="border rounded p-2 bg-white d-flex align-items-center justify-content-center" style="width: 140px; height: 70px;">
                                    <img src="{{ $activeNavbar }}" alt="Navbar Logo" style="max-width: 120px; max-height: 50px; object-fit: contain;">
                                </div>
                            </div>
                            <div class="col">
                                <form wire:submit.prevent="uploadLogo('navbar')" class="d-flex gap-2 align-items-center">
                                    <input type="file" wire:model="fileNavbar" class="form-control form-control-sm" accept="image/*">
                                    <button type="submit" class="btn btn-sm btn-primary text-nowrap" wire:loading.attr="disabled" wire:target="fileNavbar, uploadLogo">
                                        <i class="bx bx-upload me-1"></i> {{ __('Upload') }}
                                    </button>
                                    @if($isCustomNavbar)
                                        <button type="button" wire:click="resetLogo('navbar')" class="btn btn-sm btn-outline-secondary" title="{{ __('Kembalikan ke .env') }}">
                                            <i class="bx bx-undo"></i>
                                        </button>
                                    @endif
                                </form>
                                @error('fileNavbar') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                                @if (session()->has('success_navbar'))
                                    <small class="text-success d-block mt-1"><i class="bx bx-check"></i> {{ session('success_navbar') }}</small>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ── 1C. Logo Formulir Login ── --}}
                    <div class="p-3 mb-4 rounded border bg-light bg-opacity-25">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1 fw-bold">{{ __('Logo Formulir Login') }}</h6>
                                <small class="text-muted">{{ __('Tampil di bagian atas kartu form login (Rekomendasi: 180-240px x 50-80px).') }}</small>
                            </div>
                            @if($isCustomLogin)
                                <span class="badge bg-success">{{ __('Kustom (UI)') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('Default (.env)') }}</span>
                            @endif
                        </div>

                        <div class="row align-items-center mt-3">
                            <div class="col-auto">
                                <div class="border rounded p-2 bg-white d-flex align-items-center justify-content-center" style="width: 140px; height: 70px;">
                                    <img src="{{ $activeLogin }}" alt="Login Logo" style="max-width: 120px; max-height: 50px; object-fit: contain;">
                                </div>
                            </div>
                            <div class="col">
                                <form wire:submit.prevent="uploadLogo('login')" class="d-flex gap-2 align-items-center">
                                    <input type="file" wire:model="fileLogin" class="form-control form-control-sm" accept="image/*">
                                    <button type="submit" class="btn btn-sm btn-primary text-nowrap" wire:loading.attr="disabled" wire:target="fileLogin, uploadLogo">
                                        <i class="bx bx-upload me-1"></i> {{ __('Upload') }}
                                    </button>
                                    @if($isCustomLogin)
                                        <button type="button" wire:click="resetLogo('login')" class="btn btn-sm btn-outline-secondary" title="{{ __('Kembalikan ke .env') }}">
                                            <i class="bx bx-undo"></i>
                                        </button>
                                    @endif
                                </form>
                                @error('fileLogin') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                                @if (session()->has('success_login'))
                                    <small class="text-success d-block mt-1"><i class="bx bx-check"></i> {{ session('success_login') }}</small>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ── 1D. Gambar Hero Login (Panel Kiri) ── --}}
                    <div class="p-3 mb-4 rounded border bg-light bg-opacity-25">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1 fw-bold">{{ __('Gambar Latar Hero Login') }}</h6>
                                <small class="text-muted">{{ __('Foto pemandangan gedung/operasional di panel samping kiri login (Rekomendasi: 1200x800px).') }}</small>
                            </div>
                            @if($isCustomHero)
                                <span class="badge bg-success">{{ __('Kustom (UI)') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('Default (.env)') }}</span>
                            @endif
                        </div>

                        <div class="row align-items-center mt-3">
                            <div class="col-auto">
                                <div class="border rounded overflow-hidden" style="width: 140px; height: 70px;">
                                    <img src="{{ $activeLoginHero }}" alt="Login Hero" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            </div>
                            <div class="col">
                                <form wire:submit.prevent="uploadLogo('login_hero')" class="d-flex gap-2 align-items-center">
                                    <input type="file" wire:model="fileLoginHero" class="form-control form-control-sm" accept="image/*">
                                    <button type="submit" class="btn btn-sm btn-primary text-nowrap" wire:loading.attr="disabled" wire:target="fileLoginHero, uploadLogo">
                                        <i class="bx bx-upload me-1"></i> {{ __('Upload') }}
                                    </button>
                                    @if($isCustomHero)
                                        <button type="button" wire:click="resetLogo('login_hero')" class="btn btn-sm btn-outline-secondary" title="{{ __('Kembalikan ke .env') }}">
                                            <i class="bx bx-undo"></i>
                                        </button>
                                    @endif
                                </form>
                                @error('fileLoginHero') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                                @if (session()->has('success_login_hero'))
                                    <small class="text-success d-block mt-1"><i class="bx bx-check"></i> {{ session('success_login_hero') }}</small>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ── 1E. Avatar Default Pengguna ── --}}
                    <div class="p-3 rounded border bg-light bg-opacity-25">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1 fw-bold">{{ __('Avatar Profil Standar') }}</h6>
                                <small class="text-muted">{{ __('Foto profil default untuk akun pengguna (Rasio 1:1, misal 128x128px).') }}</small>
                            </div>
                            @if($isCustomAvatar)
                                <span class="badge bg-success">{{ __('Kustom (UI)') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('Default (.env)') }}</span>
                            @endif
                        </div>

                        <div class="row align-items-center mt-3">
                            <div class="col-auto">
                                <div class="border rounded-circle overflow-hidden d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <img src="{{ $activeAvatar }}" alt="Default Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                            </div>
                            <div class="col">
                                <form wire:submit.prevent="uploadLogo('avatar')" class="d-flex gap-2 align-items-center">
                                    <input type="file" wire:model="fileAvatar" class="form-control form-control-sm" accept="image/*">
                                    <button type="submit" class="btn btn-sm btn-primary text-nowrap" wire:loading.attr="disabled" wire:target="fileAvatar, uploadLogo">
                                        <i class="bx bx-upload me-1"></i> {{ __('Upload') }}
                                    </button>
                                    @if($isCustomAvatar)
                                        <button type="button" wire:click="resetLogo('avatar')" class="btn btn-sm btn-outline-secondary" title="{{ __('Kembalikan ke .env') }}">
                                            <i class="bx bx-undo"></i>
                                        </button>
                                    @endif
                                </form>
                                @error('fileAvatar') <small class="text-danger d-block mt-1">{{ $message }}</small> @enderror
                                @if (session()->has('success_avatar'))
                                    <small class="text-success d-block mt-1"><i class="bx bx-check"></i> {{ session('success_avatar') }}</small>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- ═══ 2. INFORMASI PERUSAHAAN & WARNA TEMA ═══ --}}
        <div class="col-12 col-xl-5">
            <div class="card mb-4 shadow-sm">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0">
                        <i class="bx bx-building me-1 text-primary"></i> {{ __('Identitas & Warna Brand') }}
                    </h5>
                </div>
                <div class="card-body pt-4">
                    <form wire:submit.prevent="saveDetails">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Nama Perusahaan') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('companyName') is-invalid @enderror" wire:model="companyName" placeholder="PT Maju Makmur">
                            @error('companyName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Nama Singkatan / Akronim') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('companyShortName') is-invalid @enderror" wire:model="companyShortName" placeholder="MMS">
                            @error('companyShortName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('Tagline Aplikasi') }}</label>
                            <input type="text" class="form-control @error('companyTagline') is-invalid @enderror" wire:model="companyTagline" placeholder="Sistem Rencana Kerja & Anggaran Perusahaan">
                            @error('companyTagline') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <hr class="my-4">

                        <h6 class="fw-bold mb-3"><i class="bx bx-palette me-1"></i> {{ __('Tema Warna Aplikasi') }}</h6>

                        <div class="mb-3">
                            <label class="form-label fw-semibold d-flex justify-content-between">
                                <span>{{ __('Warna Primer Brand') }}</span>
                                <span class="badge" style="background-color: {{ $themePrimary }}; color: #fff;">{{ $themePrimary }}</span>
                            </label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" wire:model.live="themePrimary" style="max-width: 60px;">
                                <input type="text" class="form-control @error('themePrimary') is-invalid @enderror" wire:model.live="themePrimary" placeholder="#1e40af">
                            </div>
                            <small class="text-muted">{{ __('Warna utama untuk tombol, tab aktif, dan aksen visual.') }}</small>
                            @error('themePrimary') <div class="text-danger mt-1 small">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold d-flex justify-content-between">
                                <span>{{ __('Warna Gelap / Sidebar') }}</span>
                                <span class="badge" style="background-color: {{ $themeDark }}; color: #fff;">{{ $themeDark }}</span>
                            </label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" wire:model.live="themeDark" style="max-width: 60px;">
                                <input type="text" class="form-control @error('themeDark') is-invalid @enderror" wire:model.live="themeDark" placeholder="#0f172a">
                            </div>
                            <small class="text-muted">{{ __('Warna dasar untuk latar elemen gelap atau sidebar.') }}</small>
                            @error('themeDark') <div class="text-danger mt-1 small">{{ $message }}</div> @enderror
                        </div>

                        {{-- Preview Box --}}
                        <div class="p-3 mb-4 rounded border text-center" style="background-color: rgba(0,0,0,0.02);">
                            <small class="text-muted d-block mb-2">{{ __('Pratinjau Tombol:') }}</small>
                            <div class="d-flex justify-content-center gap-2">
                                <button type="button" class="btn btn-sm text-white" style="background-color: {{ $themePrimary }}; border-color: {{ $themePrimary }};">
                                    {{ __('Tombol Primer') }}
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" style="border-color: {{ $themePrimary }}; color: {{ $themePrimary }};">
                                    {{ __('Outline') }}
                                </button>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <i class="bx bx-save me-1"></i> {{ __('Simpan Perubahan Identitas & Warna') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Info Card --}}
            <div class="card bg-lighter border shadow-none">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="bx bx-info-circle me-1 text-info"></i> {{ __('Konfigurasi via .env') }}</h6>
                    <p class="small text-muted mb-2">
                        {{ __('Anda juga dapat mengonfigurasi variabel-variabel ini secara permanen melalui file ') }}<code>.env</code>:
                    </p>
                    <pre class="bg-dark text-white p-2 rounded small mb-0"><code>RKAP_COMPANY_NAME="{{ $companyName }}"
RKAP_THEME_PRIMARY="{{ $themePrimary }}"
RKAP_LOGO_SIDEBAR="assets/img/brand/logo_sidebar.png"</code></pre>
                </div>
            </div>
        </div>
    </div>
</div>
