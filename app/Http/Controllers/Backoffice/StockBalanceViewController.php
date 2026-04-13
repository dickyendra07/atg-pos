<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockBalanceViewController extends Controller
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
            abort(403, 'Role kamu tidak punya akses ke halaman Inventory Control.');
        }

        return $user;
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess();

        $ingredients = Ingredient::with('category')
            ->orderBy('name')
            ->get();

        $baseQuery = StockBalance::with(['ingredient.category', 'warehouse', 'outlet'])
            ->orderByDesc('id');

        if ($request->filled('ingredient_id')) {
            $baseQuery->where('ingredient_id', $request->ingredient_id);
        }

        if ($request->filled('location_type')) {
            $baseQuery->where('location_type', $request->location_type);
        }

        if ($request->filled('keyword')) {
            $keyword = trim($request->keyword);

            $baseQuery->where(function ($q) use ($keyword) {
                $q->whereHas('ingredient', function ($sub) use ($keyword) {
                    $sub->where('name', 'like', '%' . $keyword . '%');
                });

                if (is_numeric($keyword)) {
                    $q->orWhere('location_id', (int) $keyword);
                }
            });
        }

        $stockBalances = $baseQuery->get();

        if ($request->filled('status')) {
            $stockBalances = $stockBalances->filter(function ($stock) use ($request) {
                $minimum = (float) ($stock->ingredient->minimum_stock ?? 0);
                $qty = (float) ($stock->qty_on_hand ?? 0);

                if ($request->status === 'out') {
                    return $qty <= 0;
                }

                if ($request->status === 'low') {
                    return $qty > 0 && $qty <= $minimum;
                }

                if ($request->status === 'safe') {
                    return $qty > $minimum;
                }

                return true;
            })->values();
        }

        $distinctLocations = $stockBalances
            ->map(function ($stock) {
                return ($stock->location_type ?? '-') . ':' . ($stock->location_id ?? '-');
            })
            ->unique()
            ->count();

        $summary = [
            'locations' => $distinctLocations,
            'stock_rows' => $stockBalances->count(),
            'safe_stock' => 0,
            'need_action' => 0,
            'zero_stock' => 0,
        ];

        foreach ($stockBalances as $stock) {
            $minimum = (float) ($stock->ingredient->minimum_stock ?? 0);
            $qty = (float) ($stock->qty_on_hand ?? 0);

            if ($qty <= 0) {
                $summary['zero_stock']++;
                $summary['need_action']++;
            } elseif ($qty <= $minimum) {
                $summary['need_action']++;
            } else {
                $summary['safe_stock']++;
            }
        }

        $movementQuery = StockMovement::with(['ingredient.category']);

        if ($request->filled('ingredient_id')) {
            $movementQuery->where('ingredient_id', $request->ingredient_id);
        }

        if ($request->filled('location_type')) {
            $movementQuery->where('location_type', $request->location_type);
        }

        if ($request->filled('keyword')) {
            $keyword = trim($request->keyword);

            $movementQuery->where(function ($q) use ($keyword) {
                $q->whereHas('ingredient', function ($sub) use ($keyword) {
                    $sub->where('name', 'like', '%' . $keyword . '%');
                });

                if (is_numeric($keyword)) {
                    $q->orWhere('location_id', (int) $keyword);
                }

                $q->orWhere('note', 'like', '%' . $keyword . '%')
                    ->orWhere('reference_type', 'like', '%' . $keyword . '%');
            });
        }

        $movements = $movementQuery->get();

        $stockSummaryRows = [];

        foreach ($ingredients as $ingredient) {
            if ($request->filled('ingredient_id') && (int) $request->ingredient_id !== (int) $ingredient->id) {
                continue;
            }

            $ingredientMovements = $movements->where('ingredient_id', $ingredient->id);
            $ingredientStocks = $stockBalances->where('ingredient_id', $ingredient->id);

            if ($ingredientMovements->isEmpty() && $ingredientStocks->isEmpty()) {
                continue;
            }

            $openingBalance = (float) $ingredientMovements
                ->where('movement_type', 'opening_balance')
                ->sum('qty_in');

            $stockIn = (float) $ingredientMovements
                ->where('movement_type', 'stock_in')
                ->sum('qty_in');

            $transferIn = (float) $ingredientMovements
                ->where('movement_type', 'transfer_in')
                ->sum('qty_in');

            $transferOut = (float) $ingredientMovements
                ->where('movement_type', 'transfer_out')
                ->sum('qty_out');

            $productionIn = (float) $ingredientMovements
                ->where('movement_type', 'production_in')
                ->sum('qty_in');

            $productionOut = (float) $ingredientMovements
                ->where('movement_type', 'production_out')
                ->sum('qty_out');

            $adjustmentIn = (float) $ingredientMovements
                ->where('movement_type', 'stock_adjustment')
                ->sum('qty_in');

            $adjustmentOut = (float) $ingredientMovements
                ->where('movement_type', 'stock_adjustment')
                ->sum('qty_out');

            $endingStock = (float) $ingredientStocks->sum('qty_on_hand');
            $minimumStock = (float) ($ingredient->minimum_stock ?? 0);

            $stockSummaryRows[] = [
                'category_name' => $ingredient->category->name ?? '-',
                'ingredient_name' => $ingredient->name,
                'unit' => $ingredient->unit,
                'minimum_stock' => $minimumStock,
                'opening_balance' => $openingBalance,
                'stock_in' => $stockIn,
                'transfer_in' => $transferIn,
                'transfer_out' => $transferOut,
                'production_in' => $productionIn,
                'production_out' => $productionOut,
                'adjustment_in' => $adjustmentIn,
                'adjustment_out' => $adjustmentOut,
                'ending_stock' => $endingStock,
                'need_action' => $endingStock <= $minimumStock,
                'is_zero' => $endingStock <= 0,
            ];
        }

        $stockSummaryRows = collect($stockSummaryRows)
            ->sortBy('ingredient_name')
            ->values();

        return view('backoffice.stock-balances.index', [
            'user' => $user,
            'ingredients' => $ingredients,
            'stockBalances' => $stockBalances,
            'stocks' => $stockBalances,
            'summary' => $summary,
            'stockSummaryRows' => $stockSummaryRows,
        ]);
    }

    public function create()
    {
        $user = $this->authorizeAccess();

        $ingredients = Ingredient::with(['category'])
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $outlets = Outlet::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('backoffice.stock-balances.create', [
            'user' => $user,
            'ingredients' => $ingredients,
            'warehouses' => $warehouses,
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'location_type' => 'required|in:warehouse,outlet',
            'location_id' => 'required|integer|min:1',
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => 'required|exists:ingredients,id',
            'items.*.qty_in' => 'required|numeric|min:0.01',
            'items.*.note' => 'nullable|string|max:255',
        ], [
            'items.required' => 'Minimal harus ada 1 item penerimaan barang.',
            'items.*.ingredient_id.required' => 'Ingredient wajib dipilih di setiap baris.',
            'items.*.qty_in.required' => 'Qty masuk wajib diisi di setiap baris.',
        ]);

        if ($validated['location_type'] === 'warehouse') {
            $location = Warehouse::query()
                ->where('is_active', true)
                ->find($validated['location_id']);

            if (! $location) {
                return back()
                    ->withErrors(['location_id' => 'Warehouse tujuan tidak ditemukan atau tidak aktif.'])
                    ->withInput();
            }
        } else {
            $location = Outlet::query()
                ->where('is_active', true)
                ->find($validated['location_id']);

            if (! $location) {
                return back()
                    ->withErrors(['location_id' => 'Outlet tujuan tidak ditemukan atau tidak aktif.'])
                    ->withInput();
            }
        }

        $items = collect($validated['items'])
            ->filter(function ($item) {
                return ! empty($item['ingredient_id']) && (float) ($item['qty_in'] ?? 0) > 0;
            })
            ->values();

        if ($items->isEmpty()) {
            return back()
                ->withErrors(['items' => 'Tidak ada item valid untuk disimpan.'])
                ->withInput();
        }

        DB::transaction(function () use ($validated, $items) {
            foreach ($items as $item) {
                $stockBalance = StockBalance::firstOrCreate(
                    [
                        'ingredient_id' => $item['ingredient_id'],
                        'location_type' => $validated['location_type'],
                        'location_id' => $validated['location_id'],
                    ],
                    [
                        'qty_on_hand' => 0,
                    ]
                );

                $currentQty = (float) $stockBalance->qty_on_hand;
                $newQty = $currentQty + (float) $item['qty_in'];

                $stockBalance->update([
                    'qty_on_hand' => $newQty,
                ]);

                StockMovement::create([
                    'ingredient_id' => $item['ingredient_id'],
                    'location_type' => $validated['location_type'],
                    'location_id' => $validated['location_id'],
                    'movement_type' => 'stock_in',
                    'qty_in' => $item['qty_in'],
                    'qty_out' => 0,
                    'reference_type' => 'manual_stock_in',
                    'reference_id' => null,
                    'note' => $item['note'] ?? 'Penerimaan barang dari luar sistem',
                ]);
            }
        });

        $locationLabel = $validated['location_type'] === 'warehouse' ? 'warehouse' : 'outlet';

        return redirect()
            ->route('backoffice.stock-balances.index')
            ->with('success', 'Penerimaan barang bulk berhasil disimpan ke ' . $locationLabel . ' tujuan.');
    }

    public function createAdjustment()
    {
        $user = $this->authorizeAccess();

        $ingredients = Ingredient::with(['category'])
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $outlets = Outlet::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $stockMap = StockBalance::query()
            ->select('ingredient_id', 'location_type', 'location_id', 'qty_on_hand')
            ->get()
            ->groupBy(function ($row) {
                return $row->location_type . ':' . $row->location_id;
            })
            ->map(function ($rows) {
                return $rows->mapWithKeys(function ($row) {
                    return [
                        $row->ingredient_id => (float) $row->qty_on_hand,
                    ];
                })->toArray();
            })
            ->toArray();

        return view('backoffice.stock-balances.adjustment', [
            'user' => $user,
            'ingredients' => $ingredients,
            'warehouses' => $warehouses,
            'outlets' => $outlets,
            'stockMap' => $stockMap,
        ]);
    }

    public function storeAdjustment(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'location_type' => 'required|in:warehouse,outlet',
            'location_id' => 'required|integer|min:1',
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => 'required|exists:ingredients,id',
            'items.*.actual_qty' => 'required|numeric|min:0',
            'items.*.note' => 'required|string|max:255',
        ], [
            'items.required' => 'Minimal harus ada 1 item adjustment.',
            'items.*.ingredient_id.required' => 'Ingredient wajib dipilih di setiap baris.',
            'items.*.actual_qty.required' => 'Stok aktual wajib diisi di setiap baris.',
            'items.*.note.required' => 'Keterangan wajib diisi di setiap baris.',
        ]);

        if ($validated['location_type'] === 'warehouse') {
            $location = Warehouse::query()
                ->where('is_active', true)
                ->find($validated['location_id']);

            if (! $location) {
                return back()
                    ->withErrors(['location_id' => 'Warehouse tujuan tidak ditemukan atau tidak aktif.'])
                    ->withInput();
            }
        } else {
            $location = Outlet::query()
                ->where('is_active', true)
                ->find($validated['location_id']);

            if (! $location) {
                return back()
                    ->withErrors(['location_id' => 'Outlet tujuan tidak ditemukan atau tidak aktif.'])
                    ->withInput();
            }
        }

        $items = collect($validated['items'])
            ->filter(function ($item) {
                return ! empty($item['ingredient_id']) && isset($item['actual_qty']);
            })
            ->values();

        if ($items->isEmpty()) {
            return back()
                ->withErrors(['items' => 'Tidak ada item adjustment valid untuk disimpan.'])
                ->withInput();
        }

        $changedCount = 0;

        DB::transaction(function () use ($validated, $items, &$changedCount) {
            foreach ($items as $item) {
                $stockBalance = StockBalance::firstOrCreate(
                    [
                        'ingredient_id' => $item['ingredient_id'],
                        'location_type' => $validated['location_type'],
                        'location_id' => $validated['location_id'],
                    ],
                    [
                        'qty_on_hand' => 0,
                    ]
                );

                $systemQty = (float) $stockBalance->qty_on_hand;
                $actualQty = (float) $item['actual_qty'];
                $diff = $actualQty - $systemQty;

                $stockBalance->update([
                    'qty_on_hand' => $actualQty,
                ]);

                if ($diff != 0.0) {
                    StockMovement::create([
                        'ingredient_id' => $item['ingredient_id'],
                        'location_type' => $validated['location_type'],
                        'location_id' => $validated['location_id'],
                        'movement_type' => 'stock_adjustment',
                        'qty_in' => $diff > 0 ? $diff : 0,
                        'qty_out' => $diff < 0 ? abs($diff) : 0,
                        'reference_type' => 'manual_adjustment',
                        'reference_id' => null,
                        'note' => ($item['note'] ?? 'Adjustment manual')
                            . ' | System: ' . $systemQty
                            . ' | Aktual: ' . $actualQty,
                    ]);

                    $changedCount++;
                }
            }
        });

        $locationLabel = $validated['location_type'] === 'warehouse' ? 'warehouse' : 'outlet';

        if ($changedCount === 0) {
            return redirect()
                ->route('backoffice.stock-balances.index')
                ->with('success', 'Adjustment selesai, tetapi tidak ada perubahan stok karena semua nilai aktual sama dengan stok sistem di ' . $locationLabel . ' tujuan.');
        }

        return redirect()
            ->route('backoffice.stock-balances.index')
            ->with('success', 'Adjustment bulk berhasil disimpan ke ' . $locationLabel . ' tujuan.');
    }

    public function createOpname(Request $request)
    {
        $user = $this->authorizeAccess();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedWarehouseId = $request->get('warehouse_id');

        $stockBalancesQuery = StockBalance::with(['ingredient.category'])
            ->where('location_type', 'warehouse')
            ->orderByDesc('id');

        if ($selectedWarehouseId) {
            $stockBalancesQuery->where('location_id', $selectedWarehouseId);
        }

        $stockBalances = $stockBalancesQuery->get();

        return view('backoffice.stock-balances.opname', [
            'user' => $user,
            'warehouses' => $warehouses,
            'stockBalances' => $stockBalances,
            'selectedWarehouseId' => $selectedWarehouseId,
        ]);
    }

    public function storeOpname(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'stock_balance_id' => 'required|exists:stock_balances,id',
            'physical_qty' => 'required|numeric|min:0',
            'note' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            $stockBalance = StockBalance::with('ingredient')->findOrFail($validated['stock_balance_id']);

            if (
                $stockBalance->location_type !== 'warehouse' ||
                (int) $stockBalance->location_id !== (int) $validated['warehouse_id']
            ) {
                abort(422, 'Stock item yang dipilih tidak sesuai dengan warehouse opname.');
            }

            $systemQty = (float) $stockBalance->qty_on_hand;
            $physicalQty = (float) $validated['physical_qty'];
            $diff = $physicalQty - $systemQty;

            $stockBalance->update([
                'qty_on_hand' => $physicalQty,
            ]);

            if ($diff != 0.0) {
                StockMovement::create([
                    'ingredient_id' => $stockBalance->ingredient_id,
                    'location_type' => 'warehouse',
                    'location_id' => $stockBalance->location_id,
                    'movement_type' => 'stock_adjustment',
                    'qty_in' => $diff > 0 ? $diff : 0,
                    'qty_out' => $diff < 0 ? abs($diff) : 0,
                    'reference_type' => 'stock_opname',
                    'reference_id' => null,
                    'note' => $validated['note'] . ' | System: ' . $systemQty . ' | Fisik: ' . $physicalQty,
                ]);
            }
        });

        return redirect()
            ->route('backoffice.stock-balances.index')
            ->with('success', 'Opname gudang berhasil disimpan.');
    }

    public function importForm()
    {
        $user = $this->authorizeAccess();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $outlets = Outlet::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('backoffice.stock-balances.import', [
            'user' => $user,
            'warehouses' => $warehouses,
            'outlets' => $outlets,
        ]);
    }

    public function importStore(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ], [
            'file.required' => 'File CSV wajib dipilih.',
            'file.mimes' => 'File harus berformat CSV.',
        ]);

        $realPath = $request->file('file')->getRealPath();

        if (! $realPath || ! file_exists($realPath)) {
            return redirect()
                ->route('backoffice.stock-balances.import')
                ->with('error', 'File upload tidak ditemukan. Coba upload ulang.');
        }

        $content = file_get_contents($realPath);

        if ($content === false || trim($content) === '') {
            return redirect()
                ->route('backoffice.stock-balances.import')
                ->with('error', 'File CSV kosong atau tidak bisa dibaca.');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split("/\r\n|\n|\r/", trim($content));

        if (! $lines || count($lines) < 2) {
            return redirect()
                ->route('backoffice.stock-balances.import')
                ->with('error', 'CSV minimal harus punya header dan 1 baris data.');
        }

        $firstLine = $lines[0];
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $header = str_getcsv($firstLine, $delimiter);
        $header = array_map(function ($value) {
            return trim(strtolower($value));
        }, $header);

        $expectedHeader = [
            'ingredient_name',
            'location_type',
            'location_id',
            'qty_on_hand',
            'note',
        ];

        if ($header !== $expectedHeader) {
            return redirect()
                ->route('backoffice.stock-balances.import')
                ->with('error', 'Header CSV tidak sesuai template. Pastikan urutannya: ingredient_name,location_type,location_id,qty_on_hand,note');
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $successRows = [];

        DB::transaction(function () use ($lines, $delimiter, &$imported, &$skipped, &$errors, &$successRows) {
            foreach (array_slice($lines, 1) as $index => $line) {
                $rowNumber = $index + 2;

                if (trim($line) === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: baris kosong.";
                    continue;
                }

                $row = str_getcsv($line, $delimiter);

                if (count($row) < 5) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: jumlah kolom kurang dari 5.";
                    continue;
                }

                $ingredientName = trim($row[0] ?? '');
                $locationType = trim(strtolower($row[1] ?? ''));
                $locationIdRaw = trim($row[2] ?? '');
                $qtyOnHandRaw = trim($row[3] ?? '');
                $note = trim($row[4] ?? '');

                if ($ingredientName === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: ingredient_name kosong.";
                    continue;
                }

                if ($locationType === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: location_type kosong.";
                    continue;
                }

                if (! in_array($locationType, ['outlet', 'warehouse'])) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: location_type '{$locationType}' tidak valid.";
                    continue;
                }

                if ($locationIdRaw === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: location_id kosong.";
                    continue;
                }

                if (! ctype_digit($locationIdRaw)) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: location_id harus angka bulat.";
                    continue;
                }

                if ($qtyOnHandRaw === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: qty_on_hand kosong.";
                    continue;
                }

                if (! is_numeric($qtyOnHandRaw) || (float) $qtyOnHandRaw < 0) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: qty_on_hand tidak valid.";
                    continue;
                }

                $ingredient = Ingredient::whereRaw('LOWER(name) = ?', [mb_strtolower($ingredientName)])->first();

                if (! $ingredient) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: ingredient '{$ingredientName}' tidak ditemukan.";
                    continue;
                }

                $locationId = (int) $locationIdRaw;
                $qtyOnHand = (float) $qtyOnHandRaw;

                $alreadyExists = StockBalance::where('ingredient_id', $ingredient->id)
                    ->where('location_type', $locationType)
                    ->where('location_id', $locationId)
                    ->exists();

                if ($alreadyExists) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: ingredient '{$ingredientName}' di {$locationType} ID {$locationId} sudah punya stock balance, jadi baris ini di-skip.";
                    continue;
                }

                StockBalance::create([
                    'ingredient_id' => $ingredient->id,
                    'location_type' => $locationType,
                    'location_id' => $locationId,
                    'qty_on_hand' => $qtyOnHand,
                ]);

                StockMovement::create([
                    'ingredient_id' => $ingredient->id,
                    'location_type' => $locationType,
                    'location_id' => $locationId,
                    'movement_type' => 'opening_balance',
                    'qty_in' => $qtyOnHand,
                    'qty_out' => 0,
                    'reference_type' => 'import_opening_stock',
                    'reference_id' => null,
                    'note' => $note !== '' ? $note : 'Opening stock import',
                ]);

                $imported++;
                $successRows[] = "Baris {$rowNumber}: {$ingredientName} berhasil diimport ke {$locationType} ID {$locationId} dengan qty {$qtyOnHand}.";
            }
        });

        $message = "Import Opening Stock selesai. Data masuk: {$imported}. Data di-skip: {$skipped}.";

        if ($imported === 0 && $skipped > 0) {
            $message .= " Tidak ada data baru yang masuk karena semua baris dilewati.";
        }

        return redirect()
            ->route('backoffice.stock-balances.import')
            ->with('success', $message)
            ->with('import_success_rows', $successRows)
            ->with('import_errors', $errors);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $this->authorizeAccess();

        $filename = 'sample_opening_stock_import_template.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['ingredient_name', 'location_type', 'location_id', 'qty_on_hand', 'note']);
            fputcsv($handle, ['Black Tea', 'outlet', '1', '1000', 'Opening stock outlet 1']);
            fputcsv($handle, ['Fresh Milk', 'outlet', '1', '800', 'Opening stock outlet 1']);
            fputcsv($handle, ['Liquid Sugar', 'warehouse', '1', '5000', 'Opening stock gudang utama']);

            fclose($handle);
        }, 200, $headers);
    }
}