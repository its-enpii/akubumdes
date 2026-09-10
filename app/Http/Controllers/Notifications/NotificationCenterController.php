<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Domain\Accounting\Models\JournalEntry;
use App\Models\Platform\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class NotificationCenterController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['unread_count' => 0, 'items' => []]);
        }

        $readIds = is_array($user->notifications_read)
            ? $user->notifications_read
            : (array) ($request->session()->get('notifications_read', []));

        $items = [];
        $today = CarbonImmutable::today();

        try {
            // 1. Platform Invoices (Subscription & Service Fees)
            $tenant = $user->tenant;
            if ($tenant !== null) {
                $unpaidInvoices = Invoice::query()
                    ->where('tenant_id', $tenant->row_id)
                    ->whereIn('status', ['issued', 'pending_payment', 'overdue'])
                    ->orderByRaw("CASE WHEN status = 'overdue' THEN 0 ELSE 1 END")
                    ->oldest('due_at')
                    ->get();

                if ($unpaidInvoices->isNotEmpty()) {
                    $latestInvoice = $unpaidInvoices->first();
                    $isOverdue = $latestInvoice->status === 'overdue' || ($latestInvoice->due_at && $latestInvoice->due_at->isPast());
                    $isBlocking = (bool) $latestInvoice->blocks_access;
                    $targetUrl = "/billing/invoices/{$latestInvoice->row_id}";

                    $id = 'tenant_invoice_'.$latestInvoice->row_id;
                    $title = $isBlocking
                        ? "Tagihan #{$latestInvoice->number} (Akses Terkunci)"
                        : ($isOverdue ? "Tagihan Overdue #{$latestInvoice->number}" : 'Tagihan Menunggu Pembayaran');

                    $items[] = [
                        'id' => $id,
                        'type' => 'invoice',
                        'title' => $title,
                        'message' => $isBlocking
                            ? 'Akses operasional ditangguhkan hingga tagihan sebesar Rp '.number_format((float) $latestInvoice->remainingAmount(), 0, ',', '.').' dilunasi.'
                            : 'Tagihan Rp '.number_format((float) $latestInvoice->remainingAmount(), 0, ',', '.').($latestInvoice->due_at ? ' jatuh tempo '.$latestInvoice->due_at->format('d M Y') : '').'.',
                        'time' => $isBlocking ? 'Wajib Lunas' : ($isOverdue ? 'Overdue' : ($latestInvoice->due_at?->diffForHumans() ?? 'Perlu Tindakan')),
                        'target_url' => $targetUrl,
                        'icon' => $isBlocking ? 'lock' : 'receipt_long',
                        'variant' => ($isBlocking || $isOverdue) ? 'danger' : 'warning',
                        'read' => in_array($id, $readIds, true),
                        'actor' => 'Admin Platform',
                    ];
                }
            }

            // 2. Recent journal entries created by users
            $recentJournals = JournalEntry::query()
                ->latest('row_id')
                ->take(3)
                ->get();

            if ($recentJournals->isNotEmpty()) {
                $userIds = $recentJournals->pluck('created_by_user_id')->filter()->unique()->values()->all();
                $userMap = ! empty($userIds) ? User::query()->whereIn('row_id', $userIds)->pluck('name', 'row_id')->all() : [];

                foreach ($recentJournals as $journal) {
                    $creatorName = ($journal->created_by_user_id && isset($userMap[$journal->created_by_user_id]))
                        ? $userMap[$journal->created_by_user_id]
                        : 'Petugas Akuntansi';

                    $id = 'journal_recent_'.$journal->row_id;
                    $journalNumber = $journal->journal_number ?: 'Umum';
                    $targetUrl = '/accounting/journals?q='.urlencode((string) $journalNumber);

                    $items[] = [
                        'id' => $id,
                        'type' => 'journal_activity',
                        'title' => 'Jurnal: '.$journalNumber,
                        'message' => sprintf('Jurnal %s (%s) dicatat oleh %s.', $journalNumber, $journal->description ?: 'Transaksi Operasional', $creatorName),
                        'time' => $journal->created_at?->diffForHumans() ?? 'Baru saja',
                        'target_url' => $targetUrl,
                        'icon' => 'receipt_long',
                        'variant' => 'info',
                        'read' => in_array($id, $readIds, true),
                        'actor' => $creatorName,
                    ];
                }
            }

            // Default welcome/info notification if empty
            if (empty($items)) {
                $id = 'system_status_ok';
                $items[] = [
                    'id' => $id,
                    'type' => 'system',
                    'title' => 'Operasional Normal',
                    'message' => 'Tidak ada pemberitahuan yang membutuhkan tindakan saat ini.',
                    'time' => 'Hari ini',
                    'target_url' => '/dashboard',
                    'icon' => 'check_circle',
                    'variant' => 'success',
                    'read' => in_array($id, $readIds, true),
                    'actor' => null,
                ];
            }
        } catch (Throwable) {
            $id = 'system_info';
            $items[] = [
                'id' => $id,
                'type' => 'system',
                'title' => 'Sistem Informasi SIDBM',
                'message' => 'Selamat datang di Sistem Informasi Dana Bergulir Masyarakat.',
                'time' => 'Informasi',
                'target_url' => '/dashboard',
                'icon' => 'info',
                'variant' => 'info',
                'read' => in_array($id, $readIds, true),
                'actor' => null,
            ];
        }

        $unreadCount = count(array_filter($items, fn (array $item): bool => ! $item['read']));

        return response()->json([
            'unread_count' => $unreadCount,
            'items' => $items,
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $id = $request->input('id');
        $ids = $request->input('ids');

        $readIds = [];
        if ($user !== null && is_array($user->notifications_read)) {
            $readIds = $user->notifications_read;
        } else {
            $readIds = (array) ($request->session()->get('notifications_read', []));
        }

        if (is_array($ids)) {
            foreach ($ids as $singleId) {
                if (is_string($singleId) && $singleId !== '' && ! in_array($singleId, $readIds, true)) {
                    $readIds[] = $singleId;
                }
            }
        } elseif (is_string($id) && $id !== '') {
            if (! in_array($id, $readIds, true)) {
                $readIds[] = $id;
            }
        } else {
            $allIds = ['system_status_ok', 'system_info'];
            $readIds = array_unique(array_merge($readIds, $allIds));
        }

        $readIds = array_values(array_unique($readIds));

        if ($user !== null) {
            $user->notifications_read = $readIds;
            $user->save();
        }

        $request->session()->put('notifications_read', $readIds);

        return response()->json(['success' => true]);
    }
}
