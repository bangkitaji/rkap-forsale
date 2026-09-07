# Panduan Deployment RKAP Multi-Perusahaan (White-Label)

Dokumen ini adalah panduan lengkap untuk melakukan instalasi dan implementasi aplikasi **RKAP (Rencana Kerja & Anggaran Perusahaan)** pada perusahaan baru. Aplikasi ini dirancang menggunakan arsitektur **Single-Tenant White-Label**, di mana setiap perusahaan memiliki instance dan basis data independen dengan penyesuaian identitas brand, struktur organisasi, dan hak akses tanpa mengubah kode sumber.

---

## 1. Prasyarat Sistem

Sebelum memulai instalasi pada server target, pastikan lingkungan server memenuhi persyaratan berikut:

| Komponen | Versi Minimal | Keterangan |
|---|---|---|
| **Sistem Operasi** | Linux (Ubuntu 22.04 LTS / Debian 12 / RHEL 9) atau Windows Server | Disarankan Linux untuk produksi |
| **PHP** | 8.3+ | Ekstensi: `pdo_pgsql`, `pgsql`, `mbstring`, `xml`, `curl`, `gd`, `zip`, `bcmath`, `intl` |
| **Database** | PostgreSQL 14+ | Sangat direkomendasikan PostgreSQL untuk performa analitik |
| **Web Server** | Nginx 1.20+ atau Apache 2.4+ | Konfigurasi reverse proxy PHP-FPM |
| **Node.js & NPM** | Node.js 18+ LTS | Untuk kompilasi aset frontend |
| **Composer** | 2.5+ | Dependency manager PHP |

---

## 2. Cara Cepat: Menggunakan Setup Wizard

Aplikasi telah dilengkapi dengan wizard interaktif otomatis yang memandu konfigurasi dari awal:

### Di Windows:
```cmd
setup.bat
```

### Di Linux / macOS:
```bash
chmod +x setup.sh
./setup.sh
```

### Atau Langsung Melalui Artisan:
```bash
php artisan rkap:setup
```

Wizard ini akan secara interaktif menanyakan:
1. Nama Perusahaan & Akronim
2. Website & Domain Email
3. Akun Super Administrator (Email & Password)
4. Kode Warna Brand Utama (Hex)
5. Pilihan data sampel (pilih *No/Tidak* untuk instalasi bersih)
6. Menjalankan migrasi dan seeding database

