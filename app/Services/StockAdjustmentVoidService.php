<?php

namespace App\Services;

use App\Exceptions\StockAdjustmentVoidException;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\QuantityFormatter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * VOID of a Stock Adjustment = reverse the stock impact of the ORIGINAL movements, auditable and exactly once.
 *
 * - The reversal is a DELTA: -(qty_in - qty_out) of each original movement, applied to the balance as it is NOW.
 *   It never sets a balance back to system_qty, so sales/transfers after the adjustment stay accounted for.
 * - Original adjustment, items and movements are never edited or deleted. Each reversal is a new
 *   stock_movements row (movement_type stock_adjustment, reference_type manual_adjustment_void, reference_id =
 *   the adjustment) linked from its item through void_stock_movement_id.
 * - All items or none: any problem refuses the whole VOID and the transaction is rolled back.
 * - All quantities are handled as integers scaled by 100 (the columns are DECIMAL(.,2)); no float is compared.
 *
 * Concurrency (real-engine verified on MySQL, see StockAdjustmentVoidRealEngineTest):
 *   1. lock the adjustment row, 2. claim it with an atomic UPDATE ... WHERE status = 'completed' (exactly one
 *   request can win this, also where row locks do not exist, e.g. SQLite), 3. lock the balance rows in
 *   ingredient order, 4. only then read everything else. Step 4 matters on REPEATABLE READ: the snapshot is created by
 *   the first plain SELECT, so the "newer adjustment" check sees every adjustment committed before our locks.
 *   Every other stock writer reads its balance under lockForUpdate() too, so a VOID cannot be overwritten.
 */
class StockAdjustmentVoidService
{
    public const MOVEMENT_TYPE = 'stock_adjustment';

    public const REFERENCE_TYPE = 'manual_adjustment_void';

    public const VOID_ROLES = ['owner', 'admin_pusat'];

    private const SCALE = 100;

    public function __construct(private readonly BackofficeOutletContext $context) {}

    /** Whether $user may VOID adjustments at all (UI visibility only; void() enforces it again). */
    public function userMayVoid(User $user): bool
    {
        $user->loadMissing(['role', 'roles']);

        return $user->hasAnyRoleCode(self::VOID_ROLES);
    }

    /** Role AND outlet scope. 403 for anything else, whatever the UI showed. */
    public function authorize(User $user, StockAdjustment $adjustment): void
    {
        $user->loadMissing(['role', 'roles', 'outlet', 'outlets']);

        abort_unless($this->userMayVoid($user), 403, 'Hanya Owner dan Admin Pusat yang boleh melakukan VOID adjustment.');

        if ($adjustment->location_type === 'outlet') {
            abort_unless($this->context->canAccess($user, (int) $adjustment->location_id), 403);
        }
    }

    /**
     * Read-only dry run for the confirmation panel. It takes no lock and writes nothing; void() recomputes
     * everything under locks and never trusts this result.
     *
     * @return array{rows: array<int, array>, blockers: string[], can_void: bool}
     */
    public function preview(StockAdjustment $adjustment): array
    {
        if ($adjustment->isVoid()) {
            return ['rows' => [], 'blockers' => ['Adjustment ini sudah berstatus VOID.'], 'can_void' => false];
        }

        $plan = $this->plan($adjustment, false);

        return $plan + ['can_void' => $plan['blockers'] === []];
    }

