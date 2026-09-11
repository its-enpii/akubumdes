<?php

declare(strict_types=1);

use App\Http\Requests\Access\StoreTenantUserRequest;
use App\Http\Requests\Access\TenantRoleRequest;
use App\Http\Requests\Access\UpdateTenantUserRequest;
use App\Http\Requests\Accounting\AggregateJournalRequest;
use App\Http\Requests\Accounting\JournalEntryRequest;
use App\Http\Requests\Accounting\ManualOpeningBalanceRequest;
use App\Http\Requests\Assets\AssetRequest;
use App\Http\Requests\Budgeting\SaveBudgetMonthRequest;
use App\Http\Requests\Settings\IdentityRequest;
use App\Http\Requests\Settings\LogoUploadRequest;
use App\Http\Requests\Settings\OfflineAccessRequest;
use App\Http\Requests\Settings\OrchestratorRequest;
use App\Http\Requests\Settings\SignatureImageUploadRequest;
use App\Http\Requests\Settings\SignaturesRequest;
use App\Http\Requests\Settings\WhatsappInstanceRequest;
use App\Http\Requests\Settings\WhatsappRequest;
use App\Http\Requests\Website\SitePageRequest;
use App\Http\Requests\Website\SitePostRequest;
use App\Http\Requests\Website\SiteSettingRequest;

return [
    'permissions' => [
        'website.view',
        'website.manage',
        'journals.view',
        'journals.create',
        'assets.view',
        'assets.manage',
        'period_close.view',
        'period_close.manage',
        'reports.view',
        'reports.manage',
        'tax.view',
        'budgeting.view',
        'budgeting.manage',
        'messages.send',
        'regency.view_reports',
        'province.view_reports',
        'village_user.access',
        'settings.manage',
        'assistant.use',
        'users.view',
        'users.manage',
        'roles.view',
        'roles.manage',
    ],

    'roles' => [
        'admin' => [
            'name' => 'Administrator',
            'is_system' => true,
            'permissions' => ['*'],
        ],
        'kasir' => [
            'name' => 'Kasir / Teller',
            'is_system' => true,
            'permissions' => [
                'journals.view',
                'journals.create',
                'assets.view',
                'reports.view',
                'tax.view',
                'budgeting.view',
                'messages.send',
                'assistant.use',
            ],
        ],
        'verifikator' => [
            'name' => 'Verifikator',
            'is_system' => true,
            'permissions' => [
                'journals.view',
                'assets.view',
                'reports.view',
                'assistant.use',
            ],
        ],
        'viewer' => [
            'name' => 'Viewer',
            'is_system' => true,
            'permissions' => [
                'journals.view',
                'assets.view',
                'period_close.view',
                'reports.view',
                'tax.view',
                'budgeting.view',
                'assistant.use',
            ],
        ],
        'regency_supervisor' => [
            'name' => 'Supervisor Kabupaten',
            'is_system' => true,
            'permissions' => ['regency.view_reports'],
        ],
        'province_supervisor' => [
            'name' => 'Supervisor Provinsi',
            'is_system' => true,
            'permissions' => ['province.view_reports'],
        ],
        'village_operator' => [
            'name' => 'Operator Desa',
            'is_system' => true,
            'permissions' => [
                'reports.view',
                'village_user.access',
            ],
        ],
    ],

    'nav_map' => [
        '/accounting/journals' => 'journals.view',
        '/accounting/assets' => 'assets.view',
        '/accounting/journal-entries' => 'journals.create',
        '/accounting/chart-of-accounts' => 'journals.view',
        '/accounting/period-close' => 'period_close.view',
        '/accounting/tax-estimate' => 'tax.view',
        '/accounting/reports' => 'reports.view',
        '/budgeting' => 'budgeting.view',
        '/access/users' => 'users.view',
        '/access/roles' => 'roles.view',
        '/website' => 'website.view',
        '/settings' => 'settings.manage',
        '/regency' => 'regency.view_reports',
        '/province' => 'province.view_reports',
    ],

    'request_map' => [
        JournalEntryRequest::class => 'journals.create',
        ManualOpeningBalanceRequest::class => 'journals.create',
        AggregateJournalRequest::class => 'journals.create',
        SaveBudgetMonthRequest::class => 'budgeting.manage',
        IdentityRequest::class => 'settings.manage',
        LogoUploadRequest::class => 'settings.manage',
        WhatsappRequest::class => 'settings.manage',
        OfflineAccessRequest::class => 'settings.manage',
        WhatsappInstanceRequest::class => 'settings.manage',
        OrchestratorRequest::class => 'settings.manage',
        SignaturesRequest::class => 'settings.manage',
        SignatureImageUploadRequest::class => 'settings.manage',
        AssetRequest::class => 'assets.manage',
        SitePageRequest::class => 'website.manage',
        SitePostRequest::class => 'website.manage',
        SiteSettingRequest::class => 'website.manage',
        StoreTenantUserRequest::class => 'users.manage',
        UpdateTenantUserRequest::class => 'users.manage',
        TenantRoleRequest::class => 'roles.manage',
    ],

    'tool_map' => [
        'list_accounts' => 'journals.view',
        'search_journals' => 'journals.view',
        'search_assets' => 'assets.view',
        'get_asset' => 'assets.view',
        'create_journal_entry' => 'journals.create',
        'reverse_journal' => 'journals.create',
        'download_report' => 'reports.view',
    ],
];
