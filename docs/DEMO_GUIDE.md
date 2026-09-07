# Panduan Presentasi Demo Produk RKAP (Demo Showcase)

Dokumen ini berisi panduan lengkap untuk melakukan presentasi (demo produk) sistem **RKAP (Rencana Kerja & Anggaran Perusahaan)** kepada calon klien, investor, atau manajemen.

---

## 1. Menyiapkan Lingkungan Demo

Untuk menyiapkan seluruh akun demo dan data simulasi dalam hitungan detik, jalankan perintah berikut di terminal:

```bash
php artisan rkap:demo
```

> [!TIP]
> Jika Anda ingin membersihkan database dari nol sebelum memulai demo, gunakan opsi `--fresh`:
> ```bash
> php artisan rkap:demo --fresh
> ```

> [!NOTE]
> Seeder demo bersifat **organization-agnostic** — ia akan secara otomatis mendeteksi dan menggunakan struktur organisasi yang sudah ada di database (Direktorat, Departemen, Biro/Seksi), apapun sektor industri perusahaan yang diterapkan. Jika belum ada organisasi, seeder akan membuat struktur generik minimal untuk keperluan demo.

---

## 2. Daftar Kredensial Akun Demo

Semua akun demo menggunakan kata sandi (*password*) yang seragam:

> **Password Semua Akun Demo:** `Demo123!`

| Akun / Email | Peran (*Role*) | Skenario Presentasi |
|---|---|---|
| **`demo.admin@rkap.com`** | Super Admin | Pengaturan brand & logo, manajemen user, setting periode RKAP, dan master data |
| **`demo.biro@rkap.com`** | Kepala Biro / Section Head | Demonstrasi penyusunan anggaran baru (*Draft*), input COA, cash-out, dan submit |
| **`demo.dept@rkap.com`** | Kepala Departemen / Dept Head | Review pengajuan dari unit bawahan, approval tingkat departemen, dan catatan revisi |
| **`demo.direksi@rkap.com`** | Direksi / Division Head | Approval tingkat pimpinan pada pengajuan dari departemen |
| **`demo.verifikator@rkap.com`** | Verifikator Anggaran | Verifikasi akhir anggaran, upload realisasi, dan analitik keuangan |
| **`demo.dirut@rkap.com`** | Direktur Utama / CEO | Persetujuan final RKAP tingkat perusahaan |
| **`demo.staff@rkap.com`** | Staf / User | Akses staf penginput program kerja |

> [!NOTE]
> Jabatan dan unit organisasi pada setiap akun demo akan menyesuaikan secara otomatis dengan struktur organisasi yang ada di database. Untuk melihat detail lengkap posisi dan unit yang terisi, jalankan `php artisan rkap:demo` dan lihat tabel yang tampil di terminal.

---

## 3. Data Simulasi Pengajuan RKAP

Seeder demo secara otomatis membuat **5 pengajuan RKAP** dengan status alur persetujuan yang berbeda, ditempatkan pada unit kerja yang tersedia di database:

| Status Alur | Skenario Demo | Akun yang Digunakan |
|---|---|---|
| **Approved** ✅ | Dashboard & grafik serapan anggaran vs realisasi langsung terisi data. | `demo.admin` atau `demo.dirut` |
| **Final Review** | Verifikator dapat melakukan verifikasi akhir anggaran. | `demo.verifikator` |
| **Dir Review** | Pimpinan/Direksi dapat melakukan approval tingkat atas. | `demo.direksi` |
| **Submitted** | Kepala Departemen dapat mereview dan memberikan persetujuan/revisi. | `demo.dept` |
| **Draft** | Kepala Biro dapat mengedit dan mendemonstrasikan pengisian dari nol. | `demo.biro` |

Seeder juga menyiapkan:
- **Alokasi anggaran bulanan** (Jan–Des) untuk pengajuan yang telah disetujui.
- **Data realisasi serapan** (Jan–Jun) sehingga grafik dashboard langsung berwarna.
- **1 simulasi pergeseran anggaran** (*Budget Transfer*) antar unit kerja.
- **Riwayat approval & komentar** pada setiap pengajuan.

---

## 4. Skenario Alur Presentasi Rekomendasi (Demo Flow)

Untuk memberikan impresi terbaik kepada audiens, ikuti alur presentasi berikut:

### Skenario 1: Executive Dashboard & Pemantauan Realisasi
1. **Login sebagai `demo.dirut@rkap.com`** atau **`demo.admin@rkap.com`**.
2. Tunjukkan halaman **Dashboard Utama**:
   - Kartu ringkasan total anggaran, serapan, dan status kompilasi.
   - Grafik tren alokasi bulanan vs serapan realisasi.
   - Pemantauan anggaran per unit organisasi secara *real-time*.

### Skenario 2: Alur Persetujuan Bertingkat (Workflow Approval)
1. **Login sebagai `demo.dept@rkap.com`** — Review pengajuan berstatus *Submitted*, lalu approve atau minta revisi.
2. **Login sebagai `demo.direksi@rkap.com`** — Approve pengajuan berstatus *Dir Review*.
3. **Login sebagai `demo.verifikator@rkap.com`** — Verifikasi pengajuan berstatus *Final Review*. Tunjukkan panel komentar dan riwayat audit trail.

### Skenario 3: Input Anggaran Baru
1. **Login sebagai `demo.biro@rkap.com`**.
2. Buka pengajuan berstatus *Draft*.
3. Demonstrasikan: Tambah program kerja, pilih COA dengan auto-suggest, atur distribusi bulanan.

### Skenario 4: Pergeseran Anggaran (Budget Transfer)
1. Buka menu **Pergeseran Anggaran**.
2. Tunjukkan simulasi pergeseran dana antar unit kerja.

### Skenario 5: White-Label & Kustomisasi Brand
1. **Login sebagai `demo.admin@rkap.com`**.
2. Masuk ke **Settings > Brand & Logo**.
3. Tunjukkan bahwa logo, nama perusahaan, tagline, dan warna tema dapat diganti langsung via antarmuka.

---

## 5. Reset Lingkungan Demo

Setelah sesi demo selesai, kembalikan ke kondisi awal kapan saja:

```bash
php artisan rkap:demo
```
