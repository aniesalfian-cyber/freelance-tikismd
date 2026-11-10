# Monitoring Freelancer HSM (`freelance.tikismd.id`)

Aplikasi **PWA (Progressive Web App) Mobile-First & Responsive Web** untuk memonitor, mengukur, dan mengevaluasi produktivitas serta performa kerja karyawan freelancer PT HSM secara *real-time*, presisi, dan terukur.

---

## 1. Akun Login Default (4 Role)

| Role | Email | Password | Hak Akses Utama |
| :--- | :--- | :--- | :--- |
| **SuperAdmin** | `superadmin@hsm.id` | `Password123!` | Konfigurasi sistem, CRUD seluruh user, Quick Reset Password + WA, Backup & Restore Database MySQL, Audit Log |
| **Admin HRD / SPV** | `hrd@hsm.id` | `Password123!` | CRUD Master Gerai (Leaflet Map Picker), CRUD Freelancer, Template Tugas Rutin, Distribusi Tugas (Pasti & Tidak Pasti), Validasi Presensi, Hitung Ulang KPI |
| **Freelancer** | `freelancer@hsm.id` | `Password123!` | Workspace PWA Mobile, Presensi GPS Geofencing Kantor Utama, Checklist 3-Status Tugas (`Belum Diproses` → `Sedang Diproses` → `Selesai Diproses`), Rapor KPI Pribadi |
| **Owner** | `owner@hsm.id` | `Password123!` | Executive Analytics Dashboard (6 KPI Cards, Live Outlet Map, 4 Grafik Chart.js, Leaderboard), Laporan Corporate Professional (PDF & Excel) |

*(Tersedia juga 4 akun freelancer tambahan pada data demo: `ahmad@hsm.id`, `budi@hsm.id`, `citra@hsm.id`, `dewi@hsm.id` dengan password `Password123!`)*

---

## 2. Perintah Operasional & Penjadwal Otomatis

```bash
# Menjalankan server aplikasi
php artisan serve --host=0.0.0.0 --port=8000

# Generate tugas pasti harian secara manual
php artisan tasks:generate-daily

# Hitung skor KPI harian seluruh freelancer
php artisan kpi:calculate-daily

# Buat arsip backup database MySQL (.sql.gz)
php artisan backup:run

# Jalankan seluruh pengujian otomatis (PHPUnit)
php artisan test
```
