<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Dashboard\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    private const PIPELINE_MODAL_KEYS = ['proposal', 'verifikasi', 'waiting', 'aktif'];

    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $membership = $user?->memberships()
            ->with('tenant')
            ->where('status', 'active')
            ->orderBy('row_id')
            ->first();

        $payload = $this->dashboard->build();

        return Inertia::render('Dashboard', [
            'unitName' => $membership?->tenant?->name ?? $payload['unit_name'],
            ...$payload,
        ]);
    }
}
