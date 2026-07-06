@extends('layouts/blankLayout')

@section('title', '403 - Access Forbidden')

@section('page-style')
<!-- Page -->
@vite(['resources/assets/vendor/scss/pages/page-misc.scss'])
@endsection

@section('content')
<!-- Error 403 -->
<div class="container-xxl container-p-y">
    <div class="misc-wrapper">
        <h1 class="mb-2 mx-2 rkap-error-font">403</h1>
        <h4 class="mb-2 mx-2">Access Forbidden 🔒</h4>
        <p class="mb-6 mx-2">{{ $exception->getMessage() ?: 'You do not have permission to access this page.' }}</p>
        <a href="{{ url('/') }}" class="btn btn-primary">Back to home</a>
        <div class="mt-6">
            <img src="{{ asset('assets/img/illustrations/page-misc-error-light.png') }}" alt="page-misc-error" width="500" class="img-fluid" />
        </div>
    </div>
</div>
<!-- /Error 403 -->
@endsection