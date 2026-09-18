# Perbandingan Database SIMAK (Legacy) vs Akubumdes

Dokumen ini berisi panduan komparasi arsitektur database, pemetaan tabel (*table mapping*), transformasi kolom (*column transformation*), dan relasi data antara **SIMAK (Legacy — Sistem Informasi Manajemen Akuntansi Keuangan BUMDes, `ab1-team/simak`)** dan **Akubumdes** (`ab1-team/akubumdes`, arsitektur multi-tenant modern dengan database platform dan sharding).

> **Catatan positioning**: Akubumdes adalah modern upgrade & re-engineering dari basis data SIMAK. Dokumen ini memetakan **hanya** skema akuntansi dan keuangan BUMDes dari SIMAK. Modul lending / pinjaman kelompok (SIDBM, aplikasi UPK) bukan bagian dari SIMAK dan tidak menjadi bagian dari ruang lingkup migrasi ini.

---

## 1. Ringkasan Perubahan Paradigma Arsitektur

| Aspek | SIMAK (Legacy) | Akubumdes (`akubumdes`) | Rationale & Dampak |
|---|---|---|---|
| **Model Tenancy** | **Tabel Dinamis Per-Unit Usaha**<br>`usaha` sebagai master unit, lalu `transaksi_{usaha_id}`, `rekening_{usaha_id}`, `accounts_{usaha_id}`, `saldo_{usaha_id}` dalam 1 database tunggal. | **Platform DB + Shared/Dedicated Shard DB**<br>Semua tabel tenant distandarisasi dan diisolasi dengan kolom `tenant_id` pada database shard. | Menghilangkan puluhan ribu tabel dinamis, mencegah *schema drift*, dan mempermudah migrasi struktur database. |
| **Integritas Relasional** | **Tidak ada Foreign Key (FK)**.<br>Relasi hanya dijaga pada level aplikasi atau trigger MySQL. | **Foreign Key Constraint Ketat**.<br>Menggunakan *composite foreign key* `[tenant_id, parent_row_id]` untuk menjamin data tidak bocor antar-tenant. | Menghilangkan *orphan records*, inkonsistensi transaksi, dan *ghost data*. |
| **Pencatatan Akuntansi** | **Format Flat 1 Baris** (`transaksi_{usaha_id}`).<br>Satu baris memuat `rekening_debit` dan `rekening_kredit` sekaligus. | **Double-Entry Ledger Murni**.<br>Header `journal_entries` + banyak baris `journal_lines` (debit/kredit terpisah). | Memenuhi standar akuntansi (SAK EP), mendukung multi-baris jurnal (split ledger), dan audit trail mutlak. |
| **Chart of Accounts** | **3 set skema terpisah per varian usaha** (`usaha.jenis_akun`):<br>• standar jasa/umum → `akun_level_1`, `akun_level_2`, `rekening_{n}`<br>• perdagangan/trading → `akun_level_1s`, `akun_level_2s`, `accounts_{n}`<br>• koperasi → varian koperasi. | **Satu tabel rekursif `accounts`** dengan atribut `coa_variant` (`standard` / `trading` / `cooperative`) pada level tenant. | Satu skema untuk 3 varian usaha; kedalaman pohon akun tak terbatas; varian trading mendukung HPP. |
| **Format Angka / Saldo** | **VARCHAR / String Uang**.<br>Format teks seperti `"1.500.000,00"` atau string tanpa desimal pasti. | **DECIMAL(19, 2) / Strict Precision**.<br>Format numerik presisi tinggi untuk perhitungan finansial. | Menghindari *floating point error* dan kegagalan agregasi matematis `SUM()` di database. |
| **Penghitungan Saldo** | **MySQL Triggers** pada tabel `saldo_{usaha_id}`.<br>Rawan deadlock dan penguncian tabel. | **Application Ledger Engine & Snapshots** (`account_monthly_balances`). | Mengurangi beban lock MySQL, performa I/O jauh lebih stabil, dan mudah diverifikasi ulang (*reconcile*). |
| **Identitas Record** | `id` auto-increment murni per tabel dinamis; rawan bentrok saat unit usaha digabung. | **Tri-Identity System**:<br>1. `row_id`: PK internal fisik shard.<br>2. `id`: nomor urut sekuensial per tenant (mempertahankan ID SIMAK).<br>3. `public_id`: ULID/UUID 26-char unik global untuk API/URL. | Keamanan data lebih kuat (ID tidak mudah ditebak) dan data aman saat migrasi antar-shard. |

