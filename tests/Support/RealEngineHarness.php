<?php

namespace Tests\Support;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-in harness for tests that need REAL concurrent database sessions (separate PHP processes, committed
 * data) on a disposable MySQL/MariaDB database. SQLite in memory cannot prove row-lock behaviour: its
 * lockForUpdate() is a no-op and everything runs in one connection.
 *
 * Setup DROPS EVERY TABLE of the target database, so it only ever runs through RealEngineGuard::runDestructive():
 * explicit opt-in AND explicit destructive authorization, APP_ENV=testing, a local server, a database named
 * atg_void_test_<unique id> that is empty or carries our marker table, verified again against the live server
 * immediately before the wipe. Prefer a brand-new database per run: tests/Support/real-engine.sh does that.
 *
 * Tests that use this trait must call cleanupRealEngine() from tearDown().
 */
trait RealEngineHarness
{
    protected Outlet $outlet;

    protected Outlet $outlet2;

    protected Warehouse $warehouse;

    protected User $owner;

    protected Ingredient $ingredient;

    protected Ingredient $ingredient2;

    /** @var array<int, array{proc: resource, pipes: array, barrier: string, out: string, err: string, closed: bool}> */
    private array $children = [];

    private bool $rowLockHeld = false;

    protected function bootRealEngine(): void
    {
        $context = RealEngineGuard::contextFromApplication();
        // Every destructive command below is bound to THIS verified connection, never to "whatever is default".
        $connection = (string) $context['connection'];

        try {
            RealEngineGuard::runDestructive(
                $context,
                RealEngineProbe::forConnection($connection),
                fn () => Artisan::call('db:wipe', ['--database' => $connection, '--force' => true]),
                fn () => $this->rebuildDisposableSchema($connection),
            );
        } catch (RealEngineRefused $e) {
            $e->isNotEnabled() ? $this->markTestSkipped($e->getMessage()) : $this->fail($e->getMessage());
        }

        $this->outlet = Outlet::create(['name' => 'Outlet Satu', 'code' => 'O1', 'is_active' => true]);
        $this->outlet2 = Outlet::create(['name' => 'Outlet Dua', 'code' => 'O2', 'is_active' => true]);
        // Warehouse id 1 and outlet id 1 on purpose: the same number in two different places.
        $this->warehouse = Warehouse::create(['name' => 'Gudang', 'code' => 'W1', 'is_active' => true]);

        $role = Role::create(['name' => 'Owner', 'code' => 'owner']);
        $this->owner = User::create([
            'name' => 'owner', 'username' => 'owner', 'email' => 'owner@example.test', 'password' => 'password',
            'role_id' => $role->id, 'outlet_id' => $this->outlet->id, 'is_active' => true,
        ]);
        $this->owner->outlets()->sync([$this->outlet->id, $this->outlet2->id]);

        $category = IngredientCategory::create(['name' => 'Bahan', 'code' => 'BHN', 'is_active' => true]);
        $this->ingredient = $this->makeIngredient($category, 'Gula', 'gram');
        $this->ingredient2 = $this->makeIngredient($category, 'Susu', 'ml');
    }

    /**
     * The repository's migrations cannot be applied from scratch on MySQL with foreign keys on (sales_transactions
     * references cashier_shifts before it exists; SQLite never enforced that), so the disposable schema is built with
     * checks off and they are turned back on, so the tests themselves run with real foreign keys.
     */
    private function rebuildDisposableSchema(string $connection): void
    {
        Schema::connection($connection)->disableForeignKeyConstraints();
        Artisan::call('migrate', ['--database' => $connection, '--force' => true]);
        Schema::connection($connection)->enableForeignKeyConstraints();

        // The marker is what later lets this database be wiped again; no marker, no wipe.
        Schema::connection($connection)->create(RealEngineGuard::MARKER_TABLE, fn (Blueprint $table) => $table->string('token'));
        DB::connection($connection)->table(RealEngineGuard::MARKER_TABLE)->insert(['token' => RealEngineGuard::MARKER_TOKEN]);

        DB::purge($connection);
        DB::reconnect($connection);
    }

