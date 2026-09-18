# Roadmap Fitur & Status Implementasi (Akubumdes vs SIMAK Legacy)

Tujuan: **Akubumdes sepenuhnya menggantikan SIMAK (Sistem Informasi Manajemen Akuntansi Keuangan BUMDes, `ab1-team/simak`) dalam operasional harian unit usaha BUMDes dan tingkat Kabupaten.**

> Akubumdes adalah modern upgrade & re-engineering dari SIMAK, dan **BUKAN** dari SIDBM. Modul lending/UPK tidak menjadi bagian dari ruang lingkup Akubumdes.

Dokumentasi Arsitektur: PROJECT_OVERVIEW.md, DATABASE_STRUCTURE.md, CUTOVER_RUNBOOK.md, PERBANDINGAN_SIMAK_LEGACY_VS_AKUBUMDES.md, PERBANDINGAN_DATABASE_SIMAK_VS_AKUBUMDES.md.

---

## Status Fitur & Zona (Update 2026-08-18)

| Zona | Status Akubumdes | Keterangan |
|---|---|---|
| Landing Page & Auth | ✅ Selesai | Redesain halaman depan profesional (hero, features, steps, FAQ accordion, smooth scroll, CTA), halaman login clean & modern tanpa kebocoran stack teknis. |
| Master Data | ✅ Selesai | Desa, Unit Usaha (3 varian CoA), Mitra/Lembaga + Import/Export CSV |
| Bagan Akun (CoA) 3 Varian | ✅ Selesai | Import varian `standard` (jasa/umum), `trading` (perdagangan + HPP), `cooperative` (koperasi) sesuai `tenants.coa_variant` |
| Jurnal & Akuntansi | ✅ Selesai | Jurnal Umum, Jurnal Angsuran, Reversal, **Edit Jurnal (Reverse + Recreate Atomik)**, COA Read-only |
| Cetak Bukti Kas & Kuitansi | ✅ Selesai | BKM/BKK/BM PDF langsung dari posting jurnal |
| Laporan Akuntansi Core | ✅ Selesai | Neraca, Laba Rugi, Buku Besar, Arus Kas, Perubahan Ekuitas, CALK, Neraca Saldo, Jurnal Transaksi |
| Analisis Kinerja & Aset | ✅ Selesai | Penilaian Tingkat Kesehatan Usaha, Rekap Aset Tetap, Rekap Aset Tak Berwujud |
| Paket LPJ Tahunan | ✅ Selesai | Cover Buku LPJ, Surat Pengantar, Berita Acara, MoU, Hub LPJ Pack |
| Tutup Buku & Alokasi Laba | ✅ Selesai | Tutup/Buka periode fiskal + Jurnal alokasi laba otomatis |
| Inventaris / Aset Tetap | ✅ Selesai | Register aset, Jurnal pembelian inventaris, Nilai buku & akumulasi penyusutan |
| E-Budgeting / RAPB | ✅ Selesai | Input anggaran per akun per bulan, navigasi tahun, simpan RAPB |
| Global Search | ✅ Selesai | Omnibox search header (akun, mitra, jurnal, aset) |
| SaaS Billing & Payment Gateway | ✅ Selesai | Multi Payment Gateway (Duitku & Tripay) dengan In-app Payment Channel (QRIS, E-Wallet, VA Bank, Credit Card), Auto-Invoice Scheduler, Active Gateway Switcher, dan Overdue Grace Period Suspension |
| Monitoring Kabupaten (Regency) | ✅ Selesai | Dashboard Kabupaten, Laporan Konsolidasi Multi-Kecamatan (Neraca, LR, BB, Arus Kas, CALK, PDF) |
| Monitoring Provinsi (Province) | ✅ Selesai | Dashboard Provinsi, Laporan Konsolidasi Multi-Kabupaten & Multi-Kecamatan (Neraca, LR, BB, Arus Kas, CALK, Paket PDF) |
| Pembatasan Operator Desa (Village Scope) | ✅ Selesai | Restriksi data berbasis village_row_id dengan global scope VillageScope |
| AI Assistant & RAG | ✅ Selesai | Asisten AI (enpii/assistant) dengan Vector RAG (pgvector), Ollama, & Komponen Chat Interaktif |
| Redis Infrastructure | ✅ Selesai | Redis Cache Store, Redis Session Driver, & Dedicated Redis Queue Worker |
| Automated Testing Suite | ✅ Selesai | PHPUnit 391 tests (2.326 assertions) + Playwright E2E page tests + Playwright Interactive CRUD tests |

---

## Statistik Pengujian Automated (Terakhir: 2026-09-17)

| Suite Pengujian | Total Tests | Assertions | Durasi | Status |
|---|:---:|:---:|:---:|:---:|
| **Backend PHPUnit** (Unit & Feature) | 391 | 2.326 | ~5.5 menit | ✅ 100% Pass |
| **Playwright E2E Page & Route** (`all_features.spec.ts`) | 47 | — | ~4 menit | ✅ 100% Pass |
| **Playwright Interactive CRUD** (`all_interactive_crud.spec.ts`) | 25 | — | ~5 menit | ✅ 100% Pass |
| **Total** | **391+** | **2.326+** | **~15 menit** | **✅ 100% Pass** |

---

## Prioritas Eksekusi & Track Record

