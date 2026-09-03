# Petunjuk Aset Branding Perusahaan (White-Label)

Direktori ini digunakan untuk menyimpan logo dan gambar identitas visual perusahaan. Anda dapat mengganti file-file berikut langsung atau mengubah jalurnya melalui file `.env`.

| File | Konfigurasi `.env` | Ukuran Rekomendasi | Format | Keterangan |
|---|---|---|---|---|
| `logo_sidebar.png` | `RKAP_LOGO_SIDEBAR` | Lebar ~180-220px, Tinggi ~40-60px | PNG Transparan / SVG | Tampil di pojok kiri atas menu sidebar |
| `logo_navbar.png` | `RKAP_LOGO_NAVBAR` | Lebar ~120-160px, Tinggi ~35-45px | PNG Transparan / SVG | Tampil di navbar atas (layout desktop/mobile) |
| `logo_login.png` | `RKAP_LOGO_LOGIN` | Lebar ~180-240px, Tinggi ~50-80px | PNG Transparan / SVG | Tampil di kartu form login |
| `login_hero.png` | `RKAP_LOGO_LOGIN_HERO` | 1200x800px atau 1920x1080px | JPG / PNG / WebP | Gambar latar panel kiri halaman login |
| `default_avatar.png` | `RKAP_AVATAR_DEFAULT` | 128x128px (1:1) | PNG / JPG | Avatar default pengguna jika belum memiliki foto |

## Cara Mengganti Logo:
1. Simpan file logo baru Anda di direktori ini (misal `logo_sidebar.png`), **ATAU**
2. Simpan di direktori kustom dan ubah jalurnya pada file `.env`:
   ```env
   RKAP_LOGO_SIDEBAR="assets/img/brand/logo_perusahaan_anda.png"
   RKAP_LOGO_LOGIN="assets/img/brand/logo_login.png"
   RKAP_LOGO_LOGIN_HERO="assets/img/brand/gedung_kantor.jpg"
   ```
3. Bersihkan cache aplikasi:
   ```bash
   php artisan config:clear
   ```
