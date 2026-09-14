<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Configuration proof for the shared SSO token bus (docs/SSO-CONTRACT.md).
 *
 * Deliberately network free: the central holding Redis may be unreachable from a
 * subsidiary test run, while the shape of the `sso` store is what decides whether a
 * token emitted by holding is readable at all.
 */
final class SsoSharedCacheStoreTest extends TestCase
{
    public function test_sso_store_is_redis_on_the_dedicated_connection(): void
    {
        $this->assertSame('redis', config('cache.stores.sso.driver'));
        $this->assertSame('sso', config('cache.stores.sso.connection'));
        $this->assertSame('sso', config('cache.stores.sso.lock_connection'));
        $this->assertArrayHasKey('sso', config('database.redis'));
    }

    public function test_sso_store_and_connection_keep_key_prefixes_empty(): void
    {
        // The contract key is exactly "sso:{sha256(token)}". An application prefix
        // here would hide the holding token from this receiver.
        $this->assertSame('', (string) config('cache.stores.sso.prefix'));
        $this->assertSame('', (string) config('database.redis.sso.prefix'));
    }

    public function test_store_and_connection_resolve_their_values_from_the_environment(): void
    {
        // Whatever the environment says wins; a hardcoded value would drift here.
        $this->assertSame(
            (string) env('SSO_CACHE_REDIS_CONNECTION', 'sso'),
            (string) config('cache.stores.sso.connection'),
        );
        $this->assertSame(
            (string) env('SSO_CACHE_LOCK_CONNECTION', 'sso'),
            (string) config('cache.stores.sso.lock_connection'),
        );
        // akubumdes runs its app container on the same server as the holding bus, so
        // the live .env legitimately points at the host gateway instead of loopback.
        $this->assertSame((string) env('SSO_REDIS_HOST', '127.0.0.1'), (string) config('database.redis.sso.host'));
        $this->assertSame((string) env('SSO_REDIS_PORT', '6380'), (string) config('database.redis.sso.port'));
        $this->assertSame((string) env('SSO_REDIS_DB', '0'), (string) config('database.redis.sso.database'));
        $this->assertSame((string) env('SSO_REDIS_PASSWORD', ''), (string) config('database.redis.sso.password'));
        $this->assertSame((string) env('SSO_REDIS_CLIENT', 'predis'), (string) config('database.redis.sso.client'));
    }

    public function test_bus_coordinates_are_read_from_sso_redis_environment_variables(): void
    {
        $source = $this->source('config/database.php');

        foreach (['SSO_REDIS_HOST', 'SSO_REDIS_PORT', 'SSO_REDIS_PASSWORD', 'SSO_REDIS_DB', 'SSO_REDIS_PREFIX'] as $variable) {
            $this->assertStringContainsString(
                "env('{$variable}'",
                $source,
                "{$variable} must stay environment driven so one build runs on any deployment mode."
            );
        }
    }

    public function test_bus_coordinates_are_absent_from_code_and_only_fallback_in_config(): void
    {
        $this->assertSourceIsFreeOfHardcodedBusCoordinates('app/Http/Controllers/Auth/HoldingSsoController.php');
        $this->assertSourceIsFreeOfHardcodedBusCoordinates('config/cache.php');

        // Config defaults are the generic loopback fallback, never a docker service name.
        $config = $this->source('config/database.php');
        $this->assertStringNotContainsString("'host' => 'redis'", $config);
        $this->assertStringNotContainsString('redis-sso', $config);
    }

    public function test_receiver_reads_the_token_from_the_sso_store(): void
    {
        $source = $this->source('app/Http/Controllers/Auth/HoldingSsoController.php');

        $this->assertStringContainsString("Cache::store('sso')->pull(", $source);
        $this->assertStringNotContainsString('Cache::pull(', $source, 'The receiver must never read the default store.');
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    private function assertSourceIsFreeOfHardcodedBusCoordinates(string $path): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/(redis-sso|host\.docker\.internal|\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b|\b6380\b)/',
            $this->source($path),
            "Bus coordinates in {$path} belong in the environment, not in code."
        );
    }
}
