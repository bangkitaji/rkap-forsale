// 1. Helpers
import { Helpers } from '../assets/vendor/js/helpers.js';
window.Helpers = Helpers;

// 2. Config
import '../assets/js/config.js';

// 3. jQuery
import jQuery from 'jquery';
window.$ = window.jQuery = jQuery;

// 4. Popper
import * as Popper from '@popperjs/core';
window.Popper = Popper;

// 5. Bootstrap
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// 6. Perfect Scrollbar
import PerfectScrollbar from 'perfect-scrollbar';
window.PerfectScrollbar = PerfectScrollbar;

// 7. Menu
import '../assets/vendor/js/menu.js';

// 8. Main init
import '../assets/js/main.js';

// 9. Axios (Laravel bootstrap config)
import './bootstrap';

// Assets Glob import for Vite asset handling
import.meta.glob([
  '../assets/img/**',
  '../assets/vendor/fonts/**'
]);
