#!/usr/bin/env bash
# ================================================================
# RKAP Multi-Company Quick Setup Script (Linux / macOS)
# ================================================================

set -e

echo "================================================================"
echo "       RKAP - QUICK INSTALLATION & SETUP (LINUX/MACOS)"
echo "================================================================"
echo ""

# Check PHP
if ! command -v php &> /dev/null; then
    echo "[ERROR] PHP tidak terdeteksi. Silakan install PHP 8.3+ terlebih dahulu."
    exit 1
fi

# Check Composer
if ! command -v composer &> /dev/null; then
    echo "[ERROR] Composer tidak terdeteksi. Silakan install Composer terlebih dahulu."
    exit 1
fi

echo "[1/4] Memeriksa dependensi Composer..."
if [ ! -d "vendor" ]; then
    echo "Mengunduh dependensi PHP via Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
else
    echo "Dependensi Composer sudah terinstall."
fi
echo ""

echo "[2/4] Memeriksa file lingkungan (.env)..."
if [ ! -f ".env" ]; then
    cp .env.example .env
    php artisan key:generate
    echo "File .env berhasil dibuat dan APP_KEY digenerate."
fi
echo ""

echo "[3/4] Menjalankan Wizard Konfigurasi Perusahaan..."
php artisan rkap:setup
echo ""

echo "[4/4] Selesai!"
echo "Untuk menjalankan server lokal, gunakan:"
echo "  php artisan serve"
echo ""
