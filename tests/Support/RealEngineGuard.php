<?php

namespace Tests\Support;

/**
 * Fail-closed safety gate for the real-engine tests. Their setup DROPS EVERY TABLE of the target database, so
 * nothing destructive may run unless EVERY check here passes. It is pure (all inputs arrive as arrays and
 * closures), which is what lets RealEngineGuardTest prove each refusal happens before any destructive step.
 *
 * Layers, in order:
 *   1. configuration (no database contact): explicit opt-in, explicit destructive authorization, APP_ENV=testing,
 *      a named mysql/mariadb connection that IS the default one (no silent fallback), no URL / read-write split,
 *      a local host or unix socket only, and a database named atg_void_test_<unique id>.
 *   2. live (read-only SELECTs): the database the server says the connection really uses must be that same name,
 *      and a database that already holds tables must carry our disposable-database marker. A database that is
 *      neither empty nor marked is never wiped, whatever it is called.
 *   3. the live check runs again immediately before the wipe.
 *
 * Remaining limit, stated rather than hidden: an SSH tunnel that makes a remote server look like 127.0.0.1 cannot
 * be detected from here; the name pattern, the marker table and the two explicit flags are what protect then.
 */
final class RealEngineGuard
{
    public const ENV_OPT_IN = 'ATG_REAL_ENGINE_TESTS';

    public const ENV_DESTRUCTIVE = 'ATG_REAL_ENGINE_DESTRUCTIVE_SETUP';

    public const DESTRUCTIVE_PHRASE = 'wipe-disposable-database';

    /** atg_void_test_<unique id>: one fresh database per QA run. The bare prefix is not enough. */
    public const DATABASE_PATTERN = '/^atg_void_test_[a-z0-9]{6,40}$/';

    public const MARKER_TABLE = 'atg_disposable_marker';

    public const MARKER_TOKEN = 'atg-real-engine-disposable-v1';

    private const CONNECTIONS = ['mysql' => 'mysql', 'mariadb' => 'mariadb'];

    private const LOCAL_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    /** Never acceptable, whatever else matches (the pattern already excludes them; this is the second lock). */
    private const FORBIDDEN_DATABASES = ['atgpos', 'atg_pos', 'atg', 'laravel', 'forge', 'homestead', 'mysql', 'sys', 'information_schema', 'performance_schema', 'test', 'testing'];

    /**
     * @return array<string, mixed> what the guard needs to know, read from the running application and process
     */
    public static function contextFromApplication(): array
    {
        $connection = (string) config('database.default');
        $config = (array) config("database.connections.{$connection}", []);

        return [
            'env' => [
                'APP_ENV' => getenv('APP_ENV'),
                'DB_DATABASE' => getenv('DB_DATABASE'),
                self::ENV_OPT_IN => getenv(self::ENV_OPT_IN),
                self::ENV_DESTRUCTIVE => getenv(self::ENV_DESTRUCTIVE),
            ],
            'app_env' => app()->environment(),
            'default_connection' => $connection,
            'connection' => $connection,
            'driver' => $config['driver'] ?? null,
            'host' => $config['host'] ?? null,
            'unix_socket' => $config['unix_socket'] ?? null,
            'database' => $config['database'] ?? null,
            'url' => $config['url'] ?? null,
            'read_write_split' => isset($config['read']) || isset($config['write']),
        ];
    }

    /**
     * Layer 1. Touches nothing.
     *
     * @param  array<string, mixed>  $ctx
     *
     * @throws RealEngineRefused
     */
    public static function assertConfiguration(array $ctx): void
    {
        $env = (array) ($ctx['env'] ?? []);

        if (($env[self::ENV_OPT_IN] ?? false) !== '1') {
            throw RealEngineRefused::notEnabled('Real-engine tests are opt-in ('.self::ENV_OPT_IN.'=1).');
        }

        if (($env[self::ENV_DESTRUCTIVE] ?? false) !== self::DESTRUCTIVE_PHRASE) {
            throw RealEngineRefused::unsafe(self::ENV_DESTRUCTIVE.'='.self::DESTRUCTIVE_PHRASE.' is required: this setup wipes the whole database.');
        }

        if (($env['APP_ENV'] ?? false) !== 'testing' || ($ctx['app_env'] ?? null) !== 'testing') {
            throw RealEngineRefused::unsafe('APP_ENV must be "testing" (both the process environment and the booted application).');
        }

        $connection = $ctx['connection'] ?? null;

        if (! is_string($connection) || ! isset(self::CONNECTIONS[$connection])) {
            throw RealEngineRefused::unsafe('The connection must be "mysql" or "mariadb", got '.json_encode($connection).'.');
        }

        if (($ctx['default_connection'] ?? null) !== $connection) {
            throw RealEngineRefused::unsafe('The default connection is not the verified test connection; a silent fallback to another database is possible.');
        }

        if (($ctx['driver'] ?? null) !== self::CONNECTIONS[$connection]) {
            throw RealEngineRefused::unsafe('The connection driver does not match its name.');
        }

        if (! empty($ctx['url'])) {
            throw RealEngineRefused::unsafe('DB_URL / a connection URL can override the host and database; it must be empty.');
        }

        if (! empty($ctx['read_write_split'])) {
            throw RealEngineRefused::unsafe('A read/write split connection can reach a different server; it is not allowed.');
        }

        self::assertLocalServer($ctx);
        self::assertDatabaseName($ctx);
    }