---

## 2. Tabel Pemetaan Lengkap (Master Table Mapping)

Tabel berikut memetakan setiap tabel SIMAK (Legacy) ke entitas tabel pada Akubumdes, beserta penempatan basis datanya (**Platform DB** atau **Shard DB**):

| # | Tabel SIMAK (Legacy) | Tabel Target Akubumdes (`akubumdes`) | Lokasi DB | Kategori / Keterangan Transformasi |
|---|---|---|---|---|
| **A** | **Kelembagaan, Tenant & Wilayah** | | | |
| 1 | `usaha` | `tenants` | `Platform DB` | Master unit usaha BUMDes di tingkat platform SaaS (kode wilayah, nama, varian CoA, status langganan). Pada SIMAK, 1 baris `usaha` = 1 unit usaha dengan ratusan tabelnya sendiri. |
| 2 | `usaha` (replika lokal) | `tenant_registry` | `Shard DB` | Registry sinkronisasi tenant di dalam shard database yang bersangkutan. |
| 3 | `desa` | `organization_units` / `village_namings` | `Shard DB` | Desa/Kelurahan dikonversi menjadi unit organisasi tingkat desa beserta sebutan resminya. |
| 4 | `sebutan_desa` | `village_namings` | `Shard DB` | Konfigurasi sebutan kepala desa, badan permusyawaratan, dsb. per tenant. |
| 5 | `kabupaten` | `tenants` (`regency_code`) / `Platform Settings` | `Platform DB` | Menjadi atribut kode kabupaten pada tenant dan modul supervisi platform. |
| 6 | `kecamatan` | `tenants` (`district_code`) / `Platform Settings` | `Platform DB` | Kode kecamatan sebagai bagian dari identitas tenant untuk konsolidasi wilayah. |
| 7 | `wilayah` | Dikelola via Master Referensi Regional API / Enums | `App / Shard` | Dinormalisasi menggunakan standarisasi kode Kemendagri / BPS. |
| **B** | **Pengguna, Autentikasi & Otorisasi** | | | |
| 8 | `user` | `users` + `tenant_memberships` | `Platform DB` | Pengguna tenant dimigrasi ke master `users` platform terpadu dengan relasi `tenant_memberships`. |
| 9 | `admin_users` | `users` (role: `superadmin` / `district_admin`) | `Platform DB` | User administrator pusat dan kabupaten disatukan dalam tabel `users` berstatus hak akses khusus. |
| 10 | `level` | `roles` | `Shard DB` | Master tingkatan otorisasi (Manager, Akuntan, Kasir, Verifikator, dsb). |
| 11 | `jabatan` & `personalia` | `organization_units` / `user_roles` | `Shard DB` | Posisi jabatan struktural pengelola BUMDes / BUMDesma. |
| 12 | `user_token` | Laravel Sanctum / Redis Tokens | `Redis / Platform` | Token autentikasi modern berbasis framework. |
| **C** | **Chart of Accounts (Bagan Akun)** | | | |
| 13 | `akun_level_1` (standar jasa/umum) | `accounts` (`coa_variant = standard`, `level = 1`) | `Shard DB` | Kelompok akun level 1 (Aset, Kewajiban, Ekuitas, Pendapatan, Beban). |
| 14 | `akun_level_2` (standar jasa/umum) | `accounts` (`coa_variant = standard`, `level = 2`) | `Shard DB` | Sub-kelompok akun level 2. |
| 15 | `akun_level_1s` (perdagangan/trading) | `accounts` (`coa_variant = trading`, `level = 1`) | `Shard DB` | Varian trading, termasuk akun HPP dan persediaan barang dagang. |
| 16 | `akun_level_2s` (perdagangan/trading) | `accounts` (`coa_variant = trading`, `level = 2`) | `Shard DB` | Sub-kelompok akun level 2 varian trading. |
| 17 | varian koperasi (`akun_level_1_koperasi`, `akun_level_2_koperasi`) | `accounts` (`coa_variant = cooperative`) | `Shard DB` | Varian koperasi (simpanan pokok/luar biasa, jasa usaha, SHU). |
| 18 | `rekening_{usaha_id}` (standar & koperasi) | `accounts` (`is_postable = true`) | `Shard DB` | Akun transaksi detail (postable) varian standard/cooperative. |
| 19 | `accounts_{usaha_id}` (perdagangan/trading) | `accounts` (`is_postable = true`, `coa_variant = trading`) | `Shard DB` | Akun transaksi detail (postable) varian trading. |
| **D** | **Transaksi & Jurnal Akuntansi** | | | |
| 20 | `transaksi_{usaha_id}` | `journal_entries` + `journal_lines` | `Shard DB` | Lihat §3.2 — setiap baris flat dipecah menjadi 1 header jurnal + minimal 2 baris jurnal berpasangan. |
| 21 | `saldo_{usaha_id}` | `account_monthly_balances` | `Shard DB` | Saldo periodik menjadi *projection* yang dapat dihitung ulang, bukan sumber kebenaran utama. |
| 22 | `akun_{usaha_id}` (master akun lokal) | `accounts` + `legacy_record_mappings` | `Shard DB` | Pemetaan kode akun lama → `accounts.code` dipertahankan untuk paritas laporan. |
| **E** | **Aset Tetap & Inventaris** | | | |
| 23 | `aset_{usaha_id}` / `inventaris_{usaha_id}` | `assets` | `Shard DB` | Register aset tetap & tak berwujud, nilai buku, akumulasi penyusutan. |
| **F** | **Anggaran & Pelaporan** | | | |
| 24 | `anggaran_{usaha_id}` / `rapb_{usaha_id}` | `budget_lines` | `Shard DB` | Anggaran per akun per periode (E-Budgeting / RAPB). |
| 25 | `calk_{usaha_id}` (catatan laporan) | `calk_notes` (rich text per periode) | `Shard DB` | Catatan atas laporan keuangan dengan editor narasi. |
| **G** | **Utilitas & Audit** | | | |
| 26 | *(tidak ada di legacy)* | `ai_knowledge_sources` & `ai_document_chunks` | `Shard DB / Vector DB` | Basis pengetahuan SOP & panduan akuntansi BUMDes untuk RAG (Retrieval-Augmented Generation). |
| 27 | *(tidak ada di legacy)* | `legacy_record_mappings` | `Shard DB` | Tabel pemetaan ID record SIMAK lama → ID Akubumdes untuk migrasi & rekonsiliasi. |

