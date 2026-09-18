# Panduan Pengguna Akubumdes — Sistem Informasi Akuntansi & Keuangan BUMDes
*Modern Re-engineering & Upgrade dari SIMAK BUMDes*
*Dokumen Resmi Operasional & Manual Penggunaan Aplikasi*

> **Akubumdes** adalah modern upgrade & re-engineering dari **SIMAK (Sistem Informasi Manajemen Akuntansi Keuangan BUMDes, `ab1-team/simak`)**, dan **BUKAN** dari SIDBM (aplikasi pinjaman/lending UPK). Aplikasi ini murni mencakup akuntansi & keuangan BUMDes / BUMDesma.

---

## Daftar Isi

1. [Pendahuluan & Gambaran Umum](#1-pendahuluan--gambaran-umum)
2. [Login, Keamanan & Navigasi Antarmuka](#2-login-keamanan--navigasi-antarmuka)
3. [Dashboard Operasional Eksekutif](#3-dashboard-operasional-eksekutif)
4. [Manajemen Master Data](#4-manajemen-master-data)
5. [Akuntansi & Jurnal Keuangan](#5-akuntansi--jurnal-keuangan)
6. [Inventaris & Aset Tetap](#6-inventaris--aset-tetap)
7. [Perencanaan Anggaran (E-Budgeting)](#7-perencanaan-anggaran-e-budgeting)
8. [Pelaporan Keuangan (Financial Reports)](#8-pelaporan-keuangan-financial-reports)
9. [Prosedur Periodik (Tutup Buku & Taksiran Pajak)](#9-prosedur-periodik-tutup-buku--taksiran-pajak)
10. [Tagihan & Langganan SaaS (Billing)](#10-tagihan--langganan-saas-billing)
11. [Pusat Notifikasi & WhatsApp Gateway](#11-pusat-notifikasi--whatsapp-gateway)
12. [Pengaturan Lembaga (Settings)](#12-pengaturan-lembaga-settings)
13. [Manajemen Pengguna, Peran & Hak Akses (RBAC)](#13-manajemen-pengguna-peran--hak-akses-rbac)
14. [Profil Pengguna & Personalisasi](#14-profil-pengguna--personalisasi)
15. [Wizard Onboarding & Migrasi Data Mandiri](#15-wizard-onboarding--migrasi-data-mandiri)
16. [Portal Supervisi Kabupaten](#16-portal-supervisi-kabupaten)
17. [Portal Supervisi Provinsi](#17-portal-supervisi-provinsi)
18. [Panel Superadmin Platform SaaS](#18-panel-superadmin-platform-saas)
19. [Asisten Kecerdasan Buatan (AI Assistant - Ariel)](#19-asisten-kecerdasan-buatan-ai-assistant---ariel)
20. [Pintasan Keyboard, Tips & Panduan Troubleshooting](#20-pintasan-keyboard-tips--panduan-troubleshooting)
21. [Lampiran A — Matriks Hak Akses (RBAC)](#lampiran-a--matriks-hak-akses-rbac)

---

## 1. Pendahuluan & Gambaran Umum

### 1.1 Mengenal Akubumdes
**Akubumdes** adalah aplikasi web modern berbasis komputasi awan (*Multi-Tenant Cloud SaaS*) untuk **akuntansi & keuangan BUMDes / BUMDesma** di seluruh Indonesia. Aplikasi ini adalah *modern upgrade & re-engineering* dari **SIMAK (Sistem Informasi Manajemen Akuntansi Keuangan BUMDes, `ab1-team/simak`)** — basis data lama yang menggunakan tabel dinamis per unit usaha kini distandarisasi menjadi platform multi-tenant sharding dengan *immutable double-entry general ledger*.

Aplikasi ini mengintegrasikan seluruh rantai proses akuntansi & keuangan dalam satu ekosistem terpadu:
- **Akuntansi Standar SAK EP / ETAP**: Sistem pembukuan berpasangan (*double-entry*) otomatis dan *immutable* yang menjamin akuntabilitas tanpa selisih.
- **Laporan Keuangan Lengkap**: 9 laporan akuntansi core, CALK, paket LPJ tahunan, dan rekap aset berstandar SAK EP.
- **Pengawasan Bertingkat**: Portal monitoring real-time untuk Dinas PMD Kabupaten dan Dinas PMD Provinsi.
- **Automasi Notifikasi & Billing**: Integrasi WhatsApp Gateway dan Multi-Payment Gateway (QRIS & Virtual Account).
- **Asisten Cerdas (Ariel)**: Konsultasi regulasi (PP No. 11/2021) dan analisis data keuangan berbasis AI.

### 1.2 Landasan Hukum & Kepatuhan
Akubumdes disusun mengikuti ketentuan perundang-undangan Republik Indonesia:
1. **PP No. 11 Tahun 2021** tentang Badan Usaha Milik Desa.
2. **Permendesa PDTT No. 15 Tahun 2021** tentang BUMDesa dan Pedoman Pengelolaan BUMDesa Bersama (BUMDesma).
3. **Standar Akuntansi Keuangan Entitas Privat (SAK EP)** dan SAK ETAP.
4. **Kepmendesa PDTT** tentang Bagan Akun Standar (CoA) BUMDesa — dengan varian standar jasa/umum, perdagangan/trading (HPP), dan koperasi.

---

## 2. Login, Keamanan & Navigasi Antarmuka

### 2.1 Halaman Masuk (Login)
Untuk mengakses sistem:
1. Buka alamat URL instansi Anda di peramban web (Google Chrome, Microsoft Edge, Mozilla Firefox, atau Safari).
2. Masukkan **Nama Pengguna (Username)** atau **Alamat Email** yang terdaftar.
3. Masukkan **Kata Sandi (Password)** Anda.
4. *(Opsional)* Centang kotak **Ingat Saya** untuk menyimpan sesi login pada perangkat pribadi.
5. Klik tombol **Masuk ke Sistem**.

> **Keamanan Sesi**: Sistem secara otomatis mengamankan sesi Anda. Login dari perangkat baru akan memutus sesi aktif di perangkat sebelumnya (*Single Session Enforcement*).

### 2.2 Struktur Navigasi & Sidebar
Antarmuka Akubumdes terdiri dari:
- **Sidebar Kiri**: Menu navigasi hierarkis berdasarkan modul kerja. Sidebar dapat diperkecil (*collapse*) untuk memperluas area kerja.
- **Header Atas**:
  - **Pencarian Cepat / Command Palette** (`Ctrl+K` atau `Cmd+K`).
  - **Ikon Notifikasi**: Peringatan tagihan jatuh tempo, status jurnal, dan pembaruan sistem.
  - **Menu Profil & Tema**: Ganti foto, ubah password, dan pilih dari **7 Tema Warna Antarmuka**.
- **Breadcrumb**: Petunjuk lokasi halaman aktif saat ini.
- **Area Konten Utama**: Tempat formulir data, tabel interaktif, dan grafik visual ditampilkan.

### 2.3 Command Palette (`Ctrl+K`)
Tekan tombol keyboard `Ctrl+K` (Windows/Linux) atau `Cmd+K` (Mac) dari halaman mana saja:
- Cari nomor jurnal, kode akun, nama mitra, atau aset seketika.
- Lompat ke menu laporan atau halaman transaksi secara instan tanpa perlu mencari di sidebar.

### 2.4 Personalisasi Tema Warna
Tersedia 7 preset warna tema elegan: *Modern Indigo*, *Emerald Forest*, *Sunset Amber*, *Midnight Blue*, *Slate Corporate*, *Ocean Breeze*, dan *Rose Ruby*. Preferensi tersimpan otomatis pada peramban Anda.

---

## 3. Dashboard Operasional Eksekutif

**Menu:** `Dashboard` (Akses: `/dashboard`)

Dashboard menyajikan ikhtisar kondisi finansial dan operasional BUMDes secara langsung:

### 3.1 Kartu Indikator Kinerja Utama (KPI Cards)
- **Total Omset / Pendapatan**: Total pendapatan usaha periode berjalan.
- **Jumlah Transaksi Berjalan**: Jurnal & bukti kas yang diposting pada periode berjalan.
- **Posisi Saldo Kas & Bank**: Saldo riil likuiditas keuangan per hari ini.
- **Posisi Aset Tetap**: Nilai buku aset tetap dan tak berwujud per hari ini.
- **Laba Rugi Berjalan**: Surplus/defisit usaha periode tahun berjalan.
- **Rasio Kesehatan Keuangan**: Indikator likuiditas & rentabilitas (disertai indikator warna hijau/kuning/merah).

### 3.2 Ringkasan Jurnal & Bukti Kas (Interactive Drilldown Modal)
Menampilkan alur jurnal yang sedang berjalan:
- *Draft* $\rightarrow$ *Posted* $\rightarrow$ *Reversed* (koreksi via reverse + recreate).
- **Interaksi**: Klik pada salah satu kotak status untuk membuka jendela *Drilldown Modal*, yang menampilkan daftar rinci jurnal pada status tersebut lengkap dengan nomor jurnal, tanggal, dan nilai.

### 3.3 Grafik Tren & Komposisi
- Grafik tren pendapatan vs beban operasional dalam 12 bulan terakhir.
- Grafik komposisi pendapatan per sektor usaha (Perdagangan, Pertanian, Peternakan, Jasa, Industri Rumah Tangga).

### 3.4 Ringkasan Tutup Buku & Peringatan Dini
- **Periode Belum Ditutup**: Daftar bulan yang belum dilakukan tutup buku.
- **Peringatan Saldo Tidak Seimbang**: Notifikasi jika ada selisih debit/kredit pada jurnal atau bukti kas yang belum direkonsiliasi.

---

## 4. Manajemen Master Data

Modul Master Data adalah fondasi data wilayah desa, mitra, dan master pendukung pembukuan.

### 4.1 Data Desa
**Menu:** `Master -> Data Desa` (`/master-data/villages`)
- **Daftar Desa**: Menampilkan seluruh desa/kelurahan dalam wilayah kerja BUMDes / BUMDesma.
- **Informasi Desa**: Kode wilayah, nama desa, nama Kepala Desa, nomor telepon kantor desa, dan status aktif.
- **Fitur Scoped Operator Desa**: Jika pengguna berstatus *Operator Desa*, aplikasi secara otomatis membatasi data hanya untuk desa yang bersangkutan (*VillageScope*).

### 4.2 Data Mitra & Pelanggan Usaha
**Menu:** `Master -> Mitra Usaha` (`/master-data/institutions`)
- **Pendaftaran Mitra / Pelanggan**:
  - Formulir input data: Nama Mitra/Pelanggan, Nama Kontak, Nomor WhatsApp/HP, Alamat, Desa, dan Kategori mitra (Pelanggan, Vendor, Mitra Usaha, BPD).
- **Pencarian & Filter Cepat**: Cari berdasarkan Nama atau filter per Desa dan Kategori.
- **Halaman Detail Mitra**: Menampilkan biodata lengkap mitra, dokumen, serta **Riwayat Transaksi Usaha** yang tercatat.
- **Fitur Ekspor & Impor Massal**:
  - Ekspor seluruh data mitra ke format **Excel (.xlsx)** atau **CSV**.
  - Impor data mitra dari file Excel menggunakan template standar yang disediakan sistem.

### 4.3 Unit Usaha & Pusat Biaya
**Menu:** `Master -> Unit Usaha` (`/master-data/organization-units`)
- **Pendaftaran Unit Usaha**: Nama unit usaha, kode unit, jenis usaha (Jasa/Umum, Perdagangan/Trading, Koperasi), desa domisili, dan tanggal pendirian.
- **Varian CoA**: Pemilihan varian Chart of Accounts (`standard`, `trading`, `cooperative`) menentukan struktur akun default saat onboarding — termasuk akun Harga Perolehan Pokok (HPP) untuk unit perdagangan.
- **Susunan Pengurus**: Mengatur Penanggung Jawab, Bendahara, dan Pengawas yang dipilih langsung dari daftar pengguna tenant.

### 4.4 Lembaga Lain / Mitra
**Menu:** `Master -> Lembaga Lain` (`/master-data/institutions`)
- Pencatatan data mitra BPD, Dinas PMD, Bank penyalur kas, koperasi mitra, dan vendor.
- Fitur CRUD, Impor dan Ekspor data mitra.

---

## 5. Akuntansi & Jurnal Keuangan

Akubumdes menerapkan standar akuntansi berpasangan (*double-entry*) murni. Setiap transaksi tercatat secara seimbang (*Debit = Kredit*) dan dilengkapi jejak audit (*audit trail*).

### 5.1 Bagan Akun (Chart of Accounts / CoA)
**Menu:** `Keuangan -> Bagan Akun` (`/accounting/chart-of-accounts`)

Struktur akun disesuaikan dengan SAK EP / BUMDes dan tersedia dalam 3 varian sesuai jenis unit usaha:
- **1. Aset**: Kas, Bank BPD, Bank BRI, Piutang Usaha, Cadangan Kerugian Piutang, Persediaan Barang Dagang (varian trading), Perlengkapan, Aset Tetap, Akumulasi Penyusutan.
- **2. Kewajiban**: Utang Pihak Ketiga, Titipan Dana, Utang Pajak.
- **3. Ekuitas**: Modal Awal Pendirian, Cadangan Umum, Cadangan Tujuan, Laba Ditahan, Laba Rugi Tahun Berjalan.
- **4. Pendapatan**: Pendapatan Jasa/Usaha, Pendapatan Penjualan (varian trading), Pendapatan Administrasi, Pendapatan Bunga Bank, Pendapatan Non-Operasional.
- **5. Beban / Biaya**: Beban Operasional, Beban Gaji Pengelola, Beban ATK, Beban Penyusutan Aset, Beban Pajak.
- **6. HPP (varian trading)**: Harga Perolehan Pokok / Persediaan untuk unit usaha perdagangan.

CoA bersifat *read-only* pada UI; penyesuaian dilakukan melalui migrasi varian (`coa_variant`).

### 5.2 Jurnal Umum
**Menu:** `Transaksi -> Jurnal Umum` (`/accounting/journal-entries/create`)

1. **Pilih Preset Transaksi (Opsi Cepat)**: Tersedia template otomatis untuk transaksi rutin (misal: *Biaya Operasional Kantor, Pembelian ATK, Penerimaan Bunga Bank, Pembelian Inventaris, Setoran Modal*).
2. **Entri Transaksi Bebas (Manual Mode)**:
   - Tentukan **Tanggal Transaksi** dan **Keterangan / Uraian**.
   - Masukkan baris akun: Pilih akun Debit dan akun Kredit.
   - Masukkan nominal. Sistem memvalidasi bahwa total Debit harus sama persis dengan total Kredit sebelum tombol simpan dapat ditekan.
   - Fitur Pembelian Aset: Jika memilih akun aset tetap, sistem otomatis membuatkan master data inventaris yang bersangkutan.

### 5.3 Jurnal Penjualan & Pembelian (Unit Usaha Perdagangan)
**Menu:** `Transaksi -> Jurnal Umum` (`/accounting/journal-entries/create`)

Untuk unit usaha dengan `coa_variant = trading`:
1. Pilih preset transaksi **Penjualan** / **Pembelian Barang Dagang**.
2. Sistem otomatis memuat struktur akun HPP dan persediaan.
3. Masukkan nominal **Penjualan**, **HPP**, dan **Beban Lain-lain**.
4. Pilih akun kas penerima atau piutang usaha.
5. Klik **Posting Jurnal**:
   - Sistem memposting jurnal berpasangan persediaan ke HPP dan kas/piutang ke pendapatan.
   - Tersedia tombol cetak **Bukti Kas (BKM/BKK) PDF** dan opsi kirim melalui WhatsApp.

### 5.4 Koreksi Jurnal (Immutable Reverse & Recreate)
**Menu:** `Transaksi -> Daftar Jurnal` (`/accounting/journals`)

Demi kepatuhan audit akuntansi, jurnal yang sudah diposting tidak dapat dihapus sembarangan.
- **Koreksi Jurnal**: Buka jurnal, klik tombol **Koreksi**. Sistem secara otomatis membuat **Jurnal Pembalik (Reversal Entry)** untuk membatalkan jurnal lama, lalu membuka form untuk menerbitkan jurnal baru yang telah diperbaiki.
- Semua riwayat koreksi tercatat lengkap beserta identitas pengguna dan waktu perubahan.

### 5.5 Bukti Kas Masuk & Kas Keluar (BKM / BKK / BM)
Setiap jurnal transaksi dapat dicetak sebagai bukti fisik kas:
- **BKM (Bukti Kas Masuk)**: Untuk penerimaan kas/bank.
- **BKK (Bukti Kas Keluar)**: Untuk pengeluaran biaya/pembelian.
- **BM (Bukti Memorial)**: Untuk transaksi non-kas/penyesuaian.

Format cetak dirancang berukuran standar ringkas (14 cm x 9 cm) lengkap dengan kolom tanda tangan kasir, pembukuan, dan penerima dana.

---

## 6. Inventaris & Aset Tetap

**Menu:** `Transaksi -> Daftar Inventaris` (`/accounting/assets`)

### 6.1 Pencatatan Aset
Mencatat seluruh kekayaan aset tetap lembaga (Gedung kantor, Komputer, Laptop, Sepeda Motor operasional, Meja/Kursi kantor, Brankas):
- Nama aset, kode barang, tanggal perolehan, harga perolehan.
- Nilai residu/sisa dan umur ekonomis (dalam tahun/bulan).
- Lokasi dan penanggung jawab fisik aset.

### 6.2 Metode Penyusutan
Sistem mendukung 2 metode standar akuntansi:
1. **Garis Lurus (*Straight-Line*)**: Beban depresiasi bernilai sama setiap bulan sepanjang masa manfaat.
2. **Saldo Menurun (*Declining Balance*)**: Beban depresiasi lebih besar di tahun-tahun awal.

### 6.3 Batch Penyusutan Bulanan Otomatis
Setiap akhir bulan, pengelola dapat menjalankan eksekusi **Penyusutan Batch**. Sistem secara otomatis menghitung nilai depresiasi seluruh aset aktif dan menerbitkan Jurnal Penyusutan:
- *(Debit)* Beban Penyusutan Aset Tetap
- *(Kredit)* Akumulasi Penyusutan Aset Tetap

---

## 7. Perencanaan Anggaran (E-Budgeting)

**Menu:** `Periodik -> E-Budgeting` (`/budgeting`)

### 7.1 Penyusunan Rencana Kerja & Anggaran (RKA)
- Modul anggaran 12 bulan untuk merencanakan seluruh target pendapatan jasa dan plafon batas pengeluaran operasional per akun buku besar.
- **Fitur Salin Bulan Sebelumnya (*Copy Previous*)**: Menghemat waktu penyusunan anggaran dengan menyalin pola anggaran bulan lalu.

### 7.2 Siklus Persetujuan Anggaran
```
Draft Anggaran ──► Disetujui (Approved / Terkunci) ──► Buka Kunci (Reopen)
```
- Anggaran yang telah disetujui forum MAD dikunci (*Locked*) agar menjadi pedoman baku.
- Jika terdapat revisi APB/MAD Perubahan, pengguna dengan wewenang `budgeting.manage` dapat membuka kunci (*Reopen*) untuk melakukan penyesuaian.

### 7.3 Monitoring Realisasi vs Anggaran
Halaman menampilkan perbandingan interaktif antara nilai anggaran vs realisasi riil pembukuan, lengkap dengan deviasi nominal dan persentase capaian (% Varian).

---

## 8. Pelaporan Keuangan (Financial Reports)

**Menu:** `Pelaporan -> Laporan Keuangan` (`/accounting/reports`)

Seluruh laporan keuangan dapat difilter berdasarkan bulan/tahun, ditampilkan di layar, diekspor ke **Microsoft Excel (.xlsx)**, dan dicetak ke dokumen **PDF resmi ber-kop surat**.

| No | Laporan Keuangan | Deskripsi & Kegunaan |
|---|---|---|
| 1 | **Neraca (*Balance Sheet*)** | Posisi Aset (Lancar & Tetap), Kewajiban/Utang, dan Ekuitas BUMDes / BUMDesma per tanggal tertentu. Disertai komparasi periode lalu. |
| 2 | **Laba Rugi (*Income Statement*)** | Pendapatan Operasional, Beban Operasional, Pendapatan/Beban Non-Operasional, dan Hasil Usaha Bersih (Surplus/Defisit Berjalan). |
| 3 | **Arus Kas (*Cash Flow*)** | Arus kas masuk dan keluar yang dikelompokkan ke dalam 3 aktivitas utama: Operasi, Investasi, dan Pendanaan. |
| 4 | **Perubahan Ekuitas (*Equity Change*)** | Mutasi permodalan, penambahan surplus berjalan, alokasi cadangan, dan saldo akhir ekuitas. |
| 5 | **Buku Besar (*General Ledger*)** | Rincian seluruh mutasi debit, kredit, dan saldo berjalan per akun buku besar. |
| 6 | **Neraca Saldo (*Trial Balance*)** | Ringkasan saldo awal, total mutasi debit/kredit, dan saldo akhir seluruh akun untuk verifikasi keseimbangan. |
| 7 | **Catatan atas Lap. Keuangan (CALK)** | Dokumen narasi penjelasan kebijakan akuntansi, profil lembaga, dan rincian pos laporan keuangan. Dilengkapi fitur **Rich Text Editor** untuk menyimpan narasi pengelola. |
| 8 | **Jurnal Transaksi** | Rekapitulasi seluruh jurnal yang diposting dalam periode terpilih. |
| 9 | **Dokumen LPJ & Cover Tahunan (*Annual Pack*)** | Bundel lengkap Laporan Pertanggungjawaban Tahunan (Cover resmi, Daftar Isi, Surat Pengantar, Berita Acara, MoU kerjasama antar desa, dan seluruh lampiran laporan keuangan) dalam satu berkas PDF siap cetak. |
| 10 | **Penilaian Tingkat Kesehatan Keuangan** | Analisis rasio Likuiditas, Solvabilitas, Rentabilitas, dan Kualitas Aset sesuai parameter standar Kemendesa PDTT dengan predikat: *Sehat, Cukup Sehat, Kurang Sehat, atau Tidak Sehat*. |

---

## 9. Prosedur Periodik (Tutup Buku & Taksiran Pajak)

### 9.1 Tutup Buku Bulanan
**Menu:** `Periodik -> Tutup Buku` (`/accounting/period-close`)
1. Pilih **Tahun** dan **Bulan** yang akan ditutup.
2. Klik tombol **Tutup Bulan**.
3. Sistem mengunci seluruh transaksi pada bulan tersebut agar tidak dapat diubah oleh staf kasir tanpa izin khusus (*Reopen*).

### 9.2 Tutup Buku Tahunan & Alokasi Surplus (SHU)
1. Setelah bulan ke-12 ditutup, klik tombol **Tutup Tahun**.
2. Sistem otomatis menutup akun pendapatan dan beban ke akun *Ikhtisar Laba Rugi*.
3. **Form Alokasi Surplus Hasil Usaha (SHU)**: Masukkan persentase pembagian surplus sesuai keputusan rapat pengurus BUMDes:
   - Dana Cadangan Umum (Modal BUMDes)
   - Pembagian Bagian Hasil Desa (PADes)
   - Dana Pendidikan & Sosial Masyarakat
   - Bonus / Jasa Pengelola & Pengawas
4. Sistem otomatis memposting Jurnal Pembagian Surplus dan membentuk Saldo Awal Neraca untuk tahun buku berikutnya.

### 9.3 Taksiran Pajak
**Menu:** `Periodik -> Taksiran Pajak` (`/accounting/tax-estimate`)
Perhitungan estimasi Pajak Penghasilan (PPh Badan / UMKM) berdasarkan laba kena pajak berjalan.

---

## 10. Tagihan & Langganan SaaS (Billing)

**Menu:** `Tagihan -> Daftar Tagihan` (`/billing/invoices`)

Mengelola langganan lisensi operasional Akubumdes untuk lembaga BUMDes / BUMDesma Anda.

### 10.1 Cara Pembayaran Tagihan Otomatis
1. Buka tagihan dengan status *Belum Dibayar*.
2. Klik tombol **Bayar Sekarang**.
3. Pilih metode pembayaran yang diinginkan:
   - **QRIS Dinamis**: Tampilkan kode QR di layar, lalu pindai (*scan*) menggunakan aplikasi BCA Mobile, Livin Mandiri, BRImo, BNI Mobile, GoPay, OVO, Dana, atau ShopeePay.
   - **Virtual Account (VA)**: Dapatkan nomor VA otomatis dari bank pilihan (BRI, Mandiri, BNI, BCA, BSI, Permata, CIMB Niaga). Lakukan transfer via ATM/Mobile Banking.
4. Setelah transaksi berhasil, status tagihan otomatis berubah menjadi **Lunas (Paid)** dan masa aktif langganan instansi Anda diperpanjang seketika tanpa perlu konfirmasi manual.

### 10.2 Konfirmasi Manual
Jika melakukan pembayaran via transfer rekening penampung manual, unggah foto/berkas bukti transfer pada form konfirmasi untuk divalidasi oleh administrator platform.

---

## 11. Pusat Notifikasi & WhatsApp Gateway

**Menu:** `Periodik -> Notifikasi Tagihan` (`/notifications/billing`)

Kirim pesan pengingat tagihan langganan kepada pengelola BUMDes secara massal melalui WhatsApp.

### 11.1 Pengiriman Notifikasi Tagihan
1. Pilih **Tanggal Jatuh Tempo Target**.
2. Sistem menampilkan daftar tagihan dengan jadwal pembayaran pada tanggal tersebut.
3. Pilih penerima yang akan dikirimi pesan.
4. Klik tombol **Kirim Notifikasi WhatsApp**. Pesan terkirim otomatis berisi rincian tagihan, jatuh tempo, dan instruksi pembayaran.

---

## 12. Pengaturan Lembaga (Settings)

**Menu:** `Pengaturan` (`/settings`) — *Hak Akses: `settings.manage`*

Terdiri dari **5 Tab Konfigurasi**:

1. **Tab Identitas Lembaga**: Nama resmi BUMDes / BUMDesma, Nomor SK Pendirian, Alamat Kantor, Desa/Kecamatan/Kabupaten, Email, Nomor Telepon, dan Nama Direktur/Ketua Pengelola.
2. **Tab Sistem Akuntansi**: Pemilihan varian Chart of Accounts (`standard` / `trading` / `cooperative`), periode tahun buku, dan kebijakan tutup buku.
3. **Tab Logo Lembaga**: Unggah file logo instansi resolusi tinggi (.PNG/.JPG). Logo ini otomatis disematkan pada seluruh dokumen cetak bukti kas dan Laporan Keuangan PDF.
4. **Tab WhatsApp Gateway**: Menghubungkan nomor WhatsApp BUMDes / BUMDesma ke server gateway:
   - Klik *Buat Sesi WhatsApp*, scan QR code yang muncul menggunakan WhatsApp di HP pengelola.
   - Uji coba koneksi dengan tombol *Kirim Pesan Tes*.
5. **Tab Tanda Tangan Dokumen**: Mengatur nama lengkap, NIK, dan jabatan resmi untuk format tanda tangan di lembar dokumen cetak (Kepala Desa, Direktur BUMDesma, Sekretaris, Bendahara, Tim Akuntansi).

---

## 13. Manajemen Pengguna, Peran & Hak Akses (RBAC)

**Menu:** `Akses Pengguna` (`/access/users` & `/access/roles`)

### 13.1 Manajemen Akun Pengguna
- **Tambah Pengguna Baru**: Daftarkan staf dengan mengisi Nama, Username, Email, Password, dan menetapkan **Peran (Role)**.
- **Akun Operator Desa**: Centang opsi *Operator Desa* dan pilih desa penugasan. Pengguna tersebut hanya dapat mengelola data di desanya sendiri.
- **Reset Password**: Administrator dapat mereset kata sandi staf yang lupa password.

### 13.2 Manajemen Peran (Roles) & Hak Akses (Permissions)
- Administrator dapat membuat **Role Kustom** baru dan mencentang hak akses granular sesuai tupoksi kerja staf (misal: *Akuntan, Kasir, Bendahara, Staf Administrasi*).

---

## 14. Profil Pengguna & Personalisasi

**Menu:** `Profil (Pojok Kanan Atas)` (`/profile`)

1. **Informasi Pribadi**: Perbarui nama lengkap, NIK, alamat email, dan nomor kontak.
2. **Ubah Password**: Ganti kata sandi secara berkala demi keamanan akun.
3. **Foto Profil**: Unggah foto profil formal Anda.

---

## 15. Wizard Onboarding & Migrasi Data Mandiri

**Menu:** `Onboarding & Migrasi` (`/onboarding/import`)

Disediakan khusus untuk unit usaha BUMDes baru yang ingin bermigrasi dari pencatatan manual/Excel (atau dari basis data SIMAK lama) ke Akubumdes:
- **Langkah 1 (Master Data & Unit Usaha)**: Unduh template Excel, isi data unit usaha, mitra, dan wilayah, lalu unggah ke sistem.
- **Langkah 2 (Saldo Awal CoA)**: Impor pemetaan kode akun lama ke struktur CoA Akubumdes sesuai varian (standard / trading / cooperative).
- **Langkah 3 (Saldo Awal Neraca Keuangan)**: Masukkan saldo awal kas, bank, piutang usaha, aset inventaris, dan modal awal pembentukan BUMDes. Sistem memverifikasi keseimbangan sebelum saldo awal dibukukan.

---

## 16. Portal Supervisi Kabupaten

**Akses:** `/regency/*` (Khusus Akun Dinas PMD Kabupaten / Tenaga Ahli)

- **Dashboard Supervisi Kabupaten**: Peta sebaran BUMDes / BUMDesma di seluruh kecamatan dalam 1 kabupaten, total omset/pendapatan gabungan, akumulasi aset gabungan, dan rasio kesehatan rata-rata kabupaten.
- **Laporan Konsolidasi Kabupaten**: Neraca Konsolidasi Kabupaten, Laba Rugi Konsolidasi, Arus Kas Konsolidasi, Buku Besar Konsolidasi, dan CALK Konsolidasi.

---

## 17. Portal Supervisi Provinsi

**Akses:** `/province/*` (Khusus Akun Dinas PMD Provinsi / Koordinator Wilayah)

- **Dashboard Supervisi Provinsi**: Monitoring makro kinerja keuangan seluruh kabupaten dalam satu provinsi.
- **Paket 5 Laporan Keuangan Konsolidasi Provinsi**: Laporan gabungan tingkat provinsi siap unduh (PDF/Excel) untuk keperluan evaluasi gubernur dan pelaporan kementerian.

---

## 18. Panel Superadmin Platform SaaS

**Akses:** `/admin/*` (Khusus Pengelola Platform Teknis)

- **Manajemen Shard Tenant**: Pembuatan tenant baru, perbaikan struktur database (*Tenant Repair*), dan manajemen domain kustom.
- **Data Purifier**: Alat sanitasi otomatis untuk mendeteksi data piutang ganda atau selisih pembukuan pada tenant.
- **Manajemen Gateway Pembayaran**: Konfigurasi Tripay, Duitku, dan Xendit API.
- **Alat Cutover Migrasi Database SIMAK**: Migrasi instan basis data SIMAK lama (tabel dinamis per unit usaha) ke skema Akubumdes.

---

## 19. Asisten Kecerdasan Buatan (AI Assistant - Ariel)

**Widget Chat:** Terletak di pojok kanan bawah layar.

**Ariel** adalah asisten AI berbasis regulasi dan konteks data lembaga:
- **Konsultasi SOP & Regulasi**: Tanyakan seputar aturan PP No. 11/2021, standar pembukuan SAK EP, 3 varian CoA BUMDes, atau pembagian surplus SHU.
- **Analisis Data Cepat**: Ajukan pertanyaan praktis seperti analisis tunggakan, draf pengantar LPJ tahunan, atau jurnal transaksi.

---

## 20. Pintasan Keyboard, Tips & Panduan Troubleshooting

### 20.1 Tabel Pintasan Keyboard (Shortcuts)
| Tombol | Fungsi |
|---|---|
| `Ctrl + K` / `Cmd + K` | Membuka Command Palette (Pencarian Global) |
| `Escape` | Menutup Jendela Modal / Dialog Aktif |

### 20.2 Panduan Penanganan Masalah (Troubleshooting)
- **Error 403 (Akses Ditolak)**: Akun Anda tidak memiliki hak akses untuk fitur tersebut. Hubungi administrator instansi Anda untuk penyesuaian Role.
- **Error 419 (Sesi Berakhir / Page Expired)**: Sesi login telah habis karena tidak ada aktivitas. Muat ulang halaman (*Refresh*) dan masuk kembali.
- **Error 500 / 503 (Layanan Terkendala)**: Periksa koneksi internet Anda atau hubungi admin teknis lembaga.

---

## Lampiran A — Matriks Hak Akses (RBAC)

### Role Standar Sistem
1. **`admin`**: Akses penuh ke seluruh fitur dan pengaturan tenant.
2. **`akuntan`**: Akses modul akuntansi, pencatatan jurnal, cetak bukti kas, dan laporan keuangan.
3. **`kasir`**: Akses modul kasir, pencatatan jurnal, cetak bukti kas (BKM/BKK), notifikasi WhatsApp, dan laporan keuangan.
4. **`viewer`**: Akses melihat data (*Read-Only*) untuk Badan Pengawas / BPD.
5. **`village_operator`**: Akses terbatas untuk input data di wilayah desanya.
6. **`regency_supervisor`**: Akses portal pengawasan konsolidasi tingkat kabupaten.
7. **`province_supervisor`**: Akses portal pengawasan konsolidasi tingkat provinsi.

---

*Dokumentasi Resmi Akubumdes — Hak Cipta Terlindungi.*
