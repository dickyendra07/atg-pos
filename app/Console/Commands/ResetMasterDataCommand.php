<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetMasterDataCommand extends Command
{
    protected $signature = 'atg:reset-master-data
        {--force : Jalankan tanpa konfirmasi interaktif}
        {--keep-promo : Jangan hapus promo dan discount}';

    protected $description = 'Mengosongkan data transaksi, stock, recipe, product, variant, dan ingredient untuk import master data baru.';

    public function handle(): int
    {
        $this->warn('PERINGATAN: Command ini akan mengosongkan data operasional dan master data ATG POS.');
        $this->line('Data user, role, outlet, dan warehouse tidak akan dihapus.');

        if (! $this->option('force')) {
            if (! $this->confirm('Lanjutkan reset master data?', false)) {
                $this->info('Reset dibatalkan.');
                return self::SUCCESS;
            }

            $confirmation = $this->ask('Ketik RESET untuk konfirmasi final');

            if ($confirmation !== 'RESET') {
                $this->info('Reset dibatalkan karena konfirmasi tidak sesuai.');
                return self::SUCCESS;
            }
        }

        $tables = [
            'sales_transaction_items',
            'sales_transactions',
            'cashier_shifts',

            'stock_movements',
            'stock_balances',
            'stock_transfers',

            'recipe_items',
            'recipes',

            'product_variants',
            'products',

            'ingredients',
            'ingredient_categories',

            'backoffice_notifications',
            'approval_pins',
        ];

        if (! $this->option('keep-promo')) {
            $tables = array_merge([
                'promo_rewards',
                'promo_requirements',
                'promos',
                'discounts',
            ], $tables);
        }

        $driver = DB::connection()->getDriverName();

        $this->disableForeignKeyChecks($driver);

        try {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    $this->warn("Skip: tabel {$table} tidak ditemukan.");
                    continue;
                }

                DB::table($table)->truncate();
                $this->info("Truncated: {$table}");
            }
        } finally {
            $this->enableForeignKeyChecks($driver);
        }

        $this->newLine();
        $this->info('Reset master data selesai.');
        $this->line('Urutan import yang disarankan:');
        $this->line('1. Ingredients');
        $this->line('2. Products');
        $this->line('3. Variants');
        $this->line('4. Recipes');
        $this->line('5. Stock Balances');

        return self::SUCCESS;
    }

    protected function disableForeignKeyChecks(string $driver): void
    {
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            return;
        }

        Schema::disableForeignKeyConstraints();
    }

    protected function enableForeignKeyChecks(string $driver): void
    {
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            return;
        }

        Schema::enableForeignKeyConstraints();
    }
}
