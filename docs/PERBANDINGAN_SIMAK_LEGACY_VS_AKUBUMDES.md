# Dokumen Analisis Perbandingan Komprehensif: SIMAK (Legacy) vs Akubumdes

**Dokumen ID**: docs/PERBANDINGAN_SIMAK_LEGACY_VS_AKUBUMDES.md
**Tanggal**: 10 Agustus 2026 (diperbarui 17 September 2026)
**Penulis**: Team Engineering & System Architecture
**Status**: Dokumentasi Resmi Transformasi Arsitektur

---

## 0. Pernyataan Positioning Resmi

> **Akubumdes** (`ab1-team/akubumdes`) adalah **modern upgrade & re-engineering dari SIMAK** — **Sistem Informasi Manajemen Akuntansi Keuangan BUMDes** (`ab1-team/simak`).
>
> Akubumdes **BUKAN** derivatif dari SIDBM (aplikasi pinjaman/lending UPK) dan tidak mewarisi modul lending. Seluruh narasi, perbandingan, dan pemetaan pada dokumen ini mengacu pada **SIMAK (Legacy)** sebagai satu-satunya basis aplikasi dan basis data yang dire-engineering.

Domain yang diwarisi dari SIMAK dan menjadi ruang lingkup modernisasi ini adalah **akuntansi dan keuangan BUMDes / BUMDesma**:

- Bagan akun (Chart of Accounts) 3 varian usaha: **standar (jasa/umum)**, **perdagangan/trading (dengan HPP)**, dan **koperasi**.
- Transaksi akuntansi, jurnal, buku besar, neraca, laba rugi, arus kas, perubahan ekuitas, CALK, dan paket LPJ tahunan.
- Penatausahaan saldo dan aset tetap per unit usaha BUMDes.

---

## 1. Ringkasan Eksekutif

**SIMAK (Sistem Informasi Manajemen Akuntansi Keuangan BUMDes, `ab1-team/simak`)** adalah aplikasi akuntansi keuangan BUMDes yang menjadi tulang punggung operasional pembukuan unit usaha BUMDes / BUMDesma selama bertahun-tahun. Aplikasi ini menyimpan satu basis data monolitik berisi tabel dinamis per unit usaha (`usaha`, `rekening_{usaha_id}`, `accounts_{usaha_id}`, `transaksi_{usaha_id}`, `saldo_{usaha_id}`), sehingga setiap unit usaha baru menambah ratusan tabel dengan struktur yang serupa namun tidak identik.

Seiring pertumbuhan jumlah unit usaha, kebutuhan pengawasan tingkat Kabupaten, dan tuntutan standar keandalan perangkat lunak modern, arsitektur SIMAK (legacy) menghadapi batas kemampuan teknis (*technical ceiling*) yang menghambat skalabilitas, keamanan, dan pemeliharaan kode.

**Akubumdes** dibangun sebagai **re-engineering arsitektur menyeluruh (*ground-up architectural rewrite*)** dari SIMAK untuk menyelesaikan seluruh hambatan tersebut tanpa menghilangkan data historis, kode akun, nomor transaksi, maupun alur akuntansi inti. Basis data SIMAK tetap menjadi sumber migrasi (cutover) yang resmi.

---

## 2. Alasan Utama Perlunya Upgrade (The "Why")

Mengapa pembaruan dari SIMAK ke Akubumdes **wajib** dilakukan dan tidak cukup hanya dengan menambal kode legacy?

### 2.1 Hambatan Utama pada SIMAK (Legacy)

1. **Schema Drift & Tabel Dinamis Per-Unit Usaha (`transaksi_1`, `transaksi_4`, `rekening_1`, `accounts_2`, ...)**
   Pada SIMAK, setiap unit usaha (`usaha`) memicu pembuatan tabel baru dengan suffix angka ID usaha. Ketika terdapat ratusan unit usaha, database memiliki **puluhan ribu tabel**. Mengubah struktur kolom (*migration*) mengharuskan eksekusi perintah SQL ke ribuan tabel satu per satu, yang sangat rawan memicu ketidakseragaman skema (*schema drift*) dan kegagalan migrasi pertengahan.