---

## 3. Detail Transformasi Kolom pada Modul Kunci

Berikut adalah perbandingan struktur kolom secara mendalam untuk modul akuntansi yang paling vital.

### 3.1 Modul Master Akun & Anggota: SIMAK → Akubumdes

Pada SIMAK, data nama/kontak pengelola dan pemanfaat usaha bercampur dalam tabel dinamis per unit usaha. Pada Akubumdes, entitas orang (`people`) dipisahkan dari entitas keanggotaan tenant (`members`).

```
+------------------------------------+
|      SIMAK (master per usaha)      |
+------------------------------------+
| id, nik, namadepan                 | -----> +------------------------------------+
| jk, tempat_lahir, tgl_lahir        |        |        AKUBUMDES (people)          |
| hp, foto, status                   |        +------------------------------------+
| desa, alamat                       |        | row_id, public_id, tenant_id       |
| usaha, penghasilan                 |        | national_identity_number (NIK)     |
+------------------------------------+        | full_name, gender, birth_place     |
                                              | birth_date, phone, photo_path      |
                                              +------------------------------------+
                                                                   | 1
                                      +---------------------------+---------------------------+
                                      | 1                                                     | 1..N
                                      v                                                       v
                       +-------------------------------+                 +-------------------------------+
                       |     AKUBUMDES (members)       |                 |   AKUBUMDES (member_addresses) |
                       +-------------------------------+                 +-------------------------------+
                       | row_id, public_id, tenant_id  |                 | row_id, member_row_id         |
                       | person_row_id                 |                 | street_address, rt, rw        |
                       | member_number (NIA)            |                 | village_code, postal_code     |
                       | status, joined_at             |                 +-------------------------------+
                       +-------------------------------+
```

### 3.2 Modul Akuntansi: `transaksi_{usaha_id}` → `journal_entries` + `journal_lines`

SIMAK menggunakan satu baris per transaksi dengan menyebutkan rekening debit dan rekening kredit secara horizontal. Akubumdes mentransformasikan setiap transaksi menjadi satu header jurnal dan minimal dua baris jurnal (double-entry).

