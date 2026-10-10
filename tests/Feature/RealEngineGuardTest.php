<?php

namespace Tests\Feature;

use ArrayObject;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RealEngineGuard;
use Tests\Support\RealEngineProbe;
use Tests\Support\RealEngineRefused;
use Tests\TestCase;

/**
 * The safety gate in front of the destructive real-engine setup (RealEngineGuard). No database is needed or touched:
 * every test hands the guard a configuration and a FAKE probe that records its calls, plus a "wipe" spy, and proves
 *   - a bad configuration is refused before the database is even asked a question, and
 *   - nothing refused ever reaches the wipe step.
 * These run in the normal suite so the guard can never silently rot.
 */
class RealEngineGuardTest extends TestCase
{
    public function test_a_correct_disposable_configuration_is_accepted_and_wiped_once_after_two_live_checks(): void
    {
        [$probe, $calls] = $this->probe();
        $steps = new ArrayObject;

        RealEngineGuard::runDestructive($this->ctx(), $probe, fn () => $steps[] = 'wipe', fn () => $steps[] = 'rebuild');

        $this->assertSame(['wipe', 'rebuild'], $steps->getArrayCopy());
        $this->assertSame(2, $calls['database'], 'the live database is checked again immediately before the wipe');
    }

    public function test_a_unix_socket_without_a_host_is_accepted(): void
    {
        $this->assertAccepted($this->ctx(['host' => '', 'unix_socket' => '/tmp/atgvoid.sock']));
        $this->assertAccepted($this->ctx(['host' => null, 'unix_socket' => '/var/run/mysqld/mysqld.sock']));
    }

    public function test_an_empty_database_and_a_marked_database_are_both_accepted(): void
    {
        $this->assertAccepted($this->ctx(), [], null);
        $this->assertAccepted($this->ctx(), ['users', RealEngineGuard::MARKER_TABLE], RealEngineGuard::MARKER_TOKEN);
    }

    public function test_missing_opt_in_is_refused_as_not_enabled(): void
    {
        foreach ([false, '', '0', 'true', 'yes', ' 1'] as $value) {
            $refusal = $this->assertRefused($this->ctx(['env' => ['ATG_REAL_ENGINE_TESTS' => $value] + $this->ctx()['env']]));
            $this->assertTrue($refusal->isNotEnabled(), 'a missing opt-in means "skip the tests", not "misconfigured"');
        }
    }

    public function test_missing_or_wrong_destructive_authorization_is_refused(): void
    {
        foreach ([false, '', '1', 'yes', 'WIPE-DISPOSABLE-DATABASE', 'wipe'] as $value) {
            $refusal = $this->assertRefused($this->ctx(['env' => ['ATG_REAL_ENGINE_DESTRUCTIVE_SETUP' => $value] + $this->ctx()['env']]));
            $this->assertFalse($refusal->isNotEnabled(), 'opted in but not authorized is a configuration error, not a skip');
            $this->assertStringContainsString('ATG_REAL_ENGINE_DESTRUCTIVE_SETUP', $refusal->getMessage());
        }
    }

    public function test_a_non_testing_environment_is_refused(): void
    {
        foreach (['local', 'production', 'staging', ''] as $appEnv) {
            $this->assertRefused($this->ctx(['app_env' => $appEnv]));
            $this->assertRefused($this->ctx(['env' => ['APP_ENV' => $appEnv] + $this->ctx()['env']]));
        }
        $this->assertRefused($this->ctx(['env' => ['APP_ENV' => false] + $this->ctx()['env']]));
    }

    #[DataProvider('unexpectedDatabaseNames')]
    public function test_an_unexpected_database_name_is_refused(mixed $name): void
    {
        $this->assertRefused($this->ctx(['database' => $name, 'env' => ['DB_DATABASE' => $name] + $this->ctx()['env']]));
    }