### Mode Presentasi & Setup Demo Cepat:
Jika Anda menyiapkan instance untuk keperluan demo/presentasi ke calon klien:
```bash
php artisan rkap:demo
```
Perintah ini otomatis menginisialisasi akun login per peran alur bisnis (`demo.*@rkap.com`), simulasi pengajuan multi-status, data realisasi bulanan, dan pergeseran anggaran. Lihat panduan lengkap di [docs/DEMO_GUIDE.md](file:///docs/DEMO_GUIDE.md).

---

## 3. Cara Manual: Instalasi Langkah-demi-Langkah

Jika Anda melakukan provisioning otomatis (CI/CD, Ansible, atau skrip bash), ikuti langkah berikut:

### Langkah 1: Clone Repository & Install Dependensi
```bash
git clone <repository-url> /var/www/rkap
cd /var/www/rkap

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Frontend dependencies & compile assets
npm install
npm run build
```

### Langkah 2: Konfigurasi File Lingkungan (.env)
Salin `.env.example` menjadi `.env`:
```bash
cp .env.example .env
php artisan key:generate
```

Buka file `.env` dan sesuaikan koneksi database dan identitas perusahaan:

```env
APP_NAME="RKAP Perusahaan Anda"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rkap.perusahaan-anda.co.id
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

# ── Konfigurasi Database PostgreSQL ──
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=rkap_production
DB_USERNAME=postgres
DB_PASSWORD=PasswordDatabaseAman123!

# ── Identitas Perusahaan (White-Label) ──
RKAP_COMPANY_NAME="PT Maju Makmur Sentosa"
RKAP_COMPANY_SHORT_NAME="MMS"
RKAP_COMPANY_URL="https://majumakmur.co.id"
RKAP_COMPANY_TAGLINE="Sistem Rencana Kerja & Anggaran Perusahaan"
RKAP_APP_SHORT_TITLE="RKAP"
RKAP_EMAIL_DOMAIN="majumakmur.co.id"

# ── Warna Tema Brand (Hex) ──
RKAP_THEME_PRIMARY="#1e40af"    # Warna tombol & aksen utama
RKAP_THEME_DARK="#0f172a"       # Warna sidebar & elemen gelap
RKAP_THEME_ACCENT="#3b82f6"     # Warna sorotan

# ── Akun Super Administrator ──
RKAP_ADMIN_EMAIL="admin@majumakmur.co.id"
RKAP_SEED_PASSWORD="GantiPasswordAman2026!"
RKAP_FINANCE_DIRECTORATE_CODE="FIN"
RKAP_SEED_SAMPLE_DATA=false     # 'false' untuk instalasi bersih
```

### Langkah 3: Migrasi Database & Seeding
```bash
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force
```

### Langkah 4: Optimasi Cache Laravel untuk Produksi
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Langkah 5: Set Hak Akses Direktori
```bash
chown -R www-data:www-data storage bootstrap/cache public/assets/img/brand
chmod -R 775 storage bootstrap/cache public/assets/img/brand
```

---

## 4. Kustomisasi Branding & Logo Perusahaan

Pengaturan logo dan identitas perusahaan dapat dilakukan dengan dua cara fleksibel:

### Opsi 1: Melalui Antarmuka Web (UI Settings)
1. Login sebagai pengguna **Administrator**.
2. Masuk ke menu **Settings > Brand & Logo** (`/settings/brand`).
3. Pada halaman tersebut, Anda dapat langsung:
   - Mengunggah file logo baru untuk Sidebar, Navbar, Form Login, Hero Panel Login, dan Avatar Profil.
   - Mengubah Nama Perusahaan, Akronim, dan Tagline aplikasi.
   - Memilih warna primer dan warna gelap tema secara visual menggunakan *Color Picker*.
   - Melakukan reset per-aset atau seluruhnya kembali ke konfigurasi `.env`.

### Opsi 2: Melalui File Konfigurasi `.env`
Anda dapat menentukan jalur file logo langsung pada file `.env`:

```env
RKAP_LOGO_SIDEBAR="assets/img/brand/logo_sidebar.png"
RKAP_LOGO_NAVBAR="assets/img/brand/logo_navbar.png"
RKAP_LOGO_LOGIN="assets/img/brand/logo_login.png"
RKAP_LOGO_LOGIN_HERO="assets/img/brand/login_hero.png"
RKAP_AVATAR_DEFAULT="assets/img/brand/default_avatar.png"
```

Panduan dimensi dan spesifikasi aset visual:

| Aset | Posisi Tampil | Rekomendasi Ukuran | Format |
|---|---|---|---|
| **Logo Sidebar** | Pojok kiri atas menu samping | Lebar 180–220px, Tinggi 40–60px | PNG Transparan / SVG |
| **Logo Navbar** | Navigasi atas di samping nama app | Lebar 120–160px, Tinggi 35–45px | PNG Transparan / SVG |
| **Logo Login** | Di atas kartu formulir login | Lebar 180–240px, Tinggi 50–80px | PNG Transparan / SVG |
| **Hero Login** | Latar visual panel kiri halaman login | 1200x800px | JPG / WebP |
| **Default Avatar** | Foto profil default pengguna | 128x128px (Rasio 1:1) | PNG / JPG |

---

## 5. Kustomisasi Hirarki Organisasi & Role Akses

Setiap perusahaan memiliki struktur eselon dan sebutan jabatan yang unik. RKAP mendukung kustomisasi ini melalui `.env`:

### Kustomisasi Sebutan Tingkat Organisasi:
```env
# Contoh jika perusahaan menggunakan Divisi -> Departemen -> Seksi:
RKAP_ORG_LEVEL_1="Divisi"
RKAP_ORG_LEVEL_2="Departemen"
RKAP_ORG_LEVEL_3="Seksi"
```

### Kustomisasi Nama Role & Label Jabatan Approval:
```env
# Label yang tampil pada alur persetujuan (approval flow):
RKAP_LABEL_ADMIN="Administrator"
RKAP_LABEL_KEPALA_BIRO="Section Head / Manager"
RKAP_LABEL_KEPALA_DEPARTEMEN="Department Head / Senior Manager"
RKAP_LABEL_DIREKSI="Division Head / Vice President"
RKAP_LABEL_VERIFIKATOR="Tim Budgeting & Keuangan"
RKAP_LABEL_DIREKTUR_UTAMA="Managing Director / CEO"
RKAP_LABEL_DIREKTUR_KEUANGAN="Chief Financial Officer / CFO"
```

---

## 6. Konfigurasi Web Server (Nginx Contoh)

Berikut contoh konfigurasi Nginx untuk instance RKAP:

```nginx
server {
    listen 80;
    server_name rkap.perusahaan-anda.co.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name rkap.perusahaan-anda.co.id;
    root /var/www/rkap/public;

    ssl_certificate /etc/ssl/certs/rkap.crt;
    ssl_certificate_key /etc/ssl/private/rkap.key;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 7. Checklist Pra-Produksi (Go-Live)

Sebelum menyerahkan sistem ke pengguna akhir:
- [ ] Database PostgreSQL sudah dibackup secara berkala (cron job `pg_dump`).
- [ ] `APP_DEBUG=false` di `.env`.
- [ ] Kredensial super admin default sudah diganti dengan kata sandi yang kuat.
- [ ] SMTP Server untuk notifikasi email sudah dikonfigurasi (`MAIL_MAILER`, `MAIL_HOST`, dll).
- [ ] Logo dan warna brand sudah sesuai dengan *brand guidelines* perusahaan.
- [ ] Struktur organisasi (Direktorat, Departemen, Biro) sudah diisi melalui menu **Settings > Organisasi**.
- [ ] Akun pengguna dan verifikator sudah dibuat di menu **Settings > User Management**.
- [ ] Data COA (Chart of Accounts) dan Program Kerja sudah diimpor (lihat [Panduan Data Master](file:///docs/MASTER_DATA_GUIDE.md)).
- [ ] Periode RKAP tahun berjalan sudah dibuat dan berstatus **Draft** atau **Open**.
