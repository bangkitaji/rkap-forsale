<!-- BEGIN: Vendor & Core Theme JS Bundle -->
@vite(['resources/js/app.js'])
<!-- END: Vendor & Core Theme JS Bundle -->

@yield('vendor-script')
<!-- END: Page Vendor JS-->

<!-- Pricing Modal JS-->
@stack('pricing-script')
<!-- END: Pricing Modal JS-->

<!-- BEGIN: Page JS-->
@yield('page-script')
<!-- END: Page JS-->