### P0 — Blocking Cutover Harian (DONE)
- **P0.1** Cetak bukti kas & kuitansi (BKM/BKK/BM PDF)
- **P0.2** Daftar jurnal + Reversal UI (JournalReversalService)
- **P0.3** Laporan akuntansi core SIMAK (Neraca, Laba Rugi, Buku Besar, Neraca Saldo)

### P1 — Paritas Pimpinan & Laporan Bulanan (DONE)
- **P1.1** Arus Kas (/accounting/reports/cash-flow) — Metode langsung + rekonsiliasi
- **P1.2** Perubahan Ekuitas (/accounting/reports/equity-change)
- **P1.3** CALK (/accounting/reports/calk) — Highlight otomatis + catatan kualitatif
- **P1.4** Bukti Kas (BKM/BKK/BM) per jurnal (/accounting/journals/{id}/cash-evidence)

### P2 — Periodik & Infrastruktur Multi-Tenant (DONE)
- **P2.1** Tutup buku & Alokasi Laba (ProfitAllocationService)
- **P2.2** Bagan Akun Standar (COA) UI Read-Only
- **P2.3** Inventaris & Aset Tetap (AssetService)
- **P2.4** Billing Automatisasi Multi-Gateway (Duitku & Tripay QRIS/VA/CC) & Suspension Middleware
- **P2.5** Portal Supervisi & Konsolidasi Keuangan Kabupaten (Regency Multi-Kecamatan)

### P3 — AI, Pengalaman Pengguna & Infrastruktur (DONE)
- **P3.1** Omnibox Global Search Header
- **P3.2** Asisten AI Ariel dengan pgvector + Ollama RAG & Interactive Widgets
- **P3.3** Redis Infrastructure Integration (Cache Store, Session Driver, Background Queue Worker)

### P4 — Migrasi Seluruh Laporan SIMAK & Pengujian Menyeluruh (DONE)
- **P4.1** Migrasi 100% laporan akuntansi SIMAK (9 core reports, CALK, Paket LPJ Tahunan, Rekap Aset Tetap & Tak Berwujud, Kesehatan Usaha)
- **P4.2** Redesain halaman Landing Page & Login (profesional, clean, modern, tanpa bocor info teknis)
- **P4.3** Suite pengujian E2E Playwright menyeluruh (47 Page Tests + 25 Interactive CRUD Tests)
- **P4.4** Suite pengujian Backend PHPUnit komprehensif

---

## Catatan Rilis & Changelog

| Tanggal | Fitur Utama yang Dirilis |
|---|---|
| 2026-07-28 | Inisialisasi P0–P3. P0.1 Cetak Bukti Kas/Kuitansi, P0.2 Reversal Jurnal, P0.3 Laporan Akuntansi Core SIMAK. |
| 2026-07-29 | Laporan P1 (Arus Kas, Ekuitas, CALK, Bukti Kas). P2.1 Tutup Buku, P2.2 COA UI, P2.3 Aset Tetap, P3.1 Global Search. |
| 2026-08-09 | Modul Integrasi Tripay (QRIS & Virtual Accounts), Automated Subscription Invoice Generator, Overdue Grace Period Suspension. |
| 2026-08-12 | Modul Integrasi Duitku Payment Gateway (Inquiry V2, Webhook MD5, E-Wallet/VA/CC), Superadmin Active Gateway Switcher, dan E2E Integration Suite. |
| 2026-08-10 | Modul Portal Kabupaten (Supervisory Dashboard & Real-Time Consolidated Financial Reports untuk Neraca, Laba Rugi, Arus Kas, BB, CALK + PDF Export). |
| 2026-08-10 | Migrasi penuh ke Redis Session Driver, Redis Queue Worker, refactoring Vue UI ke shared composables (useMoney, usePeriodOptions) dan komponen UI terstandar (SmartSelect, ReportPeriodFilter, AppRadioGroup). |
| 2026-08-10 | P4.1 Migrasi 100% seluruh laporan akuntansi SIMAK (9 core reports, CALK, Penilaian Kesehatan Usaha, Rekap Aset Tetap/Tak Berwujud, Paket LPJ Tahunan Cover/Surat Pengantar/BA/MoU). |
| 2026-08-10 | P4.3 Suite E2E Playwright menyeluruh (47 Page Tests + Smoke UI). P4.4 Suite Backend PHPUnit komprehensif. |
| 2026-08-11 | P4.2 Redesain halaman Landing Page & Login (hero profesional, FAQ accordion interaktif, smooth scroll, CTA Masuk/Mulai Sekarang terpisah, tanpa bocor versi teknis). |
| 2026-08-11 | P4.3 Suite E2E Playwright Interactive CRUD (25 tests): form fill, radio select, SmartSelect dropdown, toggle switch, submit data, edit, search, tab switching, dan wrong credentials test. |
| 2026-08-18 | **Fitur Edit Jurnal** — Reverse jurnal lama + buat jurnal baru dalam satu DB transaction atomik (`JournalEditService` outer transaction dengan retry). Hanya untuk `source_type=manual` & `asset_purchase`. Alasan wajib (max 500 char). Untuk `asset_purchase`: asset baru terdaftar otomatis, asset lama tetap terkait jurnal lama (audit trail utuh). Lihat `docs/audit/2026-08-18/journal-edit-feature.md`. |