2. **Ketiadaan Foreign Key & Integritas Data Rentan**
   SIMAK tidak menggunakan *Foreign Key Constraints* pada level database. Relasi antar data hanya dijaga pada level aplikasi atau trigger. Hal ini sering mengakibatkan *orphan records* (data master terhapus tetapi jurnal transaksi tetap ada, atau sebaliknya).
3. **Penghitungan Saldo Berbasis Trigger & Teks (VARCHAR Uang)**
   Nilai angka finansial pada beberapa tabel SIMAK disimpan dalam format teks string (VARCHAR). Penghitungan saldo akun dipelihara melalui *MySQL Triggers* yang kompleks. Jika terjadi koreksi transaksi historis, trigger sering memicu penguncian tabel (*table lock*) dan inkonsistensi saldo.
4. **Pemisahan COA per Varian Usaha Menjadi Skema Terpisah**
   SIMAK memisahkan struktur akun berdasarkan varian unit usaha: `akun_level_1` / `akun_level_2` / `rekening_{n}` untuk BUMDes standar, `akun_level_1s` / `akun_level_2s` / `accounts_{n}` untuk unit perdagangan/trading, dan varian koperasi tersendiri. Setiap varian harus dirawat sebagai cabang skema tersendiri.
5. **Performa Agregasi & Keterbatasan Portal Kabupaten Legacy**
   Modul konsolidasi SIMAK terkendala performa agregasi yang lambat karena harus meloop puluhan tabel dinamis per unit usaha secara *runtime*, serta keterbatasan visualisasi interaktif dan laporan konsolidasi mendalam (seperti CALK konsolidasi kabupaten).
6. **Proses Bisnis Manual (Tidak Ada Pembayaran Online & Billing Automation)**
   Tagihan biaya langganan aplikasi atau pencatatan pembayaran masih dilakukan secara manual tanpa integrasi Payment Gateway, tanpa penanganan otomatis untuk tenant yang menunggak (*overdue*).
7. **Keterbatasan Pengujian Automated & Risiko Human Error**
   SIMAK tidak memiliki *automated test suite* (unit test / feature test / E2E test). Setiap perubahan kode harus diuji manual satu per satu pada ratusan halaman Blade + jQuery.

---

## 3. Perbandingan Arsitektur & Teknologi Core

| Komponen | SIMAK (Legacy, `ab1-team/simak`) | Akubumdes (`ab1-team/akubumdes`) | Keuntungan & Implikasi Akubumdes |
|---|---|---|---|
| **Framework Backend** | PHP 8.1 / Laravel 10.x | **PHP 8.4 / Laravel 13.x** | Menggunakan fitur PHP/Laravel terbaru (Attribute, Enums, peningkatan performa, modern container injection). |
| **Arsitektur Frontend** | Monolithic Blade Views + jQuery + DataTables (server-side rendering tradisional) | **Single Page Application (SPA) via Inertia.js 2.0 + Vue 3.5 + Tailwind CSS 4 + Vite 7** | Pengalaman pengguna instan tanpa reload halaman, komponen UI modular yang konsisten, proses build & HMR kilat via Vite 7. |
| **Model Tenancy** | Single database monolitik, dynamic table name suffix per unit usaha (`tabel_{usaha_id}`) | **Platform DB + Shared/Dedicated Shard Databases (isolasi berbasis kolom `tenant_id`)** | Skalabilitas hingga ribuan unit usaha. Skema tabel seragam per shard. Mengeliminasi puluhan ribu tabel dinamis. |
| **Pembukuan Akuntansi** | Flat 1 baris per transaksi (`rekening_debit` + `rekening_kredit` horizontal); koreksi langsung pada baris | **Immutable Double-Entry Ledger: `journal_entries` + `journal_lines`** | Standar SAK, mendukung jurnal multi-baris (split ledger), reversal + recreate atomik, audit trail mutlak. |
| **Chart of Accounts** | 3 set skema terpisah per varian (`akun_level_1`, `akun_level_1s`, varian koperasi) + `rekening_{n}` / `accounts_{n}` | **Satu tabel rekursif `accounts` dengan atribut `coa_variant` (standard / trading / cooperative)** | Satu skema untuk 3 varian usaha (jasa, perdagangan dengan HPP, koperasi); kedalaman pohon akun tak terbatas. |
| **Mesin Cache & State** | File Cache / Basic Store | **Redis 8 (predis) — Cache, Session & Queue Worker** | Performa I/O super cepat untuk Session login, caching laporan, dan pemrosesan antrean latar belakang. |
| **Kecerdasan Buatan (AI)** | Tidak ada | **Embedded `enpii/assistant` + Vector Store (PostgreSQL pgvector) + Local Ollama LLM** | RAG internal untuk query dokumen SOP dan panduan akuntansi, analisis jurnal via AI, dan interaksi chat dengan widget reaktif. |
| **Pengetesan (Testing)** | Manual Testing | **PHPUnit (Unit & Feature Tests) + Playwright (End-to-End E2E Tests)** | Menjamin keamanan kode, regresi terkendali, dan kualitas rilis berkelanjutan. |