    /**
     * @throws StockAdjustmentVoidException when the VOID is refused (nothing was changed)
     */
    public function void(StockAdjustment $adjustment, User $actor, string $reason): StockAdjustment
    {
        $this->authorize($actor, $adjustment);

        $reason = trim($reason);

        if ($reason === '') {
            throw new StockAdjustmentVoidException(['Alasan VOID wajib diisi.']);
        }

        // 3 attempts: only a database deadlock is retried (the closure is all-or-nothing and re-validates).
        DB::transaction(function () use ($adjustment, $actor, $reason) {
            // 1. Lock the document. Locking reads/writes only, until the balances are locked (see class comment).
            $locked = StockAdjustment::query()->whereKey($adjustment->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->status !== StockAdjustment::STATUS_COMPLETED) {
                throw StockAdjustmentVoidException::alreadyVoid();
            }

            // 2. Atomic claim: completed -> void. Exactly one request can change a row that is still 'completed'.
            $claimed = StockAdjustment::query()
                ->whereKey($locked->id)
                ->where('status', StockAdjustment::STATUS_COMPLETED)
                ->update([
                    'status' => StockAdjustment::STATUS_VOID,
                    'void_at' => now(),
                    'void_reason' => $reason,
                    'void_by_user_id' => $actor->id,
                    'updated_at' => now(),
                ]);

            if ($claimed !== 1) {
                throw StockAdjustmentVoidException::alreadyVoid();
            }

            // 3. + 4. Lock balances, then validate everything and calculate every reversal.
            $plan = $this->plan($locked, true);

            if ($plan['blockers'] !== []) {
                throw new StockAdjustmentVoidException($plan['blockers']);
            }

            // Apply. Reaching here means every item passed validation under the locks.
            $written = 0;

            foreach ($plan['rows'] as $row) {
                if ($row['reversal'] === 0) {
                    continue;
                }

                $updated = StockBalance::query()->whereKey($row['balance_id'])->update([
                    'qty_on_hand' => $this->fromScaled($row['resulting']),
                    'updated_at' => now(),
                ]);

                $movement = StockMovement::create([
                    'ingredient_id' => $row['ingredient_id'],
                    'location_type' => $locked->location_type,
                    'location_id' => $locked->location_id,
                    'movement_type' => self::MOVEMENT_TYPE,
                    'qty_in' => $row['reversal'] > 0 ? $this->fromScaled($row['reversal']) : '0.00',
                    'qty_out' => $row['reversal'] < 0 ? $this->fromScaled(-$row['reversal']) : '0.00',
                    'reference_type' => self::REFERENCE_TYPE,
                    'reference_id' => $locked->id,
                    'note' => 'VOID '.$locked->reference.' | Original MOV-'.$row['movement_id'].' | Reason: '.$reason,
                ]);

                $linked = StockAdjustmentItem::query()
                    ->whereKey($row['item_id'])
                    ->whereNull('void_stock_movement_id')
                    ->update(['void_stock_movement_id' => $movement->id]);

                if ($updated !== 1 || $linked !== 1) {
                    throw new RuntimeException('VOID dibatalkan: saldo atau link item tidak bisa disimpan secara konsisten.');
                }

                $written++;
            }

            $expected = count(array_filter($plan['rows'], fn ($row) => $row['reversal'] !== 0));

            if ($written !== $expected) {
                throw new RuntimeException('VOID dibatalkan: jumlah movement pembalik tidak sesuai.');
            }
        }, 3);

        return StockAdjustment::query()->findOrFail($adjustment->getKey());
    }

