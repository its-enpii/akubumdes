<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Live proof against the shared SSO token bus. The test skips itself when that
 * Redis is unreachable or unauthenticated, so it never depends on the holding
 * stack being up. Coordinates stay 100% environment driven (`SSO_REDIS_*`), which
 * is what lets the same assertions pass in deployment mode 1 (same server, the
 * published loopback port) and mode 2 (remote holding host) — docs/SSO-CONTRACT.md.
 */
final class SsoSharedCacheBusTest extends TestCase
{
    private const CONNECTION = 'sso';

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->busIsReachable()) {
            $this->markTestSkipped($this->busSkipReason());
        }
    }

    public function test_token_lands_on_the_bus_under_the_exact_contract_key(): void
    {
        $token = bin2hex(random_bytes(32));
        $cacheKey = 'sso:'.hash('sha256', $token);
        $payload = ['email' => 'bus@akubumdes.test', 'exp' => time() + 60];

        Redis::connection(self::CONNECTION)->del($cacheKey);
        Cache::store(self::CONNECTION)->put($cacheKey, $payload, 60);

        // An empty prefix is the whole point: a per-application prefix here would
        // write the token somewhere the holding issuer never looks.
        $this->assertNotNull(
            Redis::connection(self::CONNECTION)->get($cacheKey),
            'The token is not on the bus under the raw contract key, so a prefix is leaking in.'
        );
        $this->assertSame(60, (int) Redis::connection(self::CONNECTION)->ttl($cacheKey), 'SSO tokens must live for 60 seconds.');

        $this->assertSame($payload, Cache::store(self::CONNECTION)->pull($cacheKey));
        $this->assertNull(Redis::connection(self::CONNECTION)->get($cacheKey), 'The token survived its single consumption.');
    }

    public function test_the_bus_is_not_the_application_default_store(): void
    {
        $cacheKey = 'sso:'.hash('sha256', bin2hex(random_bytes(32)));

        Cache::forget($cacheKey);
        Cache::store(self::CONNECTION)->put($cacheKey, ['probe' => true], 60);

        try {
            $this->assertNull(Cache::get($cacheKey), 'The application default store must never hold an SSO token.');
        } finally {
            Redis::connection(self::CONNECTION)->del($cacheKey);
        }
    }

    private function busIsReachable(): bool
    {
        $host = (string) config('database.redis.sso.host');
        $port = (int) config('database.redis.sso.port');

        $socket = @fsockopen($host, $port, $errorNumber, $errorMessage, 1.5);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        try {
            Redis::connection(self::CONNECTION)->ping();
        } catch (\Throwable) {
            // Reachable but unauthenticated: SSO_REDIS_PASSWORD is still a placeholder.
            return false;
        }

        return true;
    }

    private function busSkipReason(): string
    {
        return sprintf(
            'Shared SSO Redis bus is unreachable or unauthenticated at %s:%s (fill SSO_REDIS_HOST/SSO_REDIS_PORT/SSO_REDIS_PASSWORD).',
            config('database.redis.sso.host'),
            config('database.redis.sso.port'),
        );
    }
}
