<?php

namespace App\Http\Controllers\Backoffice;

use App\Exceptions\TransferOperationBusy;
use App\Exceptions\TransferOperationConflict;
use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\TransferOperation;
use App\Models\Warehouse;
use App\Services\BackofficeOutletContext;
use App\Services\TransferOperationService;
use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class TransferViewController extends Controller
{
    /** Bounded attempts for a whole transition/posting transaction; only a deadlock/lock-timeout is ever retried. */
    private const TRANSACTION_ATTEMPTS = 3;

    private const LOCATION_TYPES = ['warehouse', 'outlet'];

    private const OPERATION_KEY_MESSAGE = 'Halaman form transfer sudah kedaluwarsa. Muat ulang halaman lalu isi dan simpan lagi.';

    private const BUSY_MESSAGE = 'Transfer sedang diproses oleh permintaan lain. Tidak ada stok yang berubah, silakan coba lagi.';

    protected function authorizeAccess()
    {
        $user = Auth::user()->load(['role', 'outlet']);

        $allowedRoles = [
            'owner',
            'admin_pusat',
            'admin_outlet',
            'staff_gudang',
        ];

        if (! in_array($user->role?->code, $allowedRoles)) {
            abort(403, 'Role kamu tidak punya akses ke halaman Transfer.');
        }

        return $user;
    }

    protected function parseLocation(string $value): array
    {
        $parts = explode(':', $value);

        if (count($parts) !== 2) {
            abort(422, 'Format lokasi tidak valid.');
        }

        $type = $parts[0];
        $id = (int) $parts[1];

        if (! in_array($type, ['warehouse', 'outlet'])) {
            abort(422, 'Tipe lokasi tidak valid.');
        }

        if ($id <= 0) {
            abort(422, 'ID lokasi tidak valid.');
        }

        return [
            'type' => $type,
            'id' => $id,
        ];
    }

    protected function getLocationName(string $type, int $id): string
    {
        if ($type === 'warehouse') {
            return Warehouse::find($id)?->name ?? ('Warehouse #' . $id);
        }

        if ($type === 'outlet') {
            return Outlet::find($id)?->name ?? ('Outlet #' . $id);
        }

        return '-';
    }

    protected function buildLocationOptions($user)
    {
        $warehouses = Warehouse::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($warehouse) {
                return [
                    'value' => 'warehouse:' . $warehouse->id,
                    'label' => 'Gudang – ' . $warehouse->name,
                ];
            });

        $context = app(BackofficeOutletContext::class);
        $outlets = $context->accessibleOutlets($user)
            ->map(function ($outlet) {
                return [
                    'value' => 'outlet:' . $outlet->id,
                    'label' => 'Outlet – ' . $outlet->name,
                ];
            });

        return $warehouses->concat($outlets)->values();
    }

    // Outlets that give a transfer its outlet scope: the outlet source/destination, plus outlet_id
    // for legacy rows saved without from/to location columns. Warehouses are not scoped per user,
    // so a warehouse endpoint alone never puts a transfer in a limited user's scope.
    protected function scopeToInvolvedOutlets($query, array $outletIds): void
    {
        $query->where(function ($scope) use ($outletIds) {
            $scope->where(fn ($q) => $q->where('from_location_type', 'outlet')->whereIn('from_location_id', $outletIds))
                ->orWhere(fn ($q) => $q->where('to_location_type', 'outlet')->whereIn('to_location_id', $outletIds))
                ->orWhereIn('outlet_id', $outletIds);
        });
    }

    // Shared by index() and exportCsv() so the list and the CSV can never disagree. A specific
    // Active Outlet (already limited to accessible outlets) narrows to that outlet; otherwise
    // full-access roles see every transfer and other roles only their accessible outlets.
    protected function filteredTransfersQuery(Request $request, $user)
    {
        $query = StockTransfer::query()->latest();

        $context = app(BackofficeOutletContext::class);
        $activeOutletId = $context->activeOutletId($user);

        if ($activeOutletId) {
            $this->scopeToInvolvedOutlets($query, [$activeOutletId]);
        } elseif (! $user->isFullAccessUser()) {
            $this->scopeToInvolvedOutlets($query, $context->accessibleOutlets($user)->pluck('id')->map(fn ($id) => (int) $id)->all());
        }

        if ($request->filled('from_location')) {
            $fromFilter = $this->parseLocation($request->from_location);
            $query->where('from_location_type', $fromFilter['type'])
                ->where('from_location_id', $fromFilter['id']);
        } elseif ($request->filled('from_location_type')) {
            $query->where('from_location_type', $request->from_location_type);
        }

        if ($request->filled('to_location')) {
            $toFilter = $this->parseLocation($request->to_location);
            $query->where('to_location_type', $toFilter['type'])
                ->where('to_location_id', $toFilter['id']);
        } elseif ($request->filled('to_location_type')) {
            $query->where('to_location_type', $request->to_location_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query;
    }

    // Status actions use the same involved-outlet rule as the list: full-access roles keep acting
    // on every transfer, other roles only on transfers that involve an outlet they can access.
    protected function authorizeTransferAction($user, StockTransfer $transfer): void
    {
        if ($user->isFullAccessUser()) {
            return;
        }

        $involvedOutletIds = collect([
            $transfer->from_location_type === 'outlet' ? $transfer->from_location_id : null,
            $transfer->to_location_type === 'outlet' ? $transfer->to_location_id : null,
            $transfer->outlet_id,
        ])->filter()->map(fn ($id) => (int) $id)->unique();

        $context = app(BackofficeOutletContext::class);

        if ($involvedOutletIds->contains(fn ($outletId) => $context->canAccess($user, $outletId))) {
            return;
        }

        abort(403, 'Kamu tidak punya akses ke outlet yang terlibat dalam transfer ini.');
    }

    /**
     * Runs one status transition under a row lock on the REAL stock_transfers row.
     *
     * The lock is taken first in every transition; the stock balances come second (see lockStockBalances()). The
     * status the transition decides on is the one read from the locked row, never the route-bound model: that one was
     * loaded before the request had any lock and can be arbitrarily stale. A concurrent request for the same
     * transfer waits here and then sees the committed result, so it can no longer post the same stock twice.
     *
     * The whole closure is atomic and may be run again after a deadlock, so it must stay free of side effects
     * outside the database. Permission is checked again on the locked row.
     */
    protected function withLockedTransfer(StockTransfer $routeTransfer, $user, \Closure $transition): mixed
    {
        return DB::transaction(function () use ($routeTransfer, $user, $transition) {
            $transfer = StockTransfer::whereKey($routeTransfer->getKey())->lockForUpdate()->firstOrFail();

            $this->authorizeTransferAction($user, $transfer);

            return $transition($transfer);
        }, self::TRANSACTION_ATTEMPTS);
    }

    /** Redirect for a failed transition: HTTP/404 errors keep their meaning, a lock failure gets a safe message. */
    protected function transitionFailure(\Throwable $e)
    {
        if ($e instanceof HttpExceptionInterface || $e instanceof ModelNotFoundException) {
            throw $e;
        }

        return redirect()
            ->route('backoffice.transfers.index')
            ->with('error', $this->isConcurrencyFailure($e) ? self::BUSY_MESSAGE : $e->getMessage());
    }

    /** Deadlock / lock wait timeout that survived every attempt (the raw SQL text must never reach the screen). */
    protected function isConcurrencyFailure(\Throwable $e): bool
    {
        return $e instanceof DeadlockException || (new ConcurrencyErrorDetector())->causedByConcurrencyError($e);
    }

    /** A transfer can only move stock when it knows both of its locations (legacy rows may not). */
    protected function assertHasStockLocations(StockTransfer $transfer): void
    {
        foreach (['from', 'to'] as $side) {
            if (
                ! in_array($transfer->{$side.'_location_type'}, self::LOCATION_TYPES, true)
                || (int) $transfer->{$side.'_location_id'} <= 0
            ) {
                throw new \RuntimeException('Transfer lama ini tidak punya data lokasi asal/tujuan, jadi stoknya tidak bisa dibatalkan atau diaktifkan lagi.');
            }
        }
    }

    protected function balanceKey(int $ingredientId, string $type, int $locationId): string
    {
        return $ingredientId.'|'.$type.'|'.$locationId;
    }

    /**
     * Locks every stock balance an operation touches, in ONE global order, whatever the direction of the transfer:
     * ingredient, then warehouse before outlet, then location id. Two transfers in opposite directions (or bulk
     * transfers listing their items differently) therefore always queue behind each other and can never wait in a
     * circle. Warehouse comes before outlet because the older warehouse-to-outlet screen locks in that order.
     *
     * A row that may not exist yet ($create) and really is missing is created with a single INSERT ... ON
     * DUPLICATE KEY UPDATE: race-free against the unique balance identity (two requests creating the same row end
     * up with one row). It is deliberately NOT "select for update, then insert": two requests would each take a
     * gap lock on the missing key and then deadlock on the insert. The row is created inside the caller's
     * transaction, so a rollback removes it again. A row that must exist is only locked; when it is absent the
     * given message is thrown. Every row is finally read with lockForUpdate(), which returns the latest committed
     * values and keeps the row locked until the transaction ends.
     *
     * 'missing' is the message of the RuntimeException thrown for an absent row, or a Closure returning the exception
     * to throw (store() uses it for a friendly validation error).
     *
     * @param  array<int, array{ingredient_id: int, type: string, id: int, create: bool, missing: string|\Closure}>  $specs
     * @return array<string, StockBalance> the locked balances, by balanceKey()
     */
    protected function lockStockBalances(array $specs): array
    {
        $unique = [];

        foreach ($specs as $spec) {
            $key = $this->balanceKey($spec['ingredient_id'], $spec['type'], $spec['id']);

            if (isset($unique[$key])) {
                $unique[$key]['create'] = $unique[$key]['create'] || $spec['create'];

                continue;
            }

            $unique[$key] = $spec;
        }

        uasort($unique, fn (array $a, array $b) => [$a['ingredient_id'], $a['type'] === 'warehouse' ? 0 : 1, $a['id']]
            <=> [$b['ingredient_id'], $b['type'] === 'warehouse' ? 0 : 1, $b['id']]);

        $locked = [];

        foreach ($unique as $key => $spec) {
            $identity = [
                'ingredient_id' => $spec['ingredient_id'],
                'location_type' => $spec['type'],
                'location_id' => $spec['id'],
            ];

            // Only a genuinely missing row is created. The usual case (the row exists) takes the plain read and
            // never reaches the insert, which would otherwise queue behind unrelated locks on the end of the table
            // and burn an auto-increment id on every call. A stale "missing" answer is harmless: the upsert simply
            // finds the row.
            if ($spec['create'] && ! StockBalance::where($identity)->exists()) {
                StockBalance::upsert([$identity + ['qty_on_hand' => 0]], array_keys($identity), ['ingredient_id']);
            }

            $balance = StockBalance::where($identity)->lockForUpdate()->first();

            if (! $balance) {
                throw $spec['missing'] instanceof \Closure ? ($spec['missing'])() : new \RuntimeException($spec['missing']);
            }

            $locked[$key] = $balance;
        }

        return $locked;
    }

    protected function balanceSpec(int $ingredientId, string $type, int $locationId, bool $create, string|\Closure $missing = ''): array
    {
        return ['ingredient_id' => $ingredientId, 'type' => $type, 'id' => $locationId, 'create' => $create, 'missing' => $missing];
    }

    protected function rollbackTransferStock(StockTransfer $transfer): void
    {
        $this->assertHasStockLocations($transfer);

        $transferQty = (float) $transfer->qty;
        $ingredientId = (int) $transfer->ingredient_id;
        $fromId = (int) $transfer->from_location_id;
        $toId = (int) $transfer->to_location_id;

        $locked = $this->lockStockBalances([
            $this->balanceSpec($ingredientId, $transfer->from_location_type, $fromId, true),
            $this->balanceSpec($ingredientId, $transfer->to_location_type, $toId, false, 'Stock lokasi tujuan tidak ditemukan untuk rollback transfer.'),
        ]);

        $sourceStock = $locked[$this->balanceKey($ingredientId, $transfer->from_location_type, $fromId)];
        $destinationStock = $locked[$this->balanceKey($ingredientId, $transfer->to_location_type, $toId)];

        $currentDestinationQty = (float) $destinationStock->qty_on_hand;

        if ($currentDestinationQty < $transferQty) {
            throw new \RuntimeException(
                'Transfer item ini tidak bisa dibatalkan karena stock di lokasi tujuan sudah berubah dan tidak cukup untuk rollback.'
            );
        }

        $sourceStock->update([
            'qty_on_hand' => (float) $sourceStock->qty_on_hand + $transferQty,
        ]);

        $destinationStock->update([
            'qty_on_hand' => $currentDestinationQty - $transferQty,
        ]);

        $fromName = $this->getLocationName((string) $transfer->from_location_type, (int) $transfer->from_location_id);
        $toName = $this->getLocationName((string) $transfer->to_location_type, (int) $transfer->to_location_id);

        StockMovement::create([
            'ingredient_id' => $transfer->ingredient_id,
            'location_type' => $transfer->from_location_type,
            'location_id' => $transfer->from_location_id,
            'movement_type' => 'transfer_cancel_return',
            'qty_in' => $transferQty,
            'qty_out' => 0,
            'reference_type' => 'general_transfer_cancel',
            'reference_id' => $transfer->id,
            'note' => 'Rollback cancel transfer item #' . $transfer->transfer_number . ' kembali ke ' . $fromName . ' dari ' . $toName,
        ]);

        StockMovement::create([
            'ingredient_id' => $transfer->ingredient_id,
            'location_type' => $transfer->to_location_type,
            'location_id' => $transfer->to_location_id,
            'movement_type' => 'transfer_cancel_out',
            'qty_in' => 0,
            'qty_out' => $transferQty,
            'reference_type' => 'general_transfer_cancel',
            'reference_id' => $transfer->id,
            'note' => 'Rollback cancel transfer item #' . $transfer->transfer_number . ' keluar dari ' . $toName . ' kembali ke ' . $fromName,
        ]);
    }

    protected function applyTransferStockAgain(StockTransfer $transfer): void
    {
        $this->assertHasStockLocations($transfer);

        $transferQty = (float) $transfer->qty;
        $ingredientId = (int) $transfer->ingredient_id;
        $fromId = (int) $transfer->from_location_id;
        $toId = (int) $transfer->to_location_id;

        $locked = $this->lockStockBalances([
            $this->balanceSpec($ingredientId, $transfer->from_location_type, $fromId, false, 'Stock lokasi asal tidak ditemukan untuk mengaktifkan ulang transfer.'),
            $this->balanceSpec($ingredientId, $transfer->to_location_type, $toId, true),
        ]);

        $sourceStock = $locked[$this->balanceKey($ingredientId, $transfer->from_location_type, $fromId)];
        $destinationStock = $locked[$this->balanceKey($ingredientId, $transfer->to_location_type, $toId)];

        $currentSourceQty = (float) $sourceStock->qty_on_hand;

        if ($currentSourceQty < $transferQty) {
            throw new \RuntimeException(
                'Transfer item ini tidak bisa diaktifkan lagi karena stock asal sekarang tidak cukup.'
            );
        }

        $sourceStock->update([
            'qty_on_hand' => $currentSourceQty - $transferQty,
        ]);

        $destinationStock->update([
            'qty_on_hand' => (float) $destinationStock->qty_on_hand + $transferQty,
        ]);

        $fromName = $this->getLocationName((string) $transfer->from_location_type, (int) $transfer->from_location_id);
        $toName = $this->getLocationName((string) $transfer->to_location_type, (int) $transfer->to_location_id);

        StockMovement::create([
            'ingredient_id' => $transfer->ingredient_id,
            'location_type' => $transfer->from_location_type,
            'location_id' => $transfer->from_location_id,
            'movement_type' => 'transfer_out_reactivated',
            'qty_in' => 0,
            'qty_out' => $transferQty,
            'reference_type' => 'general_transfer_reactivated',
            'reference_id' => $transfer->id,
            'note' => 'Transfer item #' . $transfer->transfer_number . ' diaktifkan lagi: keluar dari ' . $fromName . ' ke ' . $toName,
        ]);

        StockMovement::create([
            'ingredient_id' => $transfer->ingredient_id,
            'location_type' => $transfer->to_location_type,
            'location_id' => $transfer->to_location_id,
            'movement_type' => 'transfer_in_reactivated',
            'qty_in' => $transferQty,
            'qty_out' => 0,
            'reference_type' => 'general_transfer_reactivated',
            'reference_id' => $transfer->id,
            'note' => 'Transfer item #' . $transfer->transfer_number . ' diaktifkan lagi: masuk ke ' . $toName . ' dari ' . $fromName,
        ]);
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess();

        $query = $this->filteredTransfersQuery($request, $user)
            ->with(['ingredient.category', 'warehouse', 'outlet', 'transferredBy']);

        $transfers = $query->get()->map(function ($transfer) {
            $transfer->from_location_name = $this->getLocationName(
                (string) $transfer->from_location_type,
                (int) $transfer->from_location_id
            );

            $transfer->to_location_name = $this->getLocationName(
                (string) $transfer->to_location_type,
                (int) $transfer->to_location_id
            );

            return $transfer;
        });

        $summary = [
            'total' => $transfers->count(),
            'in_transit' => $transfers->where('status', 'in_transit')->count(),
            'received' => $transfers->where('status', 'received')->count(),
            'cancelled' => $transfers->where('status', 'cancelled')->count(),
        ];

        $transferGroups = $transfers
            ->groupBy(function ($transfer) {
                $transferNumber = (string) ($transfer->transfer_number ?? '');

                return preg_replace('/-\\d+$/', '', $transferNumber) ?: $transferNumber;
            })
            ->map(function ($items, $groupNumber) {
                $first = $items->first();
                $statuses = $items->pluck('status')->filter()->unique()->values();

                return [
                    'group_number' => $groupNumber,
                    'date' => $first?->created_at,
                    'from_location_type' => $first?->from_location_type,
                    'from_location_name' => $first?->from_location_name,
                    'to_location_type' => $first?->to_location_type,
                    'to_location_name' => $first?->to_location_name,
                    'sender_name' => $first?->sender_name,
                    'transferred_by' => $first?->transferredBy?->name,
                    'status' => $statuses->count() === 1 ? $statuses->first() : 'mixed',
                    'item_count' => $items->count(),
                    'total_qty' => (float) $items->sum('qty'),
                    'items' => $items->values(),
                ];
            })
            ->values();

        return view('backoffice.transfers.index', [
            'user' => $user,
            'transfers' => $transfers,
            'transferGroups' => $transferGroups,
            'summary' => $summary,
            'locationOptions' => $this->buildLocationOptions($user),
            'filters' => [
                'from_location_type' => $request->from_location_type,
                'to_location_type' => $request->to_location_type,
                'from_location' => $request->from_location,
                'to_location' => $request->to_location,
                'status' => $request->status,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $this->authorizeAccess();

        $query = $this->filteredTransfersQuery($request, $user)
            ->with(['ingredient.category', 'transferredBy']);

        $transfers = $query->get()->map(function ($transfer) {
            $transfer->from_location_name = $this->getLocationName(
                (string) $transfer->from_location_type,
                (int) $transfer->from_location_id
            );

            $transfer->to_location_name = $this->getLocationName(
                (string) $transfer->to_location_type,
                (int) $transfer->to_location_id
            );

            return $transfer;
        });

        $filename = 'transfers_export_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($transfers) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'transfer_number',
                'created_at',
                'from_location_type',
                'from_location_name',
                'to_location_type',
                'to_location_name',
                'ingredient_category',
                'ingredient_name',
                'qty',
                'status',
                'sender_name',
                'receiver_name',
                'sent_at',
                'received_at',
                'input_by',
                'note',
            ]);

            foreach ($transfers as $transfer) {
                fputcsv($handle, [
                    $transfer->transfer_number ?? '',
                    $transfer->created_at?->format('Y-m-d H:i:s') ?? '',
                    $transfer->from_location_type ?? '',
                    $transfer->from_location_name ?? '',
                    $transfer->to_location_type ?? '',
                    $transfer->to_location_name ?? '',
                    $transfer->ingredient->category->name ?? '',
                    $transfer->ingredient->name ?? '',
                    (float) $transfer->qty,
                    $transfer->status ?? '',
                    $transfer->sender_name ?? '',
                    $transfer->receiver_name ?? '',
                    $transfer->sent_at?->format('Y-m-d H:i:s') ?? '',
                    $transfer->received_at?->format('Y-m-d H:i:s') ?? '',
                    $transfer->transferredBy->name ?? '',
                    $transfer->note ?? '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function create(Request $request)
    {
        $user = $this->authorizeAccess();

        $locationOptions = $this->buildLocationOptions($user);

        $prefillFromLocation = null;

        if ($request->filled('from_location_type') && $request->filled('from_location_id')) {
            $prefillFromLocation = $request->from_location_type . ':' . $request->from_location_id;
        }

        return view('backoffice.transfers.create', [
            'user' => $user,
            'locationOptions' => $locationOptions,
            'prefillFromLocation' => $prefillFromLocation,
            'defaultSenderName' => $user->name,
            'oldItems' => old('items', []),
            'operationKey' => $this->operationKeyForForm(old('operation_key')),
        ]);
    }

    public function availableIngredients(Request $request)
    {
        $user = $this->authorizeAccess();

        $request->validate([
            'location' => 'required|string',
        ]);

        $location = $this->parseLocation($request->location);

        // Same scope as the Create form options: warehouses are global for every Transfer role,
        // outlets only when accessible (full-access roles keep reading any outlet as before).
        if (
            $location['type'] === 'outlet'
            && ! $user->isFullAccessUser()
            && ! app(BackofficeOutletContext::class)->canAccess($user, $location['id'])
        ) {
            abort(403, 'Kamu tidak punya akses ke stok outlet ini.');
        }

        $stockBalances = StockBalance::withLiveIngredient()->with(['ingredient.category'])
            ->where('location_type', $location['type'])
            ->where('location_id', $location['id'])
            ->where('qty_on_hand', '>', 0)
            ->get()
            ->filter(function ($stockBalance) {
                return $stockBalance->ingredient !== null;
            })
            ->sortBy(function ($stockBalance) {
                return strtolower((string) $stockBalance->ingredient->name);
            })
            ->values();

        $items = $stockBalances->map(function ($stockBalance) {
            $ingredient = $stockBalance->ingredient;

            return [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'unit' => $ingredient->unit,
                'stock' => (float) $stockBalance->qty_on_hand,
                'label' => $ingredient->name
                    . ' - ' . ($ingredient->category->name ?? '-')
                    . ' - ' . $ingredient->unit
                    . ' | Stock: ' . number_format((float) $stockBalance->qty_on_hand, 0, ',', '.'),
            ];
        })->values();

        return response()->json([
            'items' => $items,
        ]);
    }

    /**
     * One create-form render = one operation key. A key that came back through old() (validation failure, Back
     * button) is kept so the SAME operation can be retried safely; anything that is not a UUID is replaced.
     */
    protected function operationKeyForForm(mixed $old): string
    {
        return is_string($old) && Str::isUuid($old) ? strtolower($old) : (string) Str::uuid();
    }

    public function store(Request $request)
    {
        $user = $this->authorizeAccess();

        // 1-2. Structure only: shape and format. Nothing here depends on mutable stock or master data, so a replay of
        // an operation that already committed is never rejected by it.
        $validated = $request->validate([
            'operation_key' => ['required', 'uuid'],
            'from_location' => 'required|string',
            'to_location' => 'required|string',
            'sender_name' => 'required|string|max:100',
            'receiver_name' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => 'required|integer|min:1',
            'items.*.qty' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
        ], [
            'operation_key.required' => self::OPERATION_KEY_MESSAGE,
            'operation_key.uuid' => self::OPERATION_KEY_MESSAGE,
            'items.required' => 'Minimal harus ada 1 item transfer.',
            'items.*.ingredient_id.required' => 'Ingredient wajib dipilih di setiap baris.',
            'items.*.qty.required' => 'Qty transfer wajib diisi di setiap baris.',
            'items.*.qty.decimal' => 'Qty transfer maksimal 2 angka di belakang koma.',
        ]);

        // 3. Current authorization. This runs for a replay exactly as for a new request: a key never lets anyone
        // past the role or outlet scope they have right now.
        $from = $this->parseLocation($validated['from_location']);
        $to = $this->parseLocation($validated['to_location']);
        $context = app(BackofficeOutletContext::class);

        foreach ([$from, $to] as $locationContext) {
            if ($locationContext['type'] === 'outlet' && ! $context->canAccess($user, $locationContext['id'])) {
                return back()->withErrors(['from_location' => 'Lokasi transfer memuat outlet yang tidak tersedia untuk akun ini.'])->withInput();
            }
        }

        $activeOutletId = $context->activeOutletId($user);
        if ($activeOutletId && ! (($from['type'] === 'outlet' && $from['id'] === $activeOutletId) || ($to['type'] === 'outlet' && $to['id'] === $activeOutletId))) {
            return back()->withErrors(['from_location' => 'Transfer harus melibatkan Active Outlet Backoffice.'])->withInput();
        }

        if ($from['type'] === $to['type'] && $from['id'] === $to['id']) {
            return back()
                ->withErrors([
                    'to_location' => 'Lokasi asal dan tujuan tidak boleh sama.',
                ])
                ->withInput();
        }

        // 4. Normalize the business payload exactly as it will be stored (quantities as 2-decimal strings, in the
        // submitted order, a repeated ingredient kept as its own line).
        try {
            $items = collect($validated['items'])
                ->values()
                ->map(fn ($item) => [
                    'ingredient_id' => (int) $item['ingredient_id'],
                    'qty' => TransferOperationService::canonicalQty($item['qty']),
                ]);
        } catch (\InvalidArgumentException) {
            // Cannot happen after the rules above; never round an odd quantity into a valid one if it ever does.
            return back()->withErrors(['items' => 'Qty transfer tidak valid.'])->withInput();
        }

        $globalNote = trim((string) ($validated['note'] ?? ''));
        $receiverName = $validated['receiver_name'] ?: null;

        // 5. Fingerprint of that payload (never of the operation key).
        $operationKey = strtolower($validated['operation_key']);
        $fingerprint = TransferOperationService::fingerprint(
            $from,
            $to,
            $validated['sender_name'],
            $receiverName,
            $globalNote !== '' ? $globalNote : null,
            $items->all()
        );

        try {
            // 6. The protected transaction: claim, validate against FRESH data, lock, post, record, commit - or
            // roll all of it back, claim included.
            $replay = DB::transaction(function () use ($operationKey, $fingerprint, $validated, $from, $to, $user, $items, $globalNote, $receiverName) {
                // 7-8. The claim is the first statement. A replay of a committed operation returns here, before any
                // check that depends on mutable stock, so it can never be refused for "insufficient stock".
                [$operation, $isNew] = app(TransferOperationService::class)->claim(
                    $operationKey,
                    $user->id,
                    TransferOperation::KIND_GENERAL_TRANSFER,
                    $fingerprint
                );

                if (! $isNew) {
                    return true;
                }

                // 9. A new operation: business validation on current data. A failure throws a ValidationException,
                // which rolls the claim back so the same key can be retried after the form is fixed.
                $fromName = $this->getLocationName($from['type'], $from['id']);
                $toName = $this->getLocationName($to['type'], $to['id']);
                $this->assertLocationsExist($from, $to);

                $ingredients = Ingredient::whereIn('id', $items->pluck('ingredient_id')->unique()->all())->get()->keyBy('id');
                foreach ($items as $index => $item) {
                    if (! $ingredients->has($item['ingredient_id'])) {
                        throw ValidationException::withMessages([
                            "items.{$index}.ingredient_id" => __('validation.exists', ['attribute' => "items.{$index}.ingredient_id"]),
                        ]);
                    }
                }

                // 10. Balances in the one global order of PR #34 (the source must exist, a missing destination is
                // created in this transaction), then the stock check on the locked, current quantities.
                $specs = [];
                foreach ($items as $index => $item) {
                    $name = $ingredients[$item['ingredient_id']]->name ?? 'Ingredient';
                    $specs[] = $this->balanceSpec(
                        $item['ingredient_id'], $from['type'], $from['id'], false,
                        fn () => ValidationException::withMessages([
                            "items.{$index}.ingredient_id" => 'Stock ' . $name . ' di ' . $fromName . ' belum tersedia. Lakukan stock in dulu sebelum transfer.',
                        ])
                    );
                    $specs[] = $this->balanceSpec($item['ingredient_id'], $to['type'], $to['id'], true);
                }
                $locked = $this->lockStockBalances($specs);

                $remaining = [];
                foreach ($items as $index => $item) {
                    $key = $this->balanceKey($item['ingredient_id'], $from['type'], $from['id']);
                    $available = $remaining[$key] ?? (float) $locked[$key]->qty_on_hand;
                    $transferQty = (float) $item['qty'];

                    if ($transferQty > $available) {
                        throw ValidationException::withMessages([
                            "items.{$index}.qty" => 'Stock ' . ($ingredients[$item['ingredient_id']]->name ?? 'Ingredient') . ' di ' . $fromName . ' hanya ' . number_format($available, 0, ',', '.') . '. Tidak cukup untuk transfer qty ' . number_format($transferQty, 0, ',', '.') . '.',
                        ]);
                    }

                    $remaining[$key] = $available - $transferQty;
                }

                // 11-13. Post every item, and record every created transfer id on the operation.
                $sentAt = now();
                $createdIds = [];

                foreach ($items as $item) {
                    $lockedSourceStock = $locked[$this->balanceKey($item['ingredient_id'], $from['type'], $from['id'])];
                    $destinationStock = $locked[$this->balanceKey($item['ingredient_id'], $to['type'], $to['id'])];

                    $transferQty = (float) $item['qty'];

                    $lockedSourceStock->update([
                        'qty_on_hand' => (float) $lockedSourceStock->qty_on_hand - $transferQty,
                    ]);

                    $destinationStock->update([
                        'qty_on_hand' => (float) $destinationStock->qty_on_hand + $transferQty,
                    ]);

                    $transfer = StockTransfer::create([
                        'warehouse_id' => $from['type'] === 'warehouse' ? $from['id'] : null,
                        'outlet_id' => $to['type'] === 'outlet' ? $to['id'] : null,
                        'ingredient_id' => $item['ingredient_id'],
                        'qty' => $transferQty,
                        'transferred_by_user_id' => $user->id,
                        'status' => 'in_transit',
                        'note' => $globalNote !== '' ? $globalNote : null,
                        'from_location_type' => $from['type'],
                        'from_location_id' => $from['id'],
                        'to_location_type' => $to['type'],
                        'to_location_id' => $to['id'],
                        'sender_name' => $validated['sender_name'],
                        'receiver_name' => $receiverName,
                        'sent_at' => $sentAt,
                        'received_at' => null,
                    ]);

                    $createdIds[] = $transfer->id;

                    $movementExtra = ' | sender: ' . $validated['sender_name'];

                    if (! empty($validated['receiver_name'])) {
                        $movementExtra .= ' | receiver: ' . $validated['receiver_name'];
                    }

                    StockMovement::create([
                        'ingredient_id' => $item['ingredient_id'],
                        'location_type' => $from['type'],
                        'location_id' => $from['id'],
                        'movement_type' => 'transfer_out',
                        'qty_in' => 0,
                        'qty_out' => $transferQty,
                        'reference_type' => 'general_transfer',
                        'reference_id' => $transfer->id,
                        'note' => 'Transfer #' . $transfer->transfer_number . ' keluar dari ' . $fromName . ' ke ' . $toName . $movementExtra . ($globalNote !== '' ? ' | ' . $globalNote : ''),
                    ]);

                    StockMovement::create([
                        'ingredient_id' => $item['ingredient_id'],
                        'location_type' => $to['type'],
                        'location_id' => $to['id'],
                        'movement_type' => 'transfer_in',
                        'qty_in' => $transferQty,
                        'qty_out' => 0,
                        'reference_type' => 'general_transfer',
                        'reference_id' => $transfer->id,
                        'note' => 'Transfer #' . $transfer->transfer_number . ' masuk ke ' . $toName . ' dari ' . $fromName . $movementExtra . ($globalNote !== '' ? ' | ' . $globalNote : ''),
                    ]);
                }

                $operation->update(['transfer_ids' => $createdIds]);

                return false;
            }, self::TRANSACTION_ATTEMPTS);   // 14. commit (re-run from a clean rollback only on a deadlock)
        } catch (TransferOperationConflict $e) {
            // Another user's key or another payload: refused generically. The key is dropped from the old input so
            // the re-rendered form gets a fresh one.
            return back()->withErrors(['operation_key' => $e->getMessage()])->withInput($request->except('operation_key'));
        } catch (TransferOperationBusy $e) {
            return back()->withErrors(['operation_key' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            if ($this->isConcurrencyFailure($e)) {
                return back()->withErrors(['items' => self::BUSY_MESSAGE])->withInput();
            }

            throw $e;
        }

        // 15.
        return redirect()
            ->route('backoffice.transfers.index')
            ->with('success', $replay ? 'Transfer ini sudah berhasil disimpan sebelumnya.' : 'Transfer bulk berhasil disimpan.');
    }

    /** Source and destination must still exist (checked on current data, after the claim). */
    protected function assertLocationsExist(array $from, array $to): void
    {
        $exists = fn (array $location): bool => $location['type'] === 'warehouse'
            ? (bool) Warehouse::find($location['id'])
            : (bool) Outlet::find($location['id']);

        if (! $exists($from)) {
            throw ValidationException::withMessages(['from_location' => $from['type'] === 'warehouse' ? 'Warehouse asal tidak ditemukan.' : 'Outlet asal tidak ditemukan.']);
        }

        if (! $exists($to)) {
            throw ValidationException::withMessages(['to_location' => $to['type'] === 'warehouse' ? 'Warehouse tujuan tidak ditemukan.' : 'Outlet tujuan tidak ditemukan.']);
        }
    }

    public function markReceived(StockTransfer $transfer)
    {
        $user = $this->authorizeAccess();
        $this->authorizeTransferAction($user, $transfer);

        try {
            $outcome = $this->withLockedTransfer($transfer, $user, function (StockTransfer $locked) {
                if ($locked->status === 'in_transit') {
                    $locked->update([
                        'status' => 'received',
                        'received_at' => now(),
                    ]);

                    return 'received_now';
                }

                // received -> received is a no-op (received_at keeps the first receipt); cancelled and any
                // other status is refused without touching anything.
                return in_array($locked->status, ['received', 'cancelled'], true) ? $locked->status : 'unsupported';
            });
        } catch (\Throwable $e) {
            return $this->transitionFailure($e);
        }

        $redirect = redirect()->route('backoffice.transfers.index');

        return match ($outcome) {
            'received_now' => $redirect->with('success', 'Transfer item berhasil ditandai sebagai diterima.'),
            'received' => $redirect->with('success', 'Transfer item ini sudah berstatus diterima.'),
            'cancelled' => $redirect->with('success', 'Transfer item yang sudah dibatalkan tidak bisa langsung ditandai diterima.'),
            default => $redirect->with('error', 'Status transfer item ini tidak bisa ditandai diterima.'),
        };
    }

    public function markCancelled(StockTransfer $transfer)
    {
        $user = $this->authorizeAccess();
        $this->authorizeTransferAction($user, $transfer);

        try {
            $outcome = $this->withLockedTransfer($transfer, $user, function (StockTransfer $locked) {
                if ($locked->status !== 'in_transit') {
                    return $locked->status;
                }

                $this->rollbackTransferStock($locked);

                $locked->update([
                    'status' => 'cancelled',
                    'received_at' => null,
                ]);

                return 'cancelled_now';
            });
        } catch (\Throwable $e) {
            return $this->transitionFailure($e);
        }

        $redirect = redirect()->route('backoffice.transfers.index');

        return match ($outcome) {
            'cancelled_now' => $redirect->with('success', 'Transfer item berhasil dibatalkan dan stok sudah di-rollback.'),
            'received' => $redirect->with('success', 'Transfer item yang sudah diterima tidak bisa dibatalkan.'),
            'cancelled' => $redirect->with('success', 'Transfer item ini sudah berstatus cancelled.'),
            default => $redirect->with('error', 'Status transfer item ini tidak bisa dibatalkan.'),
        };
    }

    public function markInTransit(StockTransfer $transfer)
    {
        $user = $this->authorizeAccess();
        $this->authorizeTransferAction($user, $transfer);

        try {
            $outcome = $this->withLockedTransfer($transfer, $user, function (StockTransfer $locked) {
                if ($locked->status === 'in_transit') {
                    return 'in_transit';
                }

                if ($locked->status === 'cancelled') {
                    // The stock was rolled back by the cancellation; this posts it again, exactly once.
                    $this->applyTransferStockAgain($locked);
                } elseif ($locked->status !== 'received') {
                    return 'unsupported';
                }

                // received -> in_transit is status only: the receipt never moved stock.
                $locked->update([
                    'status' => 'in_transit',
                    'received_at' => null,
                ]);

                return 'in_transit';
            });
        } catch (\Throwable $e) {
            return $this->transitionFailure($e);
        }

        $redirect = redirect()->route('backoffice.transfers.index');

        return $outcome === 'in_transit'
            ? $redirect->with('success', 'Transfer item berhasil dikembalikan ke status in transit.')
            : $redirect->with('error', 'Status transfer item ini tidak bisa diubah ke in transit.');
    }
}