    /**
     * Layer 2. Only read-only SELECTs, and only after layer 1 passed.
     *
     * @param  array<string, mixed>  $ctx
     *
     * @throws RealEngineRefused
     */
    public static function assertLive(array $ctx, RealEngineProbe $probe): void
    {
        $resolved = ($probe->database)();

        if (! is_string($resolved) || $resolved === '' || $resolved !== $ctx['database']) {
            throw RealEngineRefused::unsafe('The server says the connection uses '.json_encode($resolved).', not the configured '.json_encode($ctx['database']).'.');
        }

        if (preg_match(self::DATABASE_PATTERN, $resolved) !== 1) {
            throw RealEngineRefused::unsafe("The resolved database name {$resolved} is not an atg_void_test_<id> database.");
        }

        $tables = ($probe->tables)();

        if ($tables !== [] && ! in_array(self::MARKER_TABLE, $tables, true)) {
            throw RealEngineRefused::unsafe("{$resolved} already contains tables but is not marked as a disposable test database; it will not be wiped.");
        }

        if ($tables !== [] && ($probe->marker)() !== self::MARKER_TOKEN) {
            throw RealEngineRefused::unsafe("{$resolved} has a marker table with the wrong token; it will not be wiped.");
        }
    }

    /**
     * Runs the destructive setup only after every layer passed, and checks the live database AGAIN immediately
     * before $wipe. Rejection happens before $wipe and $afterWipe are ever called.
     *
     * @param  array<string, mixed>  $ctx
     * @param  callable(): void  $wipe  the destructive step
     * @param  callable(): void  $afterWipe  rebuilds the schema and places the marker
     *
     * @throws RealEngineRefused
     */
    public static function runDestructive(array $ctx, RealEngineProbe $probe, callable $wipe, callable $afterWipe): void
    {
        self::assertConfiguration($ctx);
        self::assertLive($ctx, $probe);

        // Immediately before the destructive command: the world may have changed since the first check.
        self::assertConfiguration($ctx);
        self::assertLive($ctx, $probe);

        $wipe();
        $afterWipe();
    }

    /** @param  array<string, mixed>  $ctx */
    private static function assertLocalServer(array $ctx): void
    {
        $host = $ctx['host'] ?? null;
        $socket = $ctx['unix_socket'] ?? null;

        if (is_string($host) && $host !== '') {
            if (! in_array(strtolower(trim($host)), self::LOCAL_HOSTS, true) || $host !== trim($host)) {
                throw RealEngineRefused::unsafe('Host '.json_encode($host).' is not an allowed local test host ('.implode(', ', self::LOCAL_HOSTS).').');
            }

            return;
        }

        if (is_string($socket) && str_starts_with($socket, '/') && ! str_contains($socket, '..')) {
            return;
        }

        throw RealEngineRefused::unsafe('No local host and no absolute unix socket is configured; the target server is ambiguous.');
    }

    /** @param  array<string, mixed>  $ctx */
    private static function assertDatabaseName(array $ctx): void
    {
        $database = $ctx['database'] ?? null;

        if (! is_string($database) || $database === '' || $database !== trim($database)) {
            throw RealEngineRefused::unsafe('The database name is empty or ambiguous.');
        }

        if (in_array(strtolower($database), self::FORBIDDEN_DATABASES, true) || preg_match(self::DATABASE_PATTERN, $database) !== 1) {
            throw RealEngineRefused::unsafe("Database {$database} is not an atg_void_test_<unique id> database.");
        }

        $envName = ($ctx['env'] ?? [])['DB_DATABASE'] ?? false;

        if (is_string($envName) && $envName !== $database) {
            throw RealEngineRefused::unsafe('DB_DATABASE in the environment and the resolved configuration disagree.');
        }
    }
}
