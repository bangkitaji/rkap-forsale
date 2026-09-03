# Panduan Penyiapan & Impor Data Master Perusahaan

Aplikasi RKAP dirancang untuk dapat menyesuaikan bagan akun (*Chart of Accounts* / COA), susunan program kerja, dan struktur organisasi dari setiap perusahaan secara fleksibel. Dokumen ini menjelaskan urutan dan format penyiapan data master pada implementasi baru.

---

## 1. Urutan Pengisian Data Master (Dependency Order)

Untuk memastikan relasi data tidak terputus, lakukan penyiapan data dengan urutan berikut:

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Struktur Organisasi (Direktorat → Departemen → Biro)    │
└──────────────────────────────┬──────────────────────────────┘
                               ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. Kelompok COA (COA Groups) & Bagan Akun (COA)             │
└──────────────────────────────┬──────────────────────────────┘
                               ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Program Kerja (Work Plans)                               │
└──────────────────────────────┬──────────────────────────────┘
                               ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Kegiatan (Activities)                                    │
└──────────────────────────────┬──────────────────────────────┘
                               ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. Pemetaan Kegiatan ke Akun (Activity-COA Mapping)         │
└─────────────────────────────────────────────────────────────┘
```

---

## 2. Mengunduh Template Excel

Aplikasi menyediakan generator template otomatis. Anda dapat menghasilkan template Excel standar kapan saja menggunakan perintah Artisan:

```bash
php artisan generate:import-templates
```

File template akan tersimpan di direktori `public/templates/`:
- `public/templates/coa_template.xlsx`
- `public/templates/workplan_template.xlsx`
- `public/templates/activity_template.xlsx`
- `public/templates/activity_coa_mapping_template.xlsx`

Atau pengguna administrator dapat langsung mengunduh template tersebut melalui tautan unduh di halaman menu **Master Data** pada antarmuka web.

---

## 3. Format Penyiapan File

### A. Bagan Akun (*Chart of Accounts* / COA)
File: `coa_template.xlsx`

| Kolom Header | Wajib? | Contoh | Keterangan |
|---|---|---|---|
| `code` | Ya | `510101001` | Kode akun unik sesuai sistem akuntansi/ERP perusahaan (SAP, Oracle, Accurate, dll) |
| `title` | Ya | `Gaji Pokok Karyawan Tetap` | Nama akun dalam Bahasa Indonesia |
| `title_en` | Opsional | `Basic Salary Permanent Staff` | Nama akun dalam Bahasa Inggris |
| `description` | Opsional | `Beban gaji bulanan karyawan tetap` | Deskripsi singkat fungsi akun |
| `coa_group_code` | Ya | `510000` | Kode kelompok COA induknya |

> **Catatan:** Sistem telah menyediakan kelompok COA standar PSAK (100000 = Aset, 200000 = Liabilitas, 300000 = Ekuitas, 400000 = Pendapatan, 510000 = Beban Pegawai, 520000 = Beban OPEX, 530000 = Beban CAPEX, 600000 = Non-Operasional). Perusahaan dapat menambah kelompok COA baru di menu **Master Data > COA Groups**.

### B. Program Kerja (*Work Plans*)
File: `workplan_template.xlsx`

| Kolom Header | Wajib? | Contoh | Keterangan |
|---|---|---|---|
| `code` | Ya | `WP-2026-001` | Kode unik program kerja |
| `title` | Ya | `Digitalisasi Sistem Operasional` | Judul program kerja (Bahasa Indonesia) |
| `title_en` | Opsional | `Operational System Digitalization` | Judul program kerja (Bahasa Inggris) |
| `bureau_code` | Opsional | `IT-DEV` | Kode Biro/Seksi penanggung jawab |

### C. Kegiatan (*Activities*)
File: `activity_template.xlsx`

| Kolom Header | Wajib? | Contoh | Keterangan |
|---|---|---|---|
| `work_plan_code` | Ya | `WP-2026-001` | Kode program kerja induk |
| `code` | Ya | `ACT-2026-001-01` | Kode kegiatan unik |
| `title` | Ya | `Pengadaan Lisensi Software & Server` | Nama kegiatan |
| `title_en` | Opsional | `Software License & Server Procurement` | Nama kegiatan dalam Bahasa Inggris |

### D. Pemetaan Kegiatan ke Akun (*Activity-COA Mapping*)
File: `activity_coa_mapping_template.xlsx`

| Kolom Header | Wajib? | Contoh | Keterangan |
|---|---|---|---|
| `activity_code` | Ya | `ACT-2026-001-01` | Kode kegiatan |
| `coa_code` | Ya | `520101005` | Kode akun yang boleh dipilih untuk kegiatan tersebut |

---

## 4. Cara Impor Data

### Melalui Antarmuka Web (UI)
1. Login sebagai pengguna bertipe **Administrator** atau memiliki izin `masterdata.*.manage`.
2. Masuk ke menu **Master Data**:
   - Untuk COA: Buka **Master Data > Coa**, klik tombol **Import Excel**.
   - Untuk Program Kerja: Buka **Master Data > Work Plans**, klik tombol **Import Excel**.
   - Untuk Kegiatan: Buka **Master Data > Activities**, klik tombol **Import Excel**.
   - Untuk Pemetaan: Buka **Master Data > Activity COA Mapping**, klik tombol **Import Excel**.
3. Pilih file Excel yang telah diisi, lalu klik **Upload**.
4. Sistem akan memvalidasi duplikasi kode dan relasi induk secara otomatis.

---

## 5. Menyiapkan Struktur Organisasi Perusahaan

Pengaturan unit kerja dapat dilakukan di menu **Settings > Organization**:
1. **Direktorat / Divisi:** Tambahkan unit tingkat atas (misal: Direktorat Keuangan, Direktorat Operasi, dll). Pastikan mencatat kode direktorat keuangan jika ingin disinkronkan dengan `RKAP_FINANCE_DIRECTORATE_CODE`.
2. **Departemen:** Tambahkan departemen dan hubungkan ke Direktorat induknya.
3. **Biro / Seksi:** Tambahkan biro/unit pelaksana dan hubungkan ke Departemen induknya. Biro inilah yang akan menjadi pemilik pengajuan anggaran (*submission owner*).

---

## 6. Pertanyaan Umum (FAQ)

**T: Bagaimana jika perusahaan kami hanya memiliki 2 tingkat organisasi (Departemen → Seksi)?**
> Anda cukup membuat satu Direktorat Utama sebagai wadah induk, lalu membuat Departemen dan Seksi (sebagai Biro). Pada `.env`, sesuaikan label tampilan:
> ```env
> RKAP_ORG_LEVEL_1="Kantor Pusat"
> RKAP_ORG_LEVEL_2="Departemen"
> RKAP_ORG_LEVEL_3="Seksi"
> ```

**T: Bisakah akun COA diubah setelah pengajuan RKAP dimulai?**
> Bisa, namun disarankan tidak menghapus akun yang telah memiliki item anggaran tertaut (*budget items*). Anda dapat menonaktifkan akun agar tidak dipilih kembali untuk periode berikutnya.