    /**
     * Validates the adjustment and computes the reversal of every item.
     *
     * @return array{rows: array<int, array>, blockers: string[]}
     */
    private function plan(StockAdjustment $adjustment, bool $lock): array
    {
        $blockers = [];

        // Items first, then balances, in ingredient order: the same order for every VOID, so two VOIDs cannot deadlock.
        $items = StockAdjustmentItem::query()
            ->where('stock_adjustment_id', $adjustment->id)
            ->orderBy('ingredient_id')->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        $balances = [];

        foreach ($items as $item) {
            $balances[$item->id] = StockBalance::query()
                ->where('ingredient_id', $item->ingredient_id)
                ->where('location_type', $adjustment->location_type)
                ->where('location_id', $adjustment->location_id)
                ->when($lock, fn ($query) => $query->lockForUpdate())
                ->first();
        }

        // From here on plain reads are fine (the locks are held).
        if ($items->isEmpty()) {
            $blockers[] = 'Adjustment ini tidak punya item; tidak ada yang bisa dibalik.';
        }

        if ($items->pluck('ingredient_id')->duplicates()->isNotEmpty()) {
            $blockers[] = 'Adjustment ini memuat ingredient yang sama lebih dari sekali; data tidak konsisten.';
        }

        $location = match ($adjustment->location_type) {
            'warehouse' => Warehouse::query()->find($adjustment->location_id),
            'outlet' => Outlet::query()->find($adjustment->location_id),
            default => null,
        };

        if (! $location || ! $location->is_active) {
            $blockers[] = 'Lokasi adjustment ('.$adjustment->locationName().') tidak ditemukan atau tidak aktif; VOID diblokir.';
        }

        $rows = [];

        foreach ($items as $item) {
            $ingredient = Ingredient::withTrashed()->find($item->ingredient_id);
            $name = $ingredient?->name ?? ('Ingredient #'.$item->ingredient_id);
            $problems = [];

            try {
                $system = $this->scaled($item->getRawOriginal('system_qty'));
                $actual = $this->scaled($item->getRawOriginal('actual_qty'));
                $difference = $this->scaled($item->getRawOriginal('difference'));
            } catch (RuntimeException $e) {
                $system = $actual = $difference = 0;
                $problems[] = 'qty item tidak valid ('.$e->getMessage().')';
            }

            if (! $ingredient) {
                $problems[] = 'ingredient tidak ditemukan';
            } elseif ($ingredient->trashed()) {
                $problems[] = 'ingredient sudah dihapus dari sistem, jadi VOID otomatis diblokir';
            }

            if ($difference !== $actual - $system) {
                $problems[] = 'selisih item tidak sama dengan aktual dikurangi sistem';
            }

            if ($item->void_stock_movement_id !== null) {
                $problems[] = 'item ini sudah punya movement pembalik';
            }

            $movement = null;
            $reversal = 0;
            $current = null;
            $resulting = null;
            $balance = $balances[$item->id];

            if ($difference === 0) {
                if ($item->stock_movement_id !== null) {
                    $problems[] = 'item tanpa selisih tetapi punya movement';
                }
            } else {
                $movement = $item->stock_movement_id ? StockMovement::query()->find($item->stock_movement_id) : null;

                if (! $movement) {
                    $problems[] = 'movement asli tidak ditemukan';
                } else {
                    foreach ($this->movementProblems($movement, $item, $adjustment, $difference) as $problem) {
                        $problems[] = $problem;
                    }
                }

                if (! $balance) {
                    $problems[] = 'saldo stok di lokasi ini tidak ditemukan';
                } elseif ($problems === []) {
                    $current = $this->scaled($balance->getRawOriginal('qty_on_hand'));
                    // The ORIGINAL movement is the authority. Delta reversal: -(qty_in - qty_out).
                    $reversal = -($this->scaled($movement->getRawOriginal('qty_in')) - $this->scaled($movement->getRawOriginal('qty_out')));
                    $resulting = $current + $reversal;

                    // VOID may never lower a balance below zero. (A balance that is already negative may still be
                    // raised: that does not cause a negative balance.)
                    if ($reversal < 0 && $resulting < 0) {
                        $problems[] = 'stok sekarang '.$this->display($current).' '.($ingredient->unit ?? '')
                            .' tidak cukup untuk membalik '.$this->display(-$reversal).' (hasilnya '.$this->display($resulting).')';
                    }
                }
            }

            foreach ($problems as $problem) {
                $blockers[] = $name.': '.$problem.'.';
            }

            $rows[] = [
                'item_id' => $item->id,
                'ingredient_id' => $item->ingredient_id,
                'ingredient_name' => $name,
                'unit' => $item->unit ?: ($ingredient?->unit ?? ''),
                'original' => $difference,
                'reversal' => $reversal,
                'current' => $current,
                'resulting' => $resulting,
                'movement_id' => $movement?->id,
                'balance_id' => $balance?->id,
                'problems' => $problems,
            ];
        }

        // Last, and as a plain read: an adjustment committed after this one for the same ingredient and location
        // has re-counted the stock, so this one may no longer be reversed (void the newer one first).
        if ($items->isNotEmpty()) {
            $newer = StockAdjustment::query()
                ->where('id', '>', $adjustment->id)
                ->where('location_type', $adjustment->location_type)
                ->where('location_id', $adjustment->location_id)
                ->where('status', '!=', StockAdjustment::STATUS_VOID)
                ->whereHas('items', fn ($query) => $query->whereIn('ingredient_id', $items->pluck('ingredient_id')))
                ->orderBy('id')
                ->get(['id', 'reference']);

            foreach ($newer as $other) {
                $blockers[] = 'Adjustment '.$other->reference.' yang lebih baru untuk ingredient dan lokasi yang sama masih aktif; VOID adjustment tersebut terlebih dahulu.';
            }
        }

        return ['rows' => $rows, 'blockers' => array_values(array_unique($blockers))];
    }

