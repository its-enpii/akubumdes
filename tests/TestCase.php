<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Point the dedicated `sso` cache store at an in-memory array store so a test run
     * never depends on the shared holding Redis token bus being reachable.
     */
    protected function useInMemorySsoStore(): void
    {
        config()->set('cache.stores.sso', ['driver' => 'array']);

        Cache::purge('sso');
        Cache::store('sso')->flush();
    }

    protected function tearDown(): void
    {
        try {
            DB::connection('platform')->disconnect();
            DB::connection('tenant')->disconnect();
        } catch (\Throwable) {
        }

        parent::tearDown();
    }
}
