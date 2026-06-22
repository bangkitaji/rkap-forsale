import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import html from '@rollup/plugin-html';
import { glob } from 'glob';
import path from 'path';
import iconsPlugin from './vite.icons.plugin.js';

/**
 * Get Files from a directory
 * @param {string} query
 * @returns array
 */
function GetFilesArray(query) {
  return glob.sync(query);
}

// Page JS Files
const pageJsFiles = GetFilesArray('resources/assets/js/*.js');

// Processing Vendor JS Files
const vendorJsFiles = GetFilesArray('resources/assets/vendor/js/*.js');

// Processing Libs JS Files
const LibsJsFiles = GetFilesArray('resources/assets/vendor/libs/**/*.js');

// Processing Libs Scss & Css Files
const LibsScssFiles = GetFilesArray('resources/assets/vendor/libs/**/!(_)*.scss');
const LibsCssFiles = GetFilesArray('resources/assets/vendor/libs/**/*.css');

// Processing Core, Themes & Pages Scss Files
const CoreScssFiles = GetFilesArray('resources/assets/vendor/scss/**/!(_)*.scss');

// Processing Fonts Scss & JS Files
const FontsScssFiles = GetFilesArray('resources/assets/vendor/fonts/!(_)*.scss');
const FontsJsFiles = GetFilesArray('resources/assets/vendor/fonts/**/!(_)*.js');
const FontsCssFiles = GetFilesArray('resources/assets/vendor/fonts/**/!(_)*.css');

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/css/app.css',
        'resources/assets/css/demo.css',
        'resources/js/app.js',
        'resources/assets/vendor/fonts/iconify/iconify.css',
        'resources/assets/vendor/scss/core.scss',
        'resources/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.scss',
        'resources/assets/vendor/scss/pages/page-misc.scss',
        'resources/assets/vendor/scss/pages/page-auth.scss',
        'resources/assets/vendor/libs/apex-charts/apex-charts.scss',
        'resources/assets/vendor/libs/apex-charts/apexcharts.js'
      ],
      refresh: true
    }),
    html(),
    iconsPlugin()
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources')
    }
  },
  json: {
    stringify: true // Helps with JSON import compatibility
  },
  build: {
    commonjsOptions: {
      include: [/node_modules/] // Helps with importing CommonJS modules
    }
  }
});