---

## 4. Transformasi Arsitektur Database

Detail lengkap pemetaan tabel dan transformasi kolom dari SIMAK ke Akubumdes tersedia pada dokumen
[PERBANDINGAN_DATABASE_SIMAK_VS_AKUBUMDES.md](PERBANDINGAN_DATABASE_SIMAK_VS_AKUBUMDES.md).

Ringkasan transformasi inti:

| Aspek | SIMAK (Legacy) | Akubumdes |
|---|---|---|
| Unit usaha | `usaha` (1 baris = 1 unit usaha, tabelnya sendiri) | `tenants` (platform) + `tenant_registry` (shard) |
| Bagan akun | `akun_level_1`, `akun_level_2`, `rekening_{n}`, `accounts_{n}` | `accounts` (rekursif, `coa_variant`) |
| Transaksi | `transaksi_{n}` (flat, debit/kredit horizontal) | `journal_entries` + `journal_lines` |
| Saldo | `saldo_{n}` (dipelihara trigger) | `account_monthly_balances` (projection, dapat dihitung ulang) |
| Tipe data uang | VARCHAR / string uang | `DECIMAL(19, 2)` |
| Integritas | Tidak ada FK | Composite FK `[tenant_id, parent_row_id]` |

---

## 5. Fitur Baru: Modernisasi Modul Akuntansi SIMAK

Akubumdes mempertahankan seluruh ruang lingkup akuntansi SIMAK dan menaikkannya ke standar modern:

1. **9 Laporan Akuntansi Core**: Neraca, Laba Rugi, Buku Besar, Arus Kas, Perubahan Ekuitas, CALK, Neraca Saldo, Jurnal Transaksi, dan Bukti Kas (BKM / BKK / BM).
2. **3 Varian Laporan Sesuai Usaha**: Neraca dan Laba Rugi otomatis menyesuaikan `coa_variant` — termasuk Harga Perolehan Pokok (HPP) untuk unit usaha perdagangan/trading dan format koperasi.
3. **Paket LPJ Tahunan**: Cover Buku LPJ, Surat Pengantar, Berita Acara Pengesahan, MoU Kerjasama Antar Desa, dan Annual LPJ Pack Hub.
4. **Koreksi Jurnal Immutable**: Reverse + recreate atomik (`JournalEditService`) — bukan lagi edit langsung baris transaksi seperti SIMAK.
5. **Aset Tetap & Inventaris**: Register aset, nilai buku, dan penyusutan terintegrasi langsung dengan jurnal pembelian.
6. **E-Budgeting (RAPB)**: Input anggaran per akun per bulan dengan monitoring realisasi vs anggaran.
7. **Tutup Buku & Alokasi Laba**: Tutup/buka periode fiskal dan jurnal alokasi surplus otomatis.

---

## 6. Infrastruktur & Performa Sistem