```
SIMAK: transaksi_4 (Flat Row)
+-----+------------+----------------+-----------------+------------+--------------------------+
| idt | tgl_trans  | rekening_debit | rekening_kredit | jumlah     | keterangan               |
+-----+------------+----------------+-----------------+------------+--------------------------+
| 101 | 2026-07-20 | 1.1.01.01      | 4.1.01.01       | 500.000,00 | Penerimaan Jasa Usaha    |
+-----+------------+----------------+-----------------+------------+--------------------------+

                                        │ TRANSFORMASI
                                        ▼
AKUBUMDES: journal_entries (Header)
+--------+-----------+----------------+------------------+--------------------------+--------+
| row_id | tenant_id | journal_number | transaction_date | description              | status |
+--------+-----------+----------------+------------------+--------------------------+--------+
| 5001   | 1         | JRN-202607-001 | 2026-07-20       | Penerimaan Jasa Usaha    | posted |
+--------+-----------+----------------+------------------+--------------------------+--------+
    │
    ├─► AKUBUMDES: journal_lines (Line 1 - Debit)
    │   +--------+------------------+------------+--------------+---------------+
    │   | row_id | journal_entry_id | account_id | debit_amount | credit_amount |
    │   +--------+------------------+------------+--------------+---------------+
    │   | 10001  | 5001             | [Kas Kasir]| 500000.00    | 0.00          |
    │   +--------+------------------+------------+--------------+---------------+
    │
    └─► AKUBUMDES: journal_lines (Line 2 - Credit)
        +--------+------------------+------------+--------------+---------------+
        | row_id | journal_entry_id | account_id | debit_amount | credit_amount |
        +--------+------------------+------------+--------------+---------------+
        | 10002  | 5001             | [Pend Jasa]| 0.00         | 500000.00     |
        +--------+------------------+------------+--------------+---------------+
```

| Kolom SIMAK `transaksi_{usaha_id}` | Tabel & Kolom Target Akubumdes | Tipe Data & Transformasi |
|---|---|---|
| `idt` | `journal_entries.legacy_id` + mapping | Disimpan pada `legacy_record_mappings` dan metadata jurnal. |
| `idtp` | `journal_entries.source_row_id` / reference | ID transaksi induk / pengelompokan batch. |
| `tgl_transaksi` | `journal_entries.transaction_date` | `DATE` standar ISO YYYY-MM-DD. |
| `keterangan` | `journal_entries.description` & `journal_lines.memo` | `TEXT`. |
| `relasi` | `journal_entries.legacy_relation` | Informasi relasi master legacy (mis. aset atau unit usaha terkait). |
| `rekening_debit` | `journal_lines.account_row_id` (Line 1) | FK ke tabel `accounts` berdasarkan kode akun. |
| `rekening_kredit` | `journal_lines.account_row_id` (Line 2) | FK ke tabel `accounts` berdasarkan kode akun. |
| `jumlah` (string) | `journal_lines.debit_amount` / `credit_amount` | Dikonversi dari string/uang ke `DECIMAL(19, 2)`. |
| `user_id` | `journal_entries.created_by_user_id` | `BIGINT` referensi pengguna pembuat jurnal. |

### 3.3 Modul Saldo: `saldo_{usaha_id}` → `account_monthly_balances`

Pada SIMAK, saldo akun dipelihara oleh *MySQL trigger* setiap kali `transaksi_{usaha_id}` berubah. Pada Akubumdes, saldo merupakan *projection* bulanan yang dapat dihitung ulang kapan saja dari `journal_lines`.

| Kolom SIMAK `saldo_{usaha_id}` | Tabel & Kolom Target Akubumdes | Keterangan |
|---|---|---|
| `kode_akun` | `account_monthly_balances.account_row_id` | FK ke `accounts`. |
| `bulan` / `tahun` | `account_monthly_balances.period_year`, `period_month` | Kunci periode fiscal. |
| `saldo_awal` | `account_monthly_balances.opening_balance` | Saldo awal periode (mutable, hasil pemindahan buku). |
| `saldo_akhir` | `account_monthly_balances.closing_balance` | Projection dari `opening + mutasi` — dapat direcompute. |
| *(trigger INSERT/UPDATE/DELETE)* | *(tidak ada — dihitung oleh `AccountBalanceQuery`)* | Penghapusan trigger menghilangkan deadlock & table lock. |

---

## 4. Perbandingan Chart of Accounts (COA) & Hirarki Akun

Pada SIMAK Legacy, struktur akun terbagi berdasarkan varian unit usaha (`usaha.jenis_akun`):
- **BUMDes Standar / Jasa / Umum**: `akun_level_1`, `akun_level_2`, `akun_{lokasi}`, `rekening_{lokasi}`
- **Unit Perdagangan / Trading (dengan HPP)**: `akun_level_1s`, `akun_level_2s`, `akun_{lokasi}`, `accounts_{lokasi}`
- **Koperasi**: `akun_level_1_koperasi`, `akun_level_2_koperasi`, `akun_{lokasi}`, `rekening_{lokasi}`

