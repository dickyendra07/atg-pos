<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransferViewController extends Controller
{
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

    protected function buildLocationOptions()
    {
        $warehouses = Warehouse::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($warehouse) {
                return [
                    'value' => 'warehouse:' . $warehouse->id,
                    'label' => 'Warehouse - ' . $warehouse->name,
                ];
            });

        $outlets = Outlet::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($outlet) {
                return [
                    'value' => 'outlet:' . $outlet->id,
                    'label' => 'Outlet - ' . $outlet->name,
                ];
            });

        return $warehouses->concat($outlets)->values();
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess();

        $query = StockTransfer::with(['ingredient.category', 'warehouse', 'outlet', 'transferredBy'])
            ->latest();

        if ($request->filled('from_location_type')) {
            $query->where('from_location_type', $request->from_location_type);
        }

        if ($request->filled('to_location_type')) {
            $query->where('to_location_type', $request->to_location_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

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

        return view('backoffice.transfers.index', [
            'user' => $user,
            'transfers' => $transfers,
            'summary' => $summary,
            'filters' => [
                'from_location_type' => $request->from_location_type,
                'to_location_type' => $request->to_location_type,
                'status' => $request->status,
            ],
        ]);
    }

    public function create(Request $request)
    {
        $user = $this->authorizeAccess();

        $locationOptions = $this->buildLocationOptions();

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
        ]);
    }

    public function availableIngredients(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'location' => 'required|string',
        ]);

        $location = $this->parseLocation($request->location);

        $stockBalances = StockBalance::with(['ingredient.category'])
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

    public function store(Request $request)
    {
        $user = $this->authorizeAccess();

        $validated = $request->validate([
            'from_location' => 'required|string',
            'to_location' => 'required|string',
            'sender_name' => 'required|string|max:100',
            'receiver_name' => 'nullable|string|max:100',
            'sent_at' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => 'required|exists:ingredients,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.note' => 'nullable|string|max:255',
        ], [
            'items.required' => 'Minimal harus ada 1 item transfer.',
            'items.*.ingredient_id.required' => 'Ingredient wajib dipilih di setiap baris.',
            'items.*.qty.required' => 'Qty transfer wajib diisi di setiap baris.',
        ]);

        $from = $this->parseLocation($validated['from_location']);
        $to = $this->parseLocation($validated['to_location']);

        if ($from['type'] === $to['type'] && $from['id'] === $to['id']) {
            return back()
                ->withErrors([
                    'to_location' => 'Lokasi asal dan tujuan tidak boleh sama.',
                ])
                ->withInput();
        }

        if ($from['type'] === 'warehouse' && ! Warehouse::find($from['id'])) {
            return back()
                ->withErrors([
                    'from_location' => 'Warehouse asal tidak ditemukan.',
                ])
                ->withInput();
        }

        if ($from['type'] === 'outlet' && ! Outlet::find($from['id'])) {
            return back()
                ->withErrors([
                    'from_location' => 'Outlet asal tidak ditemukan.',
                ])
                ->withInput();
        }

        if ($to['type'] === 'warehouse' && ! Warehouse::find($to['id'])) {
            return back()
                ->withErrors([
                    'to_location' => 'Warehouse tujuan tidak ditemukan.',
                ])
                ->withInput();
        }

        if ($to['type'] === 'outlet' && ! Outlet::find($to['id'])) {
            return back()
                ->withErrors([
                    'to_location' => 'Outlet tujuan tidak ditemukan.',
                ])
                ->withInput();
        }

        $items = collect($validated['items'])
            ->filter(function ($item) {
                return ! empty($item['ingredient_id']) && (float) ($item['qty'] ?? 0) > 0;
            })
            ->values();

        if ($items->isEmpty()) {
            return back()
                ->withErrors([
                    'items' => 'Tidak ada item transfer valid untuk disimpan.',
                ])
                ->withInput();
        }

        $fromName = $this->getLocationName($from['type'], $from['id']);
        $toName = $this->getLocationName($to['type'], $to['id']);

        $ingredientIds = $items->pluck('ingredient_id')->unique()->values();

        $sourceStocks = StockBalance::where('location_type', $from['type'])
            ->where('location_id', $from['id'])
            ->whereIn('ingredient_id', $ingredientIds)
            ->get()
            ->keyBy('ingredient_id');

        foreach ($items as $index => $item) {
            $ingredient = Ingredient::find($item['ingredient_id']);
            $ingredientName = $ingredient?->name ?? 'Ingredient';
            $sourceStock = $sourceStocks->get($item['ingredient_id']);

            if (! $sourceStock) {
                return back()
                    ->withErrors([
                        "items.{$index}.ingredient_id" => 'Stock ' . $ingredientName . ' di ' . $fromName . ' belum tersedia. Lakukan stock in dulu sebelum transfer.',
                    ])
                    ->withInput();
            }

            $currentSourceQty = (float) $sourceStock->qty_on_hand;
            $transferQty = (float) $item['qty'];

            if ($transferQty > $currentSourceQty) {
                return back()
                    ->withErrors([
                        "items.{$index}.qty" => 'Stock ' . $ingredientName . ' di ' . $fromName . ' hanya ' . number_format($currentSourceQty, 0, ',', '.') . '. Tidak cukup untuk transfer qty ' . number_format($transferQty, 0, ',', '.') . '.',
                    ])
                    ->withInput();
            }
        }

        DB::transaction(function () use ($validated, $from, $to, $user, $items, $fromName, $toName) {
            $sentAt = ! empty($validated['sent_at'])
                ? Carbon::parse($validated['sent_at'])
                : now();

            foreach ($items as $item) {
                $ingredient = Ingredient::find($item['ingredient_id']);

                $lockedSourceStock = StockBalance::where('location_type', $from['type'])
                    ->where('location_id', $from['id'])
                    ->where('ingredient_id', $item['ingredient_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $lockedSourceStock) {
                    throw new \RuntimeException('Stock asal tidak ditemukan saat proses transfer.');
                }

                $transferQty = (float) $item['qty'];
                $freshSourceQty = (float) $lockedSourceStock->qty_on_hand;

                if ($transferQty > $freshSourceQty) {
                    throw new \RuntimeException('Stock asal berubah saat proses transfer. Silakan ulangi lagi.');
                }

                $lockedSourceStock->update([
                    'qty_on_hand' => $freshSourceQty - $transferQty,
                ]);

                $destinationStock = StockBalance::firstOrCreate(
                    [
                        'ingredient_id' => $item['ingredient_id'],
                        'location_type' => $to['type'],
                        'location_id' => $to['id'],
                    ],
                    [
                        'qty_on_hand' => 0,
                    ]
                );

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
                    'note' => $item['note'] ?? null,
                    'from_location_type' => $from['type'],
                    'from_location_id' => $from['id'],
                    'to_location_type' => $to['type'],
                    'to_location_id' => $to['id'],
                    'sender_name' => $validated['sender_name'],
                    'receiver_name' => $validated['receiver_name'] ?: null,
                    'sent_at' => $sentAt,
                    'received_at' => null,
                ]);

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
                    'note' => 'Transfer #' . $transfer->transfer_number . ' keluar dari ' . $fromName . ' ke ' . $toName . $movementExtra . (! empty($item['note']) ? ' | ' . $item['note'] : ''),
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
                    'note' => 'Transfer #' . $transfer->transfer_number . ' masuk ke ' . $toName . ' dari ' . $fromName . $movementExtra . (! empty($item['note']) ? ' | ' . $item['note'] : ''),
                ]);
            }
        });

        return redirect()
            ->route('backoffice.transfers.index')
            ->with('success', 'Transfer bulk berhasil disimpan.');
    }

    public function markReceived(StockTransfer $transfer)
    {
        $this->authorizeAccess();

        $transfer->update([
            'status' => 'received',
            'received_at' => now(),
        ]);

        return redirect()
            ->route('backoffice.transfers.index')
            ->with('success', 'Transfer berhasil ditandai sebagai diterima.');
    }

    public function markCancelled(StockTransfer $transfer)
    {
        $this->authorizeAccess();

        if ($transfer->status === 'received') {
            return redirect()
                ->route('backoffice.transfers.index')
                ->with('success', 'Transfer yang sudah diterima tidak bisa dibatalkan dari flow basic ini.');
        }

        $transfer->update([
            'status' => 'cancelled',
        ]);

        return redirect()
            ->route('backoffice.transfers.index')
            ->with('success', 'Transfer berhasil ditandai sebagai dibatalkan.');
    }

    public function markInTransit(StockTransfer $transfer)
    {
        $this->authorizeAccess();

        $transfer->update([
            'status' => 'in_transit',
            'received_at' => null,
        ]);

        return redirect()
            ->route('backoffice.transfers.index')
            ->with('success', 'Transfer berhasil dikembalikan ke status in transit.');
    }
}