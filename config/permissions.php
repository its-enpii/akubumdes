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
use App\Http\Requests\MasterData\GroupRequest;
use App\Http\Requests\MasterData\MemberRequest;
use App\Http\Requests\MasterData\OtherInstitutionRequest;
use App\Http\Requests\MasterData\QuickMemberRequest;
use App\Http\Requests\MasterData\VillageRequest;
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
        'members.view',
        'members.manage',
        'groups.view',
        'groups.manage',
        'villages.view',
        'villages.manage',
        'institutions.view',
        'institutions.manage',
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
        'billing.view',
        'billing.pay',
        'regency.view_reports',
        'province.view_reports',
        'village_user.access',
        'settings.manage',
        'assistant.use',
        'portal.self',
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
                'members.view',
                'groups.view',
                'villages.view',
                'institutions.view',
                'journals.view',
                'journals.create',
                'assets.view',
                'reports.view',
                'tax.view',
                'budgeting.view',
                'messages.send',
                'billing.view',
                'billing.pay',
                'assistant.use',
            ],
        ],
        'verifikator' => [
            'name' => 'Verifikator',
            'is_system' => true,
            'permissions' => [
                'members.view',
                'groups.view',
                'villages.view',
                'institutions.view',
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
                'members.view',
                'groups.view',
                'villages.view',
                'institutions.view',
                'journals.view',
                'assets.view',
                'period_close.view',
                'reports.view',
                'tax.view',
                'budgeting.view',
                'billing.view',
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
                'members.view',
                'members.manage',
                'groups.view',
                'groups.manage',
                'reports.view',
                'village_user.access',
            ],
        ],
        'anggota' => [
            'name' => 'Anggota',
            'is_system' => true,
            'permissions' => ['portal.self'],
        ],
    ],

    'nav_map' => [
        '/portal' => 'portal.self',
        '/master-data/villages' => 'villages.view',
        '/master-data/members/create' => 'members.manage',
        '/master-data/members' => 'members.view',
        '/master-data/groups/create' => 'groups.manage',
        '/master-data/groups' => 'groups.view',
        '/master-data/institutions/create' => 'institutions.manage',
        '/master-data/institutions' => 'institutions.view',
        '/accounting/journals' => 'journals.view',
        '/accounting/assets' => 'assets.view',
        '/accounting/journal-entries' => 'journals.create',
        '/accounting/chart-of-accounts' => 'journals.view',
        '/accounting/period-close' => 'period_close.view',
        '/accounting/tax-estimate' => 'tax.view',
        '/accounting/reports' => 'reports.view',
        '/budgeting' => 'budgeting.view',
        '/billing/invoices' => 'billing.view',
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
        MemberRequest::class => 'members.manage',
        QuickMemberRequest::class => 'members.manage',
        GroupRequest::class => 'groups.manage',
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
        VillageRequest::class => 'villages.manage',
        OtherInstitutionRequest::class => 'institutions.manage',
        SitePageRequest::class => 'website.manage',
        SitePostRequest::class => 'website.manage',
        SiteSettingRequest::class => 'website.manage',
        StoreTenantUserRequest::class => 'users.manage',
        UpdateTenantUserRequest::class => 'users.manage',
        TenantRoleRequest::class => 'roles.manage',
    ],

    'tool_map' => [
        'search_members' => 'members.view',
        'search_groups' => 'groups.view',
        'list_accounts' => 'journals.view',
        'search_journals' => 'journals.view',
        'search_assets' => 'assets.view',
        'get_asset' => 'assets.view',
        'create_journal_entry' => 'journals.create',
        'reverse_journal' => 'journals.create',
        'download_report' => 'reports.view',
    ],
];