Pada Akubumdes, seluruh hirarki akun disatukan ke dalam satu tabel rekursif `accounts` yang fleksibel dengan relasi `parent_row_id` dan atribut `coa_variant` pada level tenant:

```sql
-- Akubumdes: Struktur accounts terpadu (3 varian dalam 1 tabel)
CREATE TABLE `accounts` (
    `row_id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` BIGINT UNSIGNED NOT NULL,
    `parent_row_id` BIGINT UNSIGNED NULL,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(180) NOT NULL,
    `account_type` VARCHAR(30) NOT NULL, -- asset, liability, equity, revenue, expense
    `coa_variant` VARCHAR(20) NOT NULL DEFAULT 'standard', -- standard | trading | cooperative
    `normal_balance` CHAR(1) NOT NULL,    -- 'D' (Debit) atau 'C' (Credit)
    `level` SMALLINT UNSIGNED NOT NULL,   -- 1, 2, 3, 4, dst.
    `is_postable` BOOLEAN DEFAULT TRUE,   -- FALSE untuk akun Header/Induk, TRUE untuk akun transaksi
    `is_active` BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (`tenant_id`, `parent_row_id`) REFERENCES `accounts` (`tenant_id`, `row_id`)
);
```

### Keuntungan Penyatuan Akun:

1. **Pohon Akun Tak Terbatas (*Unlimited Depth*)**: Dapat menambah sub-akun level 5 atau 6 tanpa mengubah struktur database.
2. **Kueri Neraca Cepat**: Kueri agregasi saldo per level dapat dilakukan dengan *Recursive Common Table Expressions (CTE)* standar SQL.
3. **3 Varian Usaha dalam 1 Skema**: BUMDes standar/jasa, unit perdagangan dengan akun HPP & persediaan, dan koperasi cukup memilih `coa_variant` saat onboarding tenant.
4. **Pemberian Tanda Saldo Normal Jelas**: Memastikan validasi debit/kredit berjalan otomatis saat transaksi diinput.

---

## 5. Tooling & Perintah Migrasi Data (SIMAK → Akubumdes)

Untuk mengeksekusi transformasi data dari basis data SIMAK ke skema Akubumdes, telah disediakan serangkaian perintah artisan otomatis yang aman dan dapat diuji coba (*dry-run*):

```bash
# 1. Discover & Analisis Struktur Data Legacy SIMAK
php artisan legacy:discover-accounting --suffix={lokasi_id}

# 2. Inisialisasi Periode Fiskal
php artisan legacy:ensure-fiscal-periods {tenant} --from=2018 --to=2026

# 3. Migrasi Bagan Akun (COA) Varian Legacy
php artisan tenancy:import-legacy-chart-of-accounts {tenant} --suffix={lokasi_id}

# 4. Migrasi Akuntansi & Saldo (Opening bulan 0 + Monthly bulan 1-12 + Jurnal Transaksi)
php artisan legacy:migrate-accounting {tenant} {lokasi_id} --dry-run --chunk=500
php artisan legacy:migrate-accounting {tenant} {lokasi_id} --chunk=500 --no-fail-fast

# 5. Inisialisasi Sequence Nomor Urut
php artisan tenancy:initialize-sequences {tenant}
```

---

## 6. Kesimpulan

Transformasi database dari **SIMAK (Legacy)** ke **Akubumdes** tidak hanya memodernisasi nama tabel, tetapi juga:

1. **Mengeliminasi bottleneck arsitektur tabel dinamis** — dari ribuan tabel per unit usaha (`transaksi_{n}`, `rekening_{n}`, `accounts_{n}`, `saldo_{n}`) menjadi skema sharding terpadu dengan isolasi `tenant_id`.
2. **Menyatukan 3 varian CoA** (standar jasa, perdagangan/trading dengan HPP, koperasi) ke dalam satu tabel rekursif `accounts`.
3. **Menjamin keabsahan finansial tingkat tinggi** melalui pembukuan *double-entry immutable*, tipe data desimal presisi, dan saldo yang dapat dihitung ulang.
4. **Mempersiapkan sistem untuk skalabilitas ribuan unit usaha**, API holding konsolidasi, automasi billing SaaS, serta kecerdasan buatan (*AI Assistant*) terintegrasi.
