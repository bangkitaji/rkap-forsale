@echo off
REM ================================================================
REM RKAP Multi-Company Quick Setup Script (Windows)
REM ================================================================

echo ================================================================
echo       RKAP - QUICK INSTALLATION ^& SETUP (WINDOWS)
echo ================================================================
echo.

REM Check if PHP is installed
where php >nul 2>nul
if %ERRORLEVEL% neq 0 (
    echo [ERROR] PHP tidak terdeteksi di sistem. Silakan install PHP 8.3+ terlebih dahulu.
    pause
    exit /b 1
)

REM Check if Composer is installed
where composer >nul 2>nul
if %ERRORLEVEL% neq 0 (
    echo [ERROR] Composer tidak terdeteksi. Silakan install Composer terlebih dahulu.
    pause
    exit /b 1
)

echo [1/4] Memeriksa dependensi Composer...
if not exist vendor (
    echo Mengunduh dependensi PHP via Composer...
    call composer install --no-interaction --prefer-dist --optimize-autoloader
) else (
    echo Dependensi Composer sudah terinstall.
)
echo.

echo [2/4] Memeriksa file lingkungan (.env)...
if not exist .env (
    copy .env.example .env
    call php artisan key:generate
    echo File .env berhasil dibuat dan APP_KEY digenerate.
)
echo.

echo [3/4] Menjalankan Wizard Konfigurasi Perusahaan...
call php artisan rkap:setup
echo.

echo [4/4] Selesai!
echo Untuk menjalankan server lokal, jalankan perintah:
echo   php artisan serve
echo.
pause
