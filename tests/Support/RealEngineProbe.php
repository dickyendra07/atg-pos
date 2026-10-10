<?php

namespace Tests\Support;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Read-only questions the guard asks the database server itself, instead of trusting configuration values.
 * Closures, so the guard can be tested with a fake that records every call.
 */
final class RealEngineProbe
{
    /**
     * @param  Closure(): ?string  $database  the database the live connection is really using
     * @param  Closure(): string[]  $tables  every table in that database
     * @param  Closure(): ?string  $marker  the token stored in the disposable-database marker table, if any
     */
    public function __construct(
        public readonly Closure $database,
        public readonly Closure $tables,
        public readonly Closure $marker,
    ) {}

    /** A probe bound to ONE named connection (never the default one). All three questions are plain SELECTs. */
    public static function forConnection(string $connection): self
    {
        return new self(
            fn () => DB::connection($connection)->selectOne('select database() as db')->db ?? null,
            fn () => array_map(
                fn ($row) => (string) $row->name,
                DB::connection($connection)->select('select table_name as name from information_schema.tables where table_schema = database()')
            ),
            function () use ($connection) {
                $exists = DB::connection($connection)->selectOne(
                    'select 1 as present from information_schema.tables where table_schema = database() and table_name = ?',
                    [RealEngineGuard::MARKER_TABLE]
                );

                return $exists ? (DB::connection($connection)->table(RealEngineGuard::MARKER_TABLE)->value('token')) : null;
            },
        );
    }
}
