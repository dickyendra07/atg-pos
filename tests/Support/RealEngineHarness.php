<?php

namespace Tests\Support;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-in harness for tests that need REAL concurrent database sessions (separate PHP processes, committed
 * data) on a disposable MySQL/MariaDB database. SQLite in memory cannot prove row-lock behaviour: its
 * lockForUpdate() is a no-op and everything runs in one connection.
 *
 * Enabled only when ALL hold, otherwise every test using it is skipped:
 *   ATG_REAL_ENGINE_TESTS=1, DB_CONNECTION=mysql|mariadb, and the database name starts with "atg_void_test".
 * The name guard exists so this can never wipe a development or production database: setUp() DROPS EVERY TABLE.
 */
trait RealEngineHarness
{
    protected Outlet $outlet;

    protected Outlet $outlet2;

    protected Warehouse $warehouse;

    protected User $owner;

    protected Ingredient $ingredient;

    protected Ingredient $ingredient2;

    protected function bootRealEngine(): void
    {
        if (getenv('ATG_REAL_ENGINE_TESTS') !== '1') {
            $this->markTestSkipped('Real-engine concurrency tests are opt-in (ATG_REAL_ENGINE_TESTS=1).');
        }

        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! in_array($connection, ['mysql', 'mariadb'], true) || ! str_starts_with($database, 'atg_void_test')) {
            $this->fail("Refusing to run: needs a mysql/mariadb database named atg_void_test*, got {$connection}/{$database}.");
        }

        // The repository's migrations cannot be applied from scratch on MySQL with foreign keys on (sales_transactions
        // references cashier_shifts before it exists; SQLite never enforced that), so build the disposable schema with
        // checks off and turn them back on, so the tests themselves run with real foreign keys.
        Artisan::call('db:wipe', ['--force' => true]);
        Schema::disableForeignKeyConstraints();
        Artisan::call('migrate', ['--force' => true]);
        Schema::enableForeignKeyConstraints();
        DB::reconnect();

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

    protected function balance(Ingredient $ingredient, string $type, int $id): string
    {
        return number_format((float) DB::table('stock_balances')
            ->where(['ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $id])
            ->value('qty_on_hand'), 2, '.', '');
    }

    /** Σ(qty_in − qty_out) over the movements with id > $afterId for one stock location. */
    protected function ledgerNet(Ingredient $ingredient, string $type, int $id, int $afterId): string
    {
        $row = DB::table('stock_movements')
            ->where(['ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $id])
            ->where('id', '>', $afterId)
            ->selectRaw('COALESCE(SUM(qty_in),0) - COALESCE(SUM(qty_out),0) as net')
            ->first();

        return number_format((float) $row->net, 2, '.', '');
    }

    protected function lastMovementId(): int
    {
        return (int) DB::table('stock_movements')->max('id');
    }

    /**
     * The invariant behind every test here: the stored balance moved by exactly what the ledger recorded. A lost
     * update (another writer's change overwritten by a stale value) breaks it even when every request "succeeded".
     */
    protected function assertReconciled(Ingredient $ingredient, string $type, int $id, string $before, int $afterMovementId, string $label): void
    {
        $this->assertSame(
            number_format((float) $before + (float) $this->ledgerNet($ingredient, $type, $id, $afterMovementId), 2, '.', ''),
            $this->balance($ingredient, $type, $id),
            "{$label}: balance of {$type}:{$id} must equal its starting balance plus the ledger delta (lost update?)"
        );
    }

    /**
     * Starts one child process per request, releases them together, and returns their decoded results.
     *
     * @param  array<int, array{uri: string, method?: string, data?: array, user?: int}>  $requests
     * @return array<int, array>
     */
    protected function fireTogether(array $requests): array
    {
        $worker = dirname(__DIR__).'/Support/real_engine_worker.php';
        $barrier = sys_get_temp_dir().'/atg_barrier_'.getmypid().'_'.uniqid();
        $connection = config('database.default');
        $db = config("database.connections.{$connection}");

        $env = array_merge(getenv(), [
            'APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array',
            'DB_CONNECTION' => $connection, 'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => (string) $db['password'],
        ]);

        $procs = [];
        foreach ($requests as $i => $request) {
            $procs[$i] = proc_open(
                [PHP_BINARY, $worker, $barrier, (string) ($request['user'] ?? $this->owner->id), json_encode($request)],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i], null, $env
            );
        }

        usleep(1_500_000); // every worker has booted and is polling the barrier
        touch($barrier);

        $results = [];
        foreach ($procs as $i => $proc) {
            $stdout = stream_get_contents($pipes[$i][1]);
            stream_get_contents($pipes[$i][2]);
            proc_close($proc);
            $results[$i] = json_decode(trim(strrchr("\n".trim($stdout), "\n")), true) ?? ['ok' => false, 'error' => 'no output: '.$stdout];
        }

        @unlink($barrier);

        return $results;
    }
}