    /** @return string[] */
    private function movementProblems(StockMovement $movement, StockAdjustmentItem $item, StockAdjustment $adjustment, int $difference): array
    {
        $problems = [];

        if ((int) $movement->ingredient_id !== (int) $item->ingredient_id) {
            $problems[] = 'movement asli milik ingredient lain';
        }

        if ($movement->location_type !== $adjustment->location_type || (int) $movement->location_id !== (int) $adjustment->location_id) {
            $problems[] = 'movement asli milik lokasi lain';
        }

        if ($movement->movement_type !== self::MOVEMENT_TYPE) {
            $problems[] = 'tipe movement asli bukan stock_adjustment';
        }

        if ($movement->reference_type !== 'manual_adjustment' || (int) $movement->reference_id !== (int) $adjustment->id) {
            $problems[] = 'movement asli tidak merujuk ke adjustment ini';
        }

        try {
            $in = $this->scaled($movement->getRawOriginal('qty_in'));
            $out = $this->scaled($movement->getRawOriginal('qty_out'));

            if ($in < 0 || $out < 0 || ($in > 0 && $out > 0)) {
                $problems[] = 'qty movement asli tidak valid';
            } elseif ($in - $out !== $difference) {
                $problems[] = 'qty movement asli tidak sama dengan selisih item';
            }
        } catch (RuntimeException $e) {
            $problems[] = 'qty movement asli tidak valid ('.$e->getMessage().')';
        }

        $shared = StockAdjustmentItem::query()
            ->where('stock_movement_id', $movement->id)
            ->where('id', '!=', $item->id)
            ->exists();

        if ($shared) {
            $problems[] = 'movement asli dipakai item lain';
        }

        $alreadyReversed = StockMovement::query()
            ->where('movement_type', self::MOVEMENT_TYPE)
            ->where('reference_type', self::REFERENCE_TYPE)
            ->where('reference_id', $adjustment->id)
            ->where('ingredient_id', $item->ingredient_id)
            ->exists();

        if ($alreadyReversed) {
            $problems[] = 'sudah ada movement pembalik untuk ingredient ini';
        }

        return $problems;
    }

    /**
     * Exact conversion of a DECIMAL(.,2) value to an integer number of hundredths. Strings (MySQL) are parsed digit
     * by digit; a non-string (SQLite returns numbers) is fixed to two decimals first. A value with more than two
     * significant decimals is refused instead of being rounded.
     */
    private function scaled(mixed $value): int
    {
        $text = is_string($value) ? trim($value) : number_format((float) $value, 2, '.', '');

        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $text, $m)) {
            throw new RuntimeException('bukan angka: '.$text);
        }

        $fraction = $m[3] ?? '';

        if (strlen($fraction) > 2 && trim(substr($fraction, 2), '0') !== '') {
            throw new RuntimeException('lebih dari 2 desimal: '.$text);
        }

        $number = ((int) $m[2]) * self::SCALE + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $m[1] === '-' ? -$number : $number;
    }

    /** The same number format the Back Office screens use (1.234,50). */
    private function display(int $scaled): string
    {
        return QuantityFormatter::twoDecimals($scaled / self::SCALE);
    }

    private function fromScaled(int $value): string
    {
        return ($value < 0 ? '-' : '').intdiv(abs($value), self::SCALE).'.'.str_pad((string) (abs($value) % self::SCALE), 2, '0', STR_PAD_LEFT);
    }
}
