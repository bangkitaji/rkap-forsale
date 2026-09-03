@php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
@endphp

<!--  Brand demo (display only for navbar-full and hide on below xl) -->
@if(isset($navbarFull))
<div class="navbar-brand app-brand demo d-none d-xl-flex py-0 me-4">
    <a href="{{url('/')}}" class="app-brand-link gap-2">
        <!-- <span class="app-brand-logo">
            <img src="{{ asset('assets/img/kcic/logo_kcic.png') }}" alt="KCIC Logo" class="img-fluid rkap-logo-navbar">
        </span> -->
        <img class="app-brand-logo" width="30%" src="{{ \App\Helpers\BrandHelper::logo('navbar') }}" alt="{{ \App\Helpers\BrandHelper::companyName() }}" />
        <span class="app-brand-text demo menu-text fw-bold text-heading">{{ \App\Helpers\BrandHelper::companyShortName() }}</span>
    </a>
</div>
@endif

<!-- ! Not required for layout-without-menu -->
@if(!isset($navbarHideToggle))
<div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 {{ isset($contentNavbar) ?' d-xl-none ' : '' }}">
    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
        <i class="icon-base bx bx-menu icon-md"></i>
    </a>
</div>
@endif

<div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
    <!-- Search -->
    <div class="navbar-nav align-items-center">
        <div class="nav-item d-flex align-items-center">
            <i class="icon-base bx bx-search icon-md"></i>
            <input type="text" class="form-control border-0 shadow-none ps-1 ps-sm-2" placeholder="Search..." aria-label="Search...">
        </div>
    </div>
    <!-- /Search -->
    <ul class="navbar-nav flex-row align-items-center ms-auto">
        <!-- Place this tag where you want the button to render. -->
        <!-- <li class="nav-item lh-1 me-4">
            <a class="github-button" href="{{config('variables.repository')}}" data-icon="octicon-star" data-size="large" data-show-count="true" aria-label="Star themeselection/sneat-html-laravel-admin-template-free on GitHub">Star</a>
        </li> -->

        <!-- Language Dropdown -->
        <li class="nav-item dropdown me-3">
            <a class="nav-link dropdown-toggle hide-arrow d-flex align-items-center" href="javascript:void(0);" data-bs-toggle="dropdown">
                @if(app()->getLocale() == 'en')
                    <span class="fs-4 me-1">🇬🇧</span><span class="d-none d-md-inline-block text-body">English</span>
                @else
                    <span class="fs-4 me-1">🇮🇩</span><span class="d-none d-md-inline-block text-body">Bahasa Indonesia</span>
                @endif
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <a class="dropdown-item d-flex align-items-center {{ app()->getLocale() == 'id' ? 'active' : '' }}" href="{{ route('lang.switch', 'id') }}">
                        <span class="fs-4 me-2">🇮🇩</span>
                        <span>Bahasa Indonesia</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center {{ app()->getLocale() == 'en' ? 'active' : '' }}" href="{{ route('lang.switch', 'en') }}">
                        <span class="fs-4 me-2">🇬🇧</span>
                        <span>English</span>
                    </a>
                </li>
            </ul>
        </li>
        <!--/ Language Dropdown -->

        <!-- User -->
        <li class="nav-item navbar-dropdown dropdown-user dropdown">
            <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                <div class="avatar avatar-online">
                    <img src="{{ \App\Helpers\BrandHelper::logo('avatar') }}" alt class="w-px-40 h-auto rounded-circle">
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="javascript:void(0);">
                        <div class="d-flex">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar avatar-online">
                                    <img src="{{ \App\Helpers\BrandHelper::logo('avatar') }}" alt class="w-px-40 h-auto rounded-circle">
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0">{{ Auth::user()->name ?? 'User' }}</h6>
                                <small class="text-muted">{{ Auth::user()?->roles->pluck('name')->implode(', ') ?? 'User' }}</small>
                            </div>
                        </div>
                    </a>
                </li>
                <li>
                    <div class="dropdown-divider my-1"></div>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('my-profile') }}">
                        <i class="icon-base bx bx-user icon-md me-3"></i><span>My Profile</span>
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('my-profile', ['tab' => 'security']) }}">
                        <i class="icon-base bx bx-lock-alt icon-md me-3"></i><span>Change Password</span>
                    </a>
                </li>
                <!-- <li>
                    <a class="dropdown-item" href="javascript:void(0);">
                        <span class="d-flex align-items-center align-middle">
                            <i class="flex-shrink-0 icon-base bx bx-credit-card icon-md me-3"></i><span class="flex-grow-1 align-middle">Billing Plan</span>
                            <span class="flex-shrink-0 badge rounded-pill bg-danger">4</span>
                        </span>
                    </a>
                </li> -->
                <li>
                    <div class="dropdown-divider my-1"></div>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="icon-base bx bx-power-off icon-md me-3"></i><span>Log Out</span>
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </li>
            </ul>
        </li>
        <!--/ User -->
    </ul>
</div>