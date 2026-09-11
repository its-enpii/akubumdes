<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Dump tool registry payload for orchestrator tool seed / admin import. */
final class PrintAssistantToolDefinitions extends Command
{
    protected $signature = 'sidbm:assistant-tools
        {--base= : SIDBM public base URL, default APP_URL}';

    protected $description = 'Print assistant tool definitions (JSON) for orchestrator seed.';

    public function handle(): int
    {
        $base = rtrim((string) ($this->option('base') ?: config('app.url')), '/');

        $tools = [
            [
                'name' => 'list_accounts',
                'description' => 'Daftar akun postable. Filter code_prefix, query nama (Bank Jateng, Kas Tunai), atau cash_only.',
                'requires_confirmation' => false,
                'endpoint_url' => $base.'/api/assistant/tools/list_accounts',
                'json_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'code_prefix' => ['type' => 'string'],
                        'query' => ['type' => 'string', 'description' => 'Cari di name/code'],
                        'cash_only' => ['type' => 'boolean', 'description' => 'Hanya 1.1.01.*'],
                    ],
                ],
            ],
            [
                'name' => 'search_assets',
                'description' => 'Cari inventaris/register aset by nama atau kode. Return book_value per as_of. needs_clarification jika ≠1. Beli aset = create_journal_entry tipe pembelian_aset_*.',
                'requires_confirmation' => false,
                'endpoint_url' => $base.'/api/assistant/tools/search_assets',
                'json_schema' => [
                    'type' => 'object',
                    'required' => ['query'],
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Nama/kode aset min 2 karakter'],
                        'status' => ['type' => 'string', 'description' => 'good|damaged|lost|sold|written_off'],
                        'as_of' => ['type' => 'string', 'description' => 'Y-m-d untuk nilai buku, default hari ini'],
                    ],
                ],
            ],
            [
                'name' => 'get_asset',
                'description' => 'Detail inventaris + nilai buku + riwayat status.',
                'requires_confirmation' => false,
                'endpoint_url' => $base.'/api/assistant/tools/get_asset',
                'json_schema' => [
                    'type' => 'object',
                    'required' => ['asset_row_id'],
                    'properties' => [
                        'asset_row_id' => ['type' => 'integer'],
                        'as_of' => ['type' => 'string', 'description' => 'Y-m-d nilai buku'],
                    ],
                ],
            ],
            [
                'name' => 'search_journals',
                'description' => 'Cari jurnal posted untuk koreksi/duplikat. Filter tanggal, nominal, tipe transaksi, akun, deskripsi, dan periode terakhir.',
                'requires_confirmation' => false,
                'endpoint_url' => $base.'/api/assistant/tools/search_journals',
                'json_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'journal_row_id' => ['type' => 'integer'],
                        'transaction_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD exact day (sets from+to)'],
                        'date_from' => ['type' => 'string'],
                        'date_to' => ['type' => 'string'],
                        'amount' => ['type' => 'number'],
                        'transaction_type' => ['type' => 'string'],
                        'query' => ['type' => 'string', 'description' => 'Cari di description'],
                        'account_query' => ['type' => 'string', 'description' => 'Nama/kode akun di lines'],
                        'recent' => ['type' => 'boolean', 'description' => 'true = 2 hari terakhir'],
                        'created_by_user_id' => ['type' => 'integer'],
                        'exclude_reversed' => ['type' => 'boolean'],
                        'limit' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'name' => 'create_journal_entry',
                'description' => 'Rencana/post jurnal. Default PREVIEW; post confirm=true. Beli aset=pembelian_aset_tanah|gedung|kendaraan|peralatan (server juga infer dari asset_name). Setor bank=pemindahan_saldo. Ambiguitas → needs_clarification. Tanggal Y-m-d.',
                'requires_confirmation' => true,
                'endpoint_url' => $base.'/api/assistant/tools/create_journal_entry',
                'json_schema' => [
                    'type' => 'object',
                    'required' => [
                        'transaction_date',
                        'amount',
                    ],
                    'properties' => [
                        'confirm' => [
                            'type' => 'boolean',
                            'description' => 'false/omit=preview saja; true=post setelah user setuju',
                        ],
                        'transaction_date' => [
                            'type' => 'string',
                            'description' => 'Wajib YYYY-MM-DD absolut. Jangan kirim "kemarin".',
                        ],
                        'transaction_type' => [
                            'type' => 'string',
                            'enum' => [
                                'aset_masuk',
                                'aset_keluar',
                                'pemindahan_saldo',
                                'pembelian_aset_tanah',
                                'pembelian_aset_gedung',
                                'pembelian_aset_kendaraan',
                                'pembelian_aset_peralatan',
                                'pembelian_inventaris',
                                'penyusutan_inventaris',
                                'cadangan_kerugian_aset',
                            ],
                            'description' => 'Beli: pembelian_aset_* per jenis (tanah/gedung/kendaraan/peralatan). Legacy pembelian_inventaris masih diterima. Setor bank=pemindahan_saldo. Boleh kosong — server infer.',
                        ],
                        'description' => ['type' => 'string'],
                        'reference' => ['type' => 'string'],
                        'amount' => ['type' => 'number', 'description' => 'Nominal (wajib)'],
                        'debit_account_row_id' => ['type' => 'integer'],
                        'credit_account_row_id' => ['type' => 'integer'],
                        'bank_account_query' => ['type' => 'string', 'description' => 'Nama bank tujuan setor, mis. Bank Jateng / Kas di Bank Ops'],
                        'cash_account_query' => ['type' => 'string', 'description' => 'Sumber kas, default Kas Tunai'],
                        'asset_name' => ['type' => 'string'],
                        'asset_quantity' => ['type' => 'integer'],
                        'asset_unit_cost' => ['type' => 'number'],
                        'asset_useful_life_months' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'name' => 'reverse_journal',
                'description' => 'Rencana/batalkan jurnal posted. Default PREVIEW; post dengan confirm=true. Salah bank dapat dikoreksi dengan repost. Duplikat: reverse tanpa repost. Multi → needs_clarification.',
                'requires_confirmation' => true,
                'endpoint_url' => $base.'/api/assistant/tools/reverse_journal',
                'json_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'confirm' => ['type' => 'boolean', 'description' => 'omit=preview; true=eksekusi reverse'],
                        'journal_row_id' => ['type' => 'integer', 'description' => 'Target; opsional jika filter unik'],
                        'reversal_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, default hari ini'],
                        'reason' => ['type' => 'string'],
                        'transaction_date' => ['type' => 'string', 'description' => 'Filter cari jurnal salah'],
                        'amount' => ['type' => 'number'],
                        'wrong_account_query' => ['type' => 'string', 'description' => 'Akun yang salah (mis. Bank Ops)'],
                        'account_query' => ['type' => 'string'],
                        'query' => ['type' => 'string'],
                        'transaction_type' => ['type' => 'string'],
                        'recent' => ['type' => 'boolean'],
                        'correct_bank_account_query' => ['type' => 'string', 'description' => 'Akun bank yang benar (mis. SPP)'],
                        'correct_account_query' => ['type' => 'string'],
                        'correct_debit_account_row_id' => ['type' => 'integer'],
                        'correct_credit_account_row_id' => ['type' => 'integer'],
                        'correct_cash_account_query' => ['type' => 'string'],
                        'correct_amount' => ['type' => 'number'],
                        'correct_transaction_type' => ['type' => 'string'],
                        'correct_description' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                'name' => 'download_report',
                'description' => 'Menghasilkan tombol/link direct download untuk laporan keuangan (Neraca, Laba Rugi, Arus Kas, Buku Besar, Jurnal, dll) dalam format PDF atau Excel.',
                'requires_confirmation' => false,
                'endpoint_url' => $base.'/api/assistant/tools/download_report',
                'json_schema' => [
                    'type' => 'object',
                    'required' => ['report_type'],
                    'properties' => [
                        'report_type' => [
                            'type' => 'string',
                            'description' => 'balance_sheet, income_statement, cash_flow, trial_balance, equity_change, calk, general_ledger, journals, fixed_assets',
                        ],
                        'format' => [
                            'type' => 'string',
                            'enum' => ['pdf', 'excel'],
                            'description' => 'Format file: pdf atau excel (default pdf)',
                        ],
                        'month' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12],
                        'year' => ['type' => 'integer'],
                        'from_date' => ['type' => 'string', 'format' => 'date'],
                        'to_date' => ['type' => 'string', 'format' => 'date'],
                        'account_id' => ['type' => 'integer'],
                        'as_of_date' => ['type' => 'string', 'format' => 'date'],
                    ],
                ],
            ],
        ];
        $this->line(json_encode($tools, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