    protected function makeIngredient(IngredientCategory $category, string $name, string $unit): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $category->id, 'name' => $name, 'code' => strtoupper($name), 'unit' => $unit,
            'ingredient_type' => Ingredient::TYPE_RAW, 'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => true,
        ]);
        $ingredient->outlets()->sync([$this->outlet->id, $this->outlet2->id]);

        return $ingredient;
    }

    protected function seedBalance(Ingredient $ingredient, string $type, int $id, float|string $qty): void
    {
        DB::table('stock_balances')->updateOrInsert(
            ['ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $id],
            ['qty_on_hand' => $qty, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    /** The stored DECIMAL(12,2) exactly as the database returns it, as text ("10.10"); never a float. */
    protected function balanceExact(Ingredient $ingredient, string $type, int $id): string
    {
        return (string) DB::table('stock_balances')
            ->where(['ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $id])
            ->value('qty_on_hand');
    }

    protected function balance(Ingredient $ingredient, string $type, int $id): string
    {
        return $this->fixed2($this->balanceExact($ingredient, $type, $id));
    }

    /** Σ(qty_in − qty_out) over the movements with id > $afterId for one stock location, computed by the database. */
    protected function ledgerNet(Ingredient $ingredient, string $type, int $id, int $afterId): string
    {
        $row = DB::table('stock_movements')
            ->where(['ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $id])
            ->where('id', '>', $afterId)
            ->selectRaw('COALESCE(SUM(qty_in),0) - COALESCE(SUM(qty_out),0) as net')
            ->first();

        return $this->fixed2((string) $row->net);
    }

    protected function lastMovementId(): int
    {
        return (int) DB::table('stock_movements')->max('id');
    }

    /**
     * The invariant behind every test here: the stored balance moved by exactly what the ledger recorded. A lost
     * update (another writer's change overwritten by a stale value) breaks it even when every request "succeeded".
     * Compared as exact integers (hundredths), never as floats.
     */
    protected function assertReconciled(Ingredient $ingredient, string $type, int $id, string $before, int $afterMovementId, string $label): void
    {
        $this->assertSame(
            $this->hundredths($before) + $this->hundredths($this->ledgerNet($ingredient, $type, $id, $afterMovementId)),
            $this->hundredths($this->balanceExact($ingredient, $type, $id)),
            "{$label}: balance of {$type}:{$id} must equal its starting balance plus the ledger delta (lost update?)"
        );
    }

    /** "10.1" / "10.10" / "-0.2" / 5 => an exact integer number of hundredths. Refuses more than 2 significant decimals. */
    protected function hundredths(string|int|float|null $value): int
    {
        $text = is_string($value) ? trim($value) : number_format((float) $value, 2, '.', '');

        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $text, $m) || (strlen($m[3] ?? '') > 2 && trim(substr($m[3], 2), '0') !== '')) {
            throw new \InvalidArgumentException('Not an exact two-decimal number: '.json_encode($value));
        }

        $number = ((int) $m[2]) * 100 + (int) str_pad(substr($m[3] ?? '', 0, 2), 2, '0');

        return $m[1] === '-' ? -$number : $number;
    }

    /** Canonical text with exactly two decimals, derived from exact hundredths. */
    protected function fixed2(string|int|float|null $value): string
    {
        $cents = $this->hundredths($value);

        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    // ---- child processes: signalled, bounded, always cleaned up -----------------------------------------------

    /**
     * Starts ONE worker. It boots, writes <barrier>.ready, and waits for release() before it sends its request; so
     * what happens when is decided by the test, not by sleep().
     *
     * @param  array{uri: string, method?: string, data?: array, user?: int}  $request
     */
    protected function spawn(array $request): int
    {
        $barrier = sys_get_temp_dir().'/atg_barrier_'.getmypid().'_'.uniqid();
        $connection = (string) config('database.default');
        $db = (array) config("database.connections.{$connection}");

        $env = array_merge(getenv(), [
            'APP_ENV' => 'testing', 'APP_KEY' => (string) config('app.key'), 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array',
            'DB_CONNECTION' => $connection, 'DB_HOST' => (string) ($db['host'] ?? ''), 'DB_PORT' => (string) ($db['port'] ?? ''),
            'DB_SOCKET' => (string) ($db['unix_socket'] ?? ''), 'DB_DATABASE' => (string) $db['database'],
            'DB_USERNAME' => (string) $db['username'], 'DB_PASSWORD' => (string) $db['password'], 'DB_URL' => '',
        ]);

        $proc = proc_open(
            [PHP_BINARY, dirname(__DIR__).'/Support/real_engine_worker.php', $barrier, (string) ($request['user'] ?? $this->owner->id), json_encode($request)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env
        );

        if (! is_resource($proc)) {
            $this->fail('Could not start a worker process.');
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $this->children[] = ['proc' => $proc, 'pipes' => $pipes, 'barrier' => $barrier, 'out' => '', 'err' => '', 'closed' => false];
        $handle = array_key_last($this->children);

        $this->waitFor(fn () => file_exists($barrier.'.ready'), 30, "worker #{$handle} to boot", $handle);

        return $handle;
    }

    /** Lets a spawned worker send its request now. */
    protected function release(int $handle): void
    {
        touch($this->children[$handle]['barrier']);
    }

    /** Waits (bounded) for the worker to finish and returns its decoded result. Never hangs. */
    protected function collect(int $handle, int $timeout = 60): array
    {
        $this->waitFor(function () use ($handle) {
            $this->drain($handle);

            return ! proc_get_status($this->children[$handle]['proc'])['running'];
        }, $timeout, "worker #{$handle} to finish", $handle);

        $this->drain($handle);
        $child = $this->children[$handle];
        $this->closeChild($handle);

        $lines = array_filter(array_map('trim', explode("\n", $child['out'])));
        $decoded = $lines ? json_decode(end($lines), true) : null;

        return is_array($decoded) ? $decoded : ['ok' => false, 'error' => 'worker produced no result. stdout: '.$child['out'].' stderr: '.substr($child['err'], 0, 600)];
    }

    /**
     * Starts one worker per request, waits until every one is booted, releases them all at once and returns their
     * results. For tests that only need "many at the same instant".
     *
     * @param  array<int, array{uri: string, method?: string, data?: array, user?: int}>  $requests
     * @return array<int, array>
     */
    protected function fireTogether(array $requests): array
    {
        $handles = array_map(fn ($request) => $this->spawn($request), $requests);

        foreach ($handles as $handle) {
            $this->release($handle);
        }

        return array_map(fn ($handle) => $this->collect($handle), $handles);
    }

    protected function cleanupRealEngine(): void
    {
        foreach (array_keys($this->children) as $handle) {
            $this->closeChild($handle, terminate: true);
        }

        if ($this->rowLockHeld) {
            try {
                DB::connection('atg_lock')->rollBack();
            } catch (\Throwable) {
            }
            $this->rowLockHeld = false;
        }
    }

    private function drain(int $handle): void
    {
        foreach ([1 => 'out', 2 => 'err'] as $pipe => $key) {
            if (! $this->children[$handle]['closed']) {
                $this->children[$handle][$key] .= (string) stream_get_contents($this->children[$handle]['pipes'][$pipe]);
            }
        }
    }

    private function closeChild(int $handle, bool $terminate = false): void
    {
        $child = &$this->children[$handle];

        if ($child['closed']) {
            return;
        }

        if ($terminate && proc_get_status($child['proc'])['running']) {
            proc_terminate($child['proc'], 9);
        }

        foreach ([1, 2] as $pipe) {
            @fclose($child['pipes'][$pipe]);
        }
        proc_close($child['proc']);
        @unlink($child['barrier']);
        @unlink($child['barrier'].'.ready');
        $child['closed'] = true;
    }

    /** Polls until $condition is true. On timeout kills every child and fails with what the workers said. */
    private function waitFor(callable $condition, int $timeoutSeconds, string $what, ?int $handle = null): void
    {
        $deadline = microtime(true) + $timeoutSeconds;

        while (! $condition()) {
            if (microtime(true) > $deadline) {
                $said = '';
                foreach (array_keys($this->children) as $h) {
                    $this->drain($h);
                    $said .= " [worker #{$h} stdout: {$this->children[$h]['out']} stderr: ".substr($this->children[$h]['err'], 0, 400).']';
                }
                $this->cleanupRealEngine();
                $this->fail("Timed out after {$timeoutSeconds}s waiting for {$what}.{$said}");
            }
            usleep(20_000);
        }
    }

    // ---- deterministic interleaving: pin a worker at a known point with a real row lock ------------------------

    /** Whether the server can tell us who waits on whom (MySQL 8 performance_schema). MariaDB cannot. */
    protected function canObserveLockWaits(): bool
    {
        $present = DB::selectOne("select count(*) as c from information_schema.tables where table_schema = 'performance_schema' and table_name = 'data_lock_waits'");
        $mariadb = str_contains(strtolower((string) DB::selectOne('select @@version_comment as v')->v), 'mariadb') || str_contains(strtolower((string) DB::selectOne('select version() as v')->v), 'mariadb');

        return (int) $present->c === 1 && ! $mariadb;
    }

    /** A second, independent session takes an exclusive lock on one row and keeps it until releaseRowLock(). */
    protected function holdRowLock(string $table, int $id): void
    {
        $connection = (string) config('database.default');
        config(['database.connections.atg_lock' => config("database.connections.{$connection}")]);
        DB::purge('atg_lock');

        $lock = DB::connection('atg_lock');
        $lock->beginTransaction();
        $lock->selectOne("select * from `{$table}` where id = ? for update", [$id]);
        $this->rowLockHeld = true;
    }

    protected function releaseRowLock(): void
    {
        DB::connection('atg_lock')->commit();
        $this->rowLockHeld = false;
    }

    /** How many transactions are right now blocked by a lock held on a row of $table. Read from the server itself. */
    protected function waitersBlockedOn(string $table): int
    {
        return (int) DB::selectOne(
            'select count(distinct w.requesting_engine_transaction_id) as c
               from performance_schema.data_lock_waits w
               join performance_schema.data_locks l on l.engine_lock_id = w.blocking_engine_lock_id
              where l.object_schema = database() and l.object_name = ?',
            [$table]
        )->c;
    }

    /** Waits (bounded) until at least one transaction is observably blocked behind a lock on $table. */
    protected function waitUntilBlockedOn(string $table, int $timeoutSeconds = 30): void
    {
        $this->waitFor(fn () => $this->waitersBlockedOn($table) >= 1, $timeoutSeconds, "a transaction to be blocked on a {$table} lock");
    }
}