    public static function unexpectedDatabaseNames(): array
    {
        return [
            'production name' => ['atgpos'],
            'bare prefix (the old, weaker rule)' => ['atg_void_test'],
            'prefix without unique id separator' => ['atg_void_testabc123'],
            'too short id' => ['atg_void_test_ab'],
            'upper case' => ['ATG_VOID_TEST_ABC123'],
            'laravel default' => ['laravel'],
            'system schema' => ['mysql'],
            'empty' => [''],
            'null' => [null],
            'whitespace around a valid name' => [' atg_void_test_abc123 '],
            'injection-looking' => ['atg_void_test_abc123; drop database atgpos'],
            'path-like' => ['../atgpos'],
            'sqlite memory' => [':memory:'],
            'wildcard' => ['atg_void_test_%'],
        ];
    }

    public function test_a_database_name_in_the_environment_that_disagrees_with_the_configuration_is_refused(): void
    {
        $this->assertRefused($this->ctx(['env' => ['DB_DATABASE' => 'atgpos'] + $this->ctx()['env']]));
    }

    public function test_an_unexpected_connection_is_refused(): void
    {
        foreach (['sqlite', 'pgsql', 'sqlsrv', '', null, 'MYSQL'] as $connection) {
            $this->assertRefused($this->ctx(['connection' => $connection, 'default_connection' => $connection]));
        }
        // The default connection is another database than the verified one: a silent fallback must be impossible.
        $this->assertRefused($this->ctx(['default_connection' => 'sqlite']));
        $this->assertRefused($this->ctx(['connection' => 'mariadb', 'default_connection' => 'mysql']));
        // A connection whose name says mysql but whose driver is something else.
        $this->assertRefused($this->ctx(['driver' => 'sqlite']));
    }

    #[DataProvider('remoteOrAmbiguousHosts')]
    public function test_a_remote_production_like_or_ambiguous_host_is_refused(mixed $host, mixed $socket): void
    {
        $this->assertRefused($this->ctx(['host' => $host, 'unix_socket' => $socket]));
    }

    public static function remoteOrAmbiguousHosts(): array
    {
        return [
            'production hostname' => ['app.anaktunggalgroup.com', ''],
            'public ip' => ['203.0.113.7', ''],
            'private network ip' => ['10.0.0.5', ''],
            'lookalike of localhost' => ['127.0.0.1.example.com', ''],
            'localhost lookalike' => ['localhost.evil.test', ''],
            'bind-all address' => ['0.0.0.0', ''],
            'internal service name' => ['db', ''],
            'no host and no socket' => ['', ''],
            'nothing at all' => [null, null],
            'relative socket' => ['', 'mysql.sock'],
            'socket path traversal' => ['', '/tmp/../var/run/mysqld.sock'],
            'host with a trailing space' => ['127.0.0.1 ', ''],
        ];
    }

    public function test_a_connection_url_or_a_read_write_split_is_refused(): void
    {
        $this->assertRefused($this->ctx(['url' => 'mysql://root@db.example.com/atgpos']));
        $this->assertRefused($this->ctx(['read_write_split' => true]));
    }

    public function test_a_live_database_that_is_not_the_configured_one_is_refused(): void
    {
        foreach (['atgpos', 'atg_void_test_other99', '', null] as $resolved) {
            $this->assertRefused($this->ctx(), [], null, $resolved, liveOnly: true);
        }
    }

    public function test_a_live_database_with_tables_but_no_disposable_marker_is_never_wiped(): void
    {
        // The name is perfectly valid; the content says it is somebody's real data.
        $this->assertRefused($this->ctx(), ['users', 'stock_balances', 'sales_transactions'], null, liveOnly: true);
        $this->assertRefused($this->ctx(), ['users', RealEngineGuard::MARKER_TABLE], 'some-other-token', liveOnly: true);
        $this->assertRefused($this->ctx(), ['users', RealEngineGuard::MARKER_TABLE], null, liveOnly: true);
    }

