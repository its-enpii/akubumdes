# Runbook cutover 1 tenant (SIMAK → Next)

Rehearsal per tenant: **legacy suffix (`usaha.id`)** → Next tenant code & varian COA.  
Pilot referensi: `suffix=1` → `tenant=local`.

## Prinsip

1. Tenant maintenance / read-only di legacy SIMAK (produksi).  
2. Backup terverifikasi (`mysqldump simak`).  
3. Migrate + recon.  
4. Smoke UI + laporan keuangan.  
5. Switch placement (jika belum).  
6. Legacy read-only.  

Rehearsal dev **tanpa** maintenance/backup live — tetap jalankan chain Artisan yang sama.

## Prasyarat

| Item | Cek |
|---|---|
| `SIMAK_DB_*` / `LEGACY_DB_*` di `.env` | `legacy:discover-accounting --suffix=N` |
| Platform tenant + placement + shard | `tenants.code`, `tenants.coa_variant`, `tenant_placements` |
| Shard schema migrasi | `tenancy:migrate-shards` |
| COA varian di tenant | `tenancy:import-legacy-chart-of-accounts {tenant} --suffix={suffix}` |
| Koneksi DB Legacy | default database = `simak` |

## Urutan load (wajib)

```text
fiscal periods
  → COA varian (standard / trading / cooperative dari akun_level_* + akun_{lokasi} + rekening_{lokasi}/accounts_{lokasi})
  → accounting (transaksi_{lokasi} + saldo_{lokasi} bulan 0 untuk opening & bulan 1-12 untuk monthly balances)
  → sequences (sinkronisasi nomor urut sekuensial tenant)
```

Mapping varian `usaha.jenis_akun`:
- `5` → `standard` (BUMDes Standar — `rekening_{lokasi}`)
- `7` → `trading` (Unit Usaha Perdagangan — `accounts_{lokasi}`)
- `8` → `cooperative` (Koperasi — `rekening_{lokasi}`)

**Idempotent:** re-run skip baris yang sudah terdaftar di `legacy_record_mappings`.

## Command orchestrator

```bash
# Dry-run full chain (validasi saja di tiap step migrate)
php artisan legacy:cutover-tenant local 1 --dry-run

# Full load rehearsal
php artisan legacy:cutover-tenant local 1 \
  --from-year=2018 --to-year=2026 --chunk=500 --no-fail-fast

# Skip step yang sudah selesai
php artisan legacy:cutover-tenant local 1 \
  --skip-fiscal --skip-coa --skip-accounting
```

## Manual step-by-step (jika orchestrator tidak dipakai)

Ganti `TENANT` / `SUFFIX`.

```bash
# 0. Discover (read-only)
php artisan legacy:discover-accounting --suffix=SUFFIX

# 1. Fiscal Periods
php artisan legacy:ensure-fiscal-periods TENANT --from=2018 --to=2026

# 2. COA Varian Legacy (sekali per tenant)
php artisan tenancy:import-legacy-chart-of-accounts TENANT --suffix=SUFFIX

# 3. Accounting (Transaksi + Saldo Opening & Monthly)
php artisan legacy:migrate-accounting TENANT SUFFIX --dry-run --chunk=500
php artisan legacy:migrate-accounting TENANT SUFFIX --chunk=500 --no-fail-fast

# 4. Sequences
php artisan tenancy:initialize-sequences TENANT
```

## Acceptance checklist (per tenant)

### Counts

- [ ] `usaha.id` = `suffix` terpetakan ke `tenants.code` dengan `coa_variant` sesuai.  
- [ ] `transaksi_{suffix}` migratable ≈ `journal_entries` source legacy.  
- [ ] `saldo_{suffix}` bulan 0 = `account_opening_balances`.  
- [ ] `saldo_{suffix}` bulan 1..12 = `account_monthly_balances`.  

### Accounting

- [ ] Openings bulan 0 match (±0.01).  
- [ ] Saldo bulanan dan Neraca / Laba Rugi seimbang vs legacy SIMAK.  
- [ ] Buku Besar 3 footer totals (Debit, Kredit, Saldo Akhir) valid.  

### Smoke UI

- [ ] `/accounting/chart-of-accounts` (struktur pohon akun 4-level sesuai varian).  
- [ ] `/accounting/journal` (jurnal terposting dari legacy).  
- [ ] `/accounting/reports/balance-sheet` (Neraca seimbang).  
- [ ] `/accounting/reports/income-statement` (Laba Rugi sesuai varian BUMDes/Trading/Koperasi).  
- [ ] `/admin/migration` (monitoring eksekusi cutover run).  

## Referensi

- `docs/DATABASE_STRUCTURE.md`
- `docs/PERBANDINGAN_DATABASE_LEGACY_VS_NEXT.md`