| Item Infrastruktur | SIMAK (Legacy) | Akubumdes |
|---|---|---|
| **Koneksi Database** | Single MySQL connection | Dynamic Shard Connection Manager (`ShardConnectionManager`) |
| **Cache Engine** | File-based Cache | **Redis 8 Cache (`CACHE_STORE=redis`)** |
| **Session Handling** | File-based Session | **Redis 8 Session Driver (`SESSION_DRIVER=redis`)** |
| **Queue & Background Jobs** | Synchronous Execution | **Dedicated Redis Queue Worker (`QUEUE_CONNECTION=redis`)** |
| **Search Engine** | Query SQL LIKE parsial per tabel dinamis | **Omnibox Global Search (`GlobalSearchService`)** menembus Jurnal, Akun, Aset & Master Data |
| **UI Components** | HTML Select / Inputs bawaan browser | **Reusable Component Suite** (SmartSelect, ReportPeriodFilter, AppRadioGroup, AppDatePicker, AppCard, AppBadge, AppModal, AppToast) |
| **Konsolidasi Multi-Unit** | Loop UNION tabel dinamis per unit usaha | **API Holding & Konsolidasi** lintas tenant (Neraca, LR, Arus Kas, CALK, paket 5-in-1) |

---

## 7. SaaS Platform & Automasi Bisnis Modern

SIMAK adalah aplikasi *on-premise* per instansi. Akubumdes menambahkan lapisan platform SaaS di atas modul akuntansi yang diwarisinya:

1. **Multi-Tenant Sharding**: Satu platform database (`akubumdes_platform`) + banyak shard database (`akubumdes_shard_*`) dengan skema identik.
2. **SaaS Billing Otomatis**: Auto-invoice scheduler (`subscriptions:generate-invoices`), integrasi Multi-Payment Gateway (Tripay, Duitku, Xendit) untuk QRIS, Virtual Account, dan Kartu Kredit, serta *Active Gateway Switcher` dari Superadmin.
3. **Overdue Suspension**: Middleware `EnsureSubscriptionActive` menangguhkan tenant menunggak dan mengarahkannya langsung ke halaman pembayaran.
4. **Perpanjangan Langganan Real-Time**: Webhook callback (HMAC/MD5 per gateway) memperpanjang masa aktif subscription otomatis (1 bulan / 1 tahun sesuai paket).
5. **Portal Supervisi Berjenjang**: Dashboard & laporan konsolidasi real-time tingkat Kabupaten (`/regency`) dan Provinsi (`/province`).

---

## 8. Fitur Baru: Asisten AI Interaktif & Vector RAG (`enpii/assistant`)

Akubumdes mengintegrasikan asisten cerdas internal **Ariel** yang bertindak sebagai *pair assistant* pengguna:

1. **Vector Store RAG (PostgreSQL pgvector)**:
   Membaca dan mencari dokumen SOP, regulasi keuangan desa (PP No. 11/2021, SAK EP), dan panduan akuntansi menggunakan *Cosine Similarity* sub-milidetik pada indeks HNSW.
2. **Local LLM & Embedding Server (Ollama)**:
   Menggunakan model embedding `nomic-embed-text` untuk mengonversi dokumen menjadi vector tanpa mengirim data sensitif keuangan ke layanan pihak ketiga.
3. **Komponen Chat Interaktif (Vue Components)**:
   Mendukung balasan AI berupa **Markdown Tables**, **Interactive Artifacts**, **Tombol Aksi**, dan **Polls (Survei interaktif gaya WhatsApp)**.
4. **Integrasi Domain Tools**:
   Asisten AI dapat mengeksekusi pencarian data akun, jurnal, aset, hingga draft jurnal atas izin pengguna (`permissions.tool_map`).

---

## 9. Matriks Perbandingan Fitur Samping-demi-Samping (Head-to-Head)

| Fitur / Kemampuan | SIMAK (Legacy) | Akubumdes |
|---|---|---|
| **Multi-Tenant Sharding** | ❌ Tidak (tabel dinamis suffix per unit usaha) | ✅ Ya (Platform DB + Tenant Shards) |
| **Pencegahan Schema Drift** | ❌ Tidak (perlu run SQL per tabel) | ✅ Ya (migration berjalan per Shard DB) |
| **3 Varian COA (Standard / Trading HPP / Koperasi)** | ⚠️ Terpisah dalam skema berbeda | ✅ Ya (satu tabel `accounts` + `coa_variant`) |
| **Immutable Double-Entry Ledger** | ❌ Flat row debit/kredit | ✅ Ya (`journal_entries` + `journal_lines`) |
| **Pencetakan Bukti Kas / PDF** | ⚠️ Terbatas | ✅ Ya (BKM/BKK/BM + laporan PDF langsung dari posting jurnal) |
| **Reversal / Koreksi Jurnal** | ⚠️ Hapus / edit manual | ✅ Ya (jurnal pembalik otomatis & audit trail) |
| **Laporan Rencana vs Realisasi (Anggaran)** | ⚠️ Manual Excel | ✅ Ya (E-Budgeting terintegrasi) |
| **Tutup Buku & Alokasi Laba** | ⚠️ Manual via script | ✅ Ya (otomatis dengan jurnal alokasi laba) |
| **Manajemen Aset Tetap / Inventaris** | ⚠️ Terpisah | ✅ Ya (register aset, nilai buku, penyusutan) |
| **Dashboard Supervisi Kabupaten** | ⚠️ Ada (terbatas & lambat) | ✅ Ya (modern SPA, performa tinggi, real-time) |
| **Laporan Konsolidasi Kabupaten (PDF)** | ⚠️ Ada (terbatas: Neraca, LR, Arus Kas) | ✅ Ya (lengkap: Neraca, LR, BB, Arus Kas, CALK kabupaten) |
| **API Holding & Konsolidasi Multi-Tenant** | ❌ Tidak ada | ✅ Ya (endpoint RESTful laporan keuangan terpadu) |
| **Integrasi Payment Gateway** | ❌ Tidak ada | ✅ Ya (Tripay, Duitku, Xendit — QRIS & Virtual Account) |
| **Otomatisasi Billing & Overdue Lock** | ❌ Tidak ada | ✅ Ya (scheduler invoice & suspension middleware) |
| **Asisten AI (RAG & Chat Tools)** | ❌ Tidak ada | ✅ Ya (pgvector + Ollama + interactive chat widgets) |
| **Global Omnibox Search Header** | ❌ Tidak ada | ✅ Ya (cari akun, jurnal, aset, master data) |
| **Background Processing (Queues)** | ❌ Sync only | ✅ Ya (Redis Queue Worker container) |
| **Automated Test Coverage** | ❌ Tidak ada | ✅ Ya (PHPUnit + Playwright E2E) |

---

## 10. Kesimpulan & Rekomendasi

Pembaruan dari **SIMAK (Legacy)** ke **Akubumdes** bukan sekadar pembaruan tampilan (*facelift*), melainkan **modernisasi total arsitektur sistem informasi akuntansi keuangan BUMDes** — dari basis data dinamis per unit usaha menjadi platform cloud SaaS multi-tenant sharding dengan immutable double-entry general ledger.

### Rekomendasi Langkah Selanjutnya:

1. **Lakukan Cutover Data Pilot**: Gunakan Artisan orchestrator (`php artisan legacy:cutover-tenant local 1`) untuk menguji migrasi data unit usaha dari basis data SIMAK ke Akubumdes sesuai [CUTOVER_RUNBOOK.md](CUTOVER_RUNBOOK.md).
2. **Sosialisasi Portal Kabupaten**: Aktifkan akun supervisor kabupaten (`is_regency_user = true`) agar pihak pengawas dapat langsung memantau laporan konsolidasi keuangan seluruh kecamatan.
3. **Pengaktifan Payment Gateway Produksi**: Masukkan kredensial gateway produksi pada `.env` untuk mengaktifkan penerimaan pembayaran langganan secara otomatis.
4. **Verifikasi Paritas Laporan SIMAK**: Gunakan [LEGACY_REPORTS_MIGRATION_ROADMAP.md](LEGACY_REPORTS_MIGRATION_ROADMAP.md) sebagai checklist paritas 9 laporan akuntansi core, CALK, paket LPJ tahunan, dan rekap aset.

Dokumen ini menjadi acuan resmi mengenai keputusan teknis, arsitektur, dan keunggulan Akubumdes dibanding SIMAK (Legacy).