    public function test_the_live_check_runs_again_immediately_before_the_wipe_and_a_change_in_between_is_caught(): void
    {
        // The first live check sees a harmless empty database, the second (just before the wipe) sees real data.
        $calls = new ArrayObject(['tables' => 0, 'wiped' => false]);
        $probe = new RealEngineProbe(
            fn () => 'atg_void_test_abc123',
            function () use ($calls) {
                return ++$calls['tables'] === 1 ? [] : ['users', 'stock_balances'];
            },
            fn () => null,
        );

        try {
            RealEngineGuard::runDestructive($this->ctx(), $probe, function () use ($calls) {
                $calls['wiped'] = true;
            }, fn () => null);
            $this->fail('The second live check must refuse.');
        } catch (RealEngineRefused $e) {
            $this->assertFalse($e->isNotEnabled());
        }

        $this->assertSame(2, $calls['tables']);
        $this->assertFalse($calls['wiped'], 'refused before the destructive step');
    }

    public function test_the_application_default_environment_of_this_suite_is_never_accepted(): void
    {
        // The normal suite runs on SQLite in memory without any of the flags: the guard must say "not enabled".
        $this->expectException(RealEngineRefused::class);
        RealEngineGuard::assertConfiguration(RealEngineGuard::contextFromApplication());
    }

    // ---- helpers ----------------------------------------------------------------------------------------

    /** A configuration that is accepted. Each test breaks exactly one thing. */
    private function ctx(array $override = []): array
    {
        return array_replace([
            'env' => [
                'APP_ENV' => 'testing',
                'DB_DATABASE' => 'atg_void_test_abc123',
                'ATG_REAL_ENGINE_TESTS' => '1',
                'ATG_REAL_ENGINE_DESTRUCTIVE_SETUP' => 'wipe-disposable-database',
            ],
            'app_env' => 'testing',
            'default_connection' => 'mysql',
            'connection' => 'mysql',
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'unix_socket' => '',
            'database' => 'atg_void_test_abc123',
            'url' => null,
            'read_write_split' => false,
        ], $override);
    }

    /** @return array{0: RealEngineProbe, 1: ArrayObject} */
    private function probe(mixed $resolved = 'atg_void_test_abc123', array $tables = [], ?string $marker = null): array
    {
        $calls = new ArrayObject(['database' => 0, 'tables' => 0, 'marker' => 0]);

        return [new RealEngineProbe(
            function () use ($calls, $resolved) {
                $calls['database']++;

                return $resolved;
            },
            function () use ($calls, $tables) {
                $calls['tables']++;

                return $tables;
            },
            function () use ($calls, $marker) {
                $calls['marker']++;

                return $marker;
            },
        ), $calls];
    }

    private function assertAccepted(array $ctx, array $tables = [], ?string $marker = null): void
    {
        [$probe] = $this->probe($ctx['database'], $tables, $marker);
        $wiped = new ArrayObject;

        RealEngineGuard::runDestructive($ctx, $probe, fn () => $wiped[] = 'wipe', fn () => $wiped[] = 'rebuild');

        $this->assertSame(['wipe', 'rebuild'], $wiped->getArrayCopy());
    }

    /**
     * Asserts a refusal and that it came BEFORE the destructive step. A configuration refusal must also come before
     * the database was asked anything; a live refusal (liveOnly) must at least come before the wipe.
     */
    private function assertRefused(array $ctx, array $tables = [], ?string $marker = null, mixed $resolved = 'atg_void_test_abc123', bool $liveOnly = false): RealEngineRefused
    {
        [$probe, $calls] = $this->probe($resolved, $tables, $marker);
        $steps = new ArrayObject;

        try {
            RealEngineGuard::runDestructive($ctx, $probe, fn () => $steps[] = 'wipe', fn () => $steps[] = 'rebuild');
        } catch (RealEngineRefused $e) {
            $this->assertSame([], $steps->getArrayCopy(), 'a refused run must never reach the destructive step');
            if (! $liveOnly) {
                $this->assertSame(0, $calls['database'] + $calls['tables'] + $calls['marker'], 'a bad configuration is refused before the database is contacted');
            }

            return $e;
        }

        $this->fail('Expected the guard to refuse: '.json_encode($ctx));
    }
}
