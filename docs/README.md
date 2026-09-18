# Dokumentasi Proyek Akubumdes

Akubumdes adalah modern upgrade & re-engineering dari **SIMAK (Sistem Informasi Manajemen Akuntansi Keuangan BUMDes, `ab1-team/simak`)**, dan **BUKAN** dari SIDBM (aplikasi pinjaman/lending UPK).

Indeks dokumentasi arsitektur, panduan pengguna, basis data, billing, modul supervisi, RBAC, asisten AI, dan pengujian Akubumdes:

---

## 1. Panduan Pengguna & Operasional

Dokumentasi penggunaan aplikasi untuk pengguna akhir, pengelola BUMDesma/LKD, operator desa, supervisor wilayah, dan administrator:

- [USER_GUIDE.md](USER_GUIDE.md) – **Panduan Pengguna Lengkap (User Manual)**: Mencakup seluruh modul akuntansi & keuangan aplikasi (Dashboard, Master Data, Akuntansi & Jurnal, Inventaris & Aset, E-Budgeting, Pelaporan Keuangan, Prosedur Periodik, Billing SaaS, Notifikasi, RBAC, Onboarding, Portal Supervisi Kabupaten/Provinsi, Superadmin, dan AI Assistant).
- [VALIDATION.md](VALIDATION.md) – Panduan verifikasi statis, pengujian backend PHPUnit, dan pengujian frontend Playwright browser (E2E).

---

## 2. Arsitektur & Spesifikasi Sistem

Dokumentasi teknis arsitektur, skema basis data, keamanan hak akses, billing, integrasi holding, dan integrasi AI:

- [PROJECT_OVERVIEW.md](PROJECT_OVERVIEW.md) – Gambaran umum proyek, arsitektur multi-tenant sharding, sasaran, risiko, dan kriteria penyelesaian (*definition of done*).
- [HOLDING_API_INTEGRATION_GUIDE.md](HOLDING_API_INTEGRATION_GUIDE.md) – **Panduan Integrasi API Holding & Konsolidasi**: Spesifikasi endpoint RESTful API laporan keuangan (Neraca, Laba Rugi, Arus Kas, CALK, Perubahan Ekuitas, Paket 5-in-1, dan Konsolidasi Multi-Tenant) untuk integrasi dengan aplikasi holding / enterprise luar.
- [DATABASE_STRUCTURE.md](DATABASE_STRUCTURE.md) – Struktur detail skema platform dan database shard multi-tenant, pemetaan tabel legacy, pembentukan identitas ganda (`row_id` vs `id`), hierarki supervisi, dan portabilitas MySQL/SQLite.
- [RBAC_MATRIX.md](RBAC_MATRIX.md) – Matriks hak akses (*Role-Based Access Control*) dan 37 permission granular modul tenant, supervisor provinsi/kabupaten, operator desa, dan platform superadmin.
- [BILLING_PAYMENT_AUTOMATION.md](BILLING_PAYMENT_AUTOMATION.md) – Spesifikasi integrasi Multi-Payment Gateway (Tripay, Duitku, Xendit) (QRIS & Virtual Accounts), automatisasi invoice perpanjangan, dan middleware pembatasan tenant overdue.
- [ASSISTANT_INTEGRATION.md](ASSISTANT_INTEGRATION.md) – Spesifikasi integrasi Asisten AI (`enpii/assistant`), pgvector store RAG, Ollama embedding server, dan komponen chat interaktif.
- [ai-assistant-project-guide.md](ai-assistant-project-guide.md) – Panduan teknis proyek implementasi modul AI Assistant dan ekosistem pendukungnya.

---

## 3. Analisis Komparatif & Migrasi SIMAK (Legacy)

Akubumdes adalah modern upgrade & re-engineering dari **SIMAK (`ab1-team/simak`)**, dan **BUKAN** dari SIDBM. Dokumentasi perbandingan mendalam antara SIMAK (legacy, sistem monolitik tabel dinamis per unit usaha) dengan arsitektur modern Akubumdes:

- [PERBANDINGAN_SIMAK_LEGACY_VS_AKUBUMDES.md](PERBANDINGAN_SIMAK_LEGACY_VS_AKUBUMDES.md) – Analisis komparatif menyeluruh **SIMAK (Legacy) vs Akubumdes**: alasan upgrade, arsitektur, double-entry ledger, 3 varian CoA, SaaS billing, supervisi wilayah, dan infrastruktur.
- [PERBANDINGAN_DATABASE_SIMAK_VS_AKUBUMDES.md](PERBANDINGAN_DATABASE_SIMAK_VS_AKUBUMDES.md) – Pemetaan & perbandingan skema tabel database **SIMAK vs Akubumdes** (`usaha`, `akun_level_*`, `rekening_{n}`, `accounts_{n}`, `transaksi_{n}`, `saldo_{n}` → `tenants`, `accounts`, `journal_entries`/`journal_lines`, `account_monthly_balances`).
- [LEGACY_REPORTS_MIGRATION_ROADMAP.md](LEGACY_REPORTS_MIGRATION_ROADMAP.md) – Matriks spesifikasi dan status 100% implementasi 9 laporan akuntansi core SIMAK, CALK, paket LPJ tahunan, dan rekap aset.
- [CUTOVER_RUNBOOK.md](CUTOVER_RUNBOOK.md) – Panduan teknis migrasi & cutover data per tenant dari basis data SIMAK ke Akubumdes.

---

## 4. Roadmap & Riwayat Pengujian

- [FLUTTER_MOBILE_ROADMAP.md](FLUTTER_MOBILE_ROADMAP.md) – **Roadmap Mobile App (Flutter Native Companion)**: Panduan arsitektur Clean Architecture, pemisahan fitur Mobile vs Web/Desktop, integrasi printer thermal bluetooth, GPS, survei 5C, approval mobile, dan checklist fase kerja.
- [DESKTOP_ROADMAP.md](DESKTOP_ROADMAP.md) – **Roadmap Desktop App (NativePHP + SQLite Read-Only Offline)**: Panduan arsitektur, strategi pull-sync satu arah, matriks online vs offline, dan checklist implementasi desktop installer.
- [FEATURE_ROADMAP.md](FEATURE_ROADMAP.md) – Status implementasi fitur harian, modul supervisi, pembatasan operator desa, suite pengujian, dan changelog rilis.
- [TEST_AUDIT_LOG.md](TEST_AUDIT_LOG.md) – Log audit hasil pengujian backend PHPUnit (258 tests) dan Playwright E2E browser tests.

---

## Keputusan Arsitektur Inti

- **Topologi**: Platform Database + Multi-Tenant Shard Database (`tenant_id` column-based isolation) dengan opsi MySQL 8.4 dan SQLite support.
- **Identitas**: `row_id` sebagai PK internal teknis, `id` lama dipertahankan utuh untuk laporan & audit.
- **Akuntansi**: Double-entry journal (`journal_entries` & `journal_lines`) yang seimbang dan bersifat *immutable* ? koreksi jurnal posted melalui reverse + recreate atomik (`JournalEditService`).
- **Supervisi Berjenjang (Kabupaten & Provinsi)**: Dashboard & laporan keuangan konsolidasi real-time lintas kecamatan & kabupaten (Neraca, LR, BB, Arus Kas, CALK, PDF Pack).
- **Pembatasan Operator Desa (Village Scope)**: Pengguna level desa (`is_village_user`) hanya dapat melihat dan mengelola data wilayah desa bersangkutan via global scope `VillageScope`.
- **SaaS Billing**: Integrasi Multi-Payment Gateway (Tripay, Duitku, Xendit) dengan auto-invoice scheduler & penangguhan otomatis tenant overdue.
- **Automated Testing**: 100% Passed across layers (PHPUnit 391 tests + Playwright E2E page & interactive CRUD tests).

