# Roadmap & Spesifikasi Migrasi Laporan SIMAK Legacy ke Sistem Modern (Akubumdes)

**Status:** ✅ **100% IMPLEMENTED (Seluruh Laporan Akuntansi SIMAK Selesai Di-migrasi ke Arsitektur Modern)**

Seluruh laporan akuntansi **SIMAK (Sistem Informasi Manajemen Akuntansi Keuangan BUMDes, `ab1-team/simak`)** kini telah di-implementasikan secara penuh pada arsitektur modern Akubumdes (Multi-tenant, Clean Domain Service, Inertia Vue 3, Tailwind CSS, dan PDF Rendering Engine berstandar SAK Entitas Privat / PP No. 11/2021). Ruang lingkup migrasi adalah **modul akuntansi SIMAK** — 9 laporan core, CALK, paket LPJ tahunan, dan rekap aset. Modul pinjaman/lending (SIDBM) tidak termasuk dalam ruang lingkup Akubumdes.

---

## Matriks Komprehensif Laporan

### A. Laporan Keuangan Pokok & Akuntansi SIMAK (SAK EP)
| No | Laporan | File Legacy | File Modern (PDF) | Endpoint Web & PDF | Status |
|---|---|---|---|---|:---:|
| 1 | **Neraca (Balance Sheet)** | `neraca/neraca1.blade.php`, `neraca2.blade.php` | `reports/pdf/balance_sheet.blade.php` | `/accounting/reports/balance-sheet` | ✅ Selesai |
| 2 | **Laba Rugi (Income Statement)** | `view/laba_rugi.blade.php` | `reports/pdf/income_statement.blade.php` | `/accounting/reports/income-statement` | ✅ Selesai; varian `standard`, `trading`, dan `cooperative` mengikuti `tenants.coa_variant` |
| 3 | **Arus Kas (Cash Flow)** | `view/arus_kas.blade.php` | `reports/pdf/cash_flow.blade.php` | `/accounting/reports/cash-flow` | ✅ Selesai |
| 4 | **Perubahan Ekuitas (Equity Change)** | `view/perubahan_modal.blade.php` | `reports/pdf/equity_change.blade.php` | `/accounting/reports/equity-change` | ✅ Selesai |
| 5 | **CALK (Catatan Atas Lap. Keuangan)** | `view/calk.blade.php`, `calk_c.blade.php` | `reports/pdf/calk.blade.php` | `/accounting/reports/calk` | ✅ Selesai |
| 6 | **Neraca Saldo (Trial Balance)** | `view/neraca_saldo.blade.php` | `reports/pdf/trial_balance.blade.php` | `/accounting/reports/trial-balance` | ✅ Selesai |
| 7 | **Buku Besar (General Ledger)** | `view/buku_besar.blade.php` | `reports/pdf/general_ledger.blade.php` | `/accounting/reports/general-ledger` | ✅ Selesai |
| 8 | **Jurnal Transaksi (Journal Listing)** | `view/jurnal_transaksi.blade.php` | `reports/pdf/journal.blade.php` | `/accounting/reports/journals` | ✅ Selesai |
| 9 | **Bukti Kas (BKM, BKK, BM)** | `view/bukti_kas.blade.php` | `reports/pdf/cash_evidence/*` | `/accounting/journals/{id}/cash-evidence` | ✅ Selesai |

---

### B. Analisis Kinerja Keuangan & Rekapitulasi Aset
| No | Laporan | File Legacy | File Modern (PDF) | Endpoint Web & PDF | Status |
|---|---|---|---|---|:---:|
| 1 | **Penilaian Tingkat Kesehatan Usaha** | `view/penilaian_kesehatan.blade.php` | `reports/pdf/penilaian_kesehatan.blade.php` | `/accounting/reports/financial-health` | ✅ Selesai |
| 2 | **Rekapitulasi Aset Tetap** | `view/aset_tetap.blade.php` | `reports/pdf/assets/fixed_assets.blade.php` | `/accounting/reports/assets/fixed/pdf` | ✅ Selesai |
| 3 | **Rekapitulasi Aset Tak Berwujud** | `view/aset_tak_berwujud.blade.php` | `reports/pdf/assets/intangible_assets.blade.php` | `/accounting/reports/assets/intangible/pdf` | ✅ Selesai |

### C. Dokumen Paket Pelaporan Tahunan & Administratif (LPJ)
| No | Dokumen Administratif | File Legacy | File Modern (PDF) | Endpoint Web & PDF | Status |
|---|---|---|---|---|:---:|
| 1 | **Cover Buku Laporan Tahunan** | `view/cover.blade.php` | `reports/pdf/annual/cover.blade.php` | `/accounting/reports/annual-pack/cover/pdf` | ✅ Selesai |
| 2 | **Surat Pengantar Laporan (LPJ)** | `view/surat_pengantar.blade.php` | `reports/pdf/annual/surat_pengantar.blade.php` | `/accounting/reports/annual-pack/surat-pengantar/pdf` | ✅ Selesai |
| 3 | **Berita Acara Pengesahan (LPJ)** | `view/ba_pergantian_laporan.blade.php` | `reports/pdf/annual/ba_pergantian.blade.php` | `/accounting/reports/annual-pack/ba-pergantian/pdf` | ✅ Selesai |
| 4 | **Naskah Kerjasama Antar Desa (MoU)** | `view/mou.blade.php` | `reports/pdf/annual/mou.blade.php` | `/accounting/reports/annual-pack/mou/pdf` | ✅ Selesai |
| 5 | **Hub Dokumen LPJ & Cover** | `view/index.blade.php` | `Accounting/Reports/AnnualPack.vue` | `/accounting/reports/annual-pack` | ✅ Selesai |

---

### D. Catatan Kaki Ruang Lingkup
- Dokumen perjanjian/akad pinjaman (SPK, kuitansi angsuran, kartu pinjaman, dsb.) merupakan modul **SIDBM (aplikasi pinjaman/lending UPK)** yang **bukan** bagian dari SIMAK dan **bukan** bagian dari Akubumdes. Modul lending telah dihapus dari ruang lingkup produk; dokumen cetak Akubumdes terbatas pada bukti kas, laporan keuangan, dan paket LPJ tahunan di atas.

---

*Dokumen ini menjadi checklist resmi paritas laporan akuntansi SIMAK → Akubumdes.*
