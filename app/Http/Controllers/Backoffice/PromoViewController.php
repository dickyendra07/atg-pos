<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\ProductVariant;
use App\Models\Promo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PromoViewController extends Controller
{
    protected array $dayOptions = [
        'sunday' => 'Minggu',
        'monday' => 'Senin',
        'tuesday' => 'Selasa',
        'wednesday' => 'Rabu',
        'thursday' => 'Kamis',
        'friday' => 'Jumat',
        'saturday' => 'Sabtu',
    ];

    protected function authorizeAccess()
    {
        $user = Auth::user()->load(['role', 'outlet']);
        $roleCode = $user->role?->code;

        if (! in_array($roleCode, ['owner', 'admin_pusat', 'staff_gudang'], true)) {
            return null;
        }

        return $user;
    }

    protected function productVariantOptions()
    {
        return ProductVariant::with('product')
            ->where('is_active', true)
            ->get()
            ->sortBy(function ($variant) {
                return ($variant->product->name ?? '') . ' ' . ($variant->name ?? '');
            })
            ->values();
    }

    protected function normalizeOutletIds(array $validated): array
    {
        return collect($validated['outlet_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function validatePromo(Request $request): array
    {
        return $request->validate([
            'outlet_id' => 'nullable|exists:outlets,id',
            'outlet_ids' => 'nullable|array',
            'outlet_ids.*' => 'nullable|exists:outlets,id',
            'name' => 'required|string|max:255',
            'requirement_logic' => 'required|in:and,or',

            'requirements' => 'nullable|array',
            'requirements.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'requirements.*.qty' => 'nullable|numeric|min:1',

            'rewards' => 'nullable|array',
            'rewards.*.reward_type' => 'nullable|in:discount_amount,discount_percent,free_item',
            'rewards.*.reward_value' => 'nullable|numeric|min:0',
            'rewards.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'rewards.*.qty' => 'nullable|numeric|min:1',

            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'active_days' => 'nullable|array',
            'active_days.*' => 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday',
            'status' => 'required|in:draft,active,discontinued',
            'is_active' => 'nullable|boolean',
        ], [
            'name.required' => 'Nama promo wajib diisi.',
            'requirement_logic.required' => 'Logic requirement wajib dipilih.',
            'end_date.after_or_equal' => 'Tanggal akhir promo tidak boleh lebih awal dari tanggal mulai.',
            'status.required' => 'Status promo wajib dipilih.',
        ]);
    }

    protected function normalizePromoData(array $validated, Request $request): array
    {
        return [
            'outlet_id' => null,
            'name' => $validated['name'],
            'requirement_logic' => $validated['requirement_logic'] ?? 'and',
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'active_days' => $validated['active_days'] ?? [],
            'status' => $validated['status'],
            'is_active' => $request->boolean('is_active'),
        ];
    }

    protected function syncPromoRules(Promo $promo, array $validated): void
    {
        $requirements = collect($validated['requirements'] ?? [])
            ->filter(function ($row) {
                return ! empty($row['product_variant_id']);
            })
            ->map(function ($row) {
                return [
                    'product_variant_id' => $row['product_variant_id'],
                    'qty' => max(1, (float) ($row['qty'] ?? 1)),
                ];
            })
            ->values();

        $rewards = collect($validated['rewards'] ?? [])
            ->filter(function ($row) {
                $rewardType = $row['reward_type'] ?? null;

                if ($rewardType === 'free_item') {
                    return ! empty($row['product_variant_id']);
                }

                return in_array($rewardType, ['discount_amount', 'discount_percent'], true)
                    && (float) ($row['reward_value'] ?? 0) > 0;
            })
            ->map(function ($row) {
                $rewardType = $row['reward_type'];

                if ($rewardType === 'free_item') {
                    return [
                        'reward_type' => 'free_item',
                        'reward_value' => 0,
                        'product_variant_id' => $row['product_variant_id'],
                        'qty' => max(1, (float) ($row['qty'] ?? 1)),
                    ];
                }

                return [
                    'reward_type' => $rewardType,
                    'reward_value' => max(0, (float) ($row['reward_value'] ?? 0)),
                    'product_variant_id' => null,
                    'qty' => 1,
                ];
            })
            ->values();

        $promo->requirements()->delete();
        $promo->rewards()->delete();

        foreach ($requirements as $requirement) {
            $promo->requirements()->create($requirement);
        }

        foreach ($rewards as $reward) {
            $promo->rewards()->create($reward);
        }

        $firstRequirement = $requirements->first();
        $firstReward = $rewards->first();

        $promo->update([
            'requirement_product_variant_id' => $firstRequirement['product_variant_id'] ?? null,
            'requirement_qty' => $firstRequirement['qty'] ?? 1,
            'reward_type' => $firstReward['reward_type'] ?? 'discount_amount',
            'reward_value' => $firstReward['reward_value'] ?? 0,
            'reward_product_variant_id' => $firstReward['product_variant_id'] ?? null,
            'reward_qty' => $firstReward['qty'] ?? 1,
        ]);
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            return redirect()
                ->route('backoffice.index')
                ->with('error', 'Role kamu tidak punya akses ke Promos.');
        }

        $query = Promo::with([
            'outlet',
            'outlets',
            'requirements.variant.product',
            'rewards.variant.product',
            'requirementVariant.product',
            'rewardVariant.product',
        ])->latest();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('outlet_id')) {
            $query->where(function ($outletQuery) use ($request) {
                $outletQuery->where('outlet_id', $request->outlet_id)
                    ->orWhereHas('outlets', function ($pivotQuery) use ($request) {
                        $pivotQuery->where('outlets.id', $request->outlet_id);
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return view('backoffice.promos.index', [
            'user' => $user,
            'promos' => $query->get(),
            'outletOptions' => Outlet::where('is_active', true)->orderBy('name')->get(),
            'filters' => [
                'search' => $request->search,
                'outlet_id' => $request->outlet_id,
                'status' => $request->status,
            ],
            'dayOptions' => $this->dayOptions,
        ]);
    }

    public function create()
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            return redirect()
                ->route('backoffice.index')
                ->with('error', 'Role kamu tidak punya akses create promo.');
        }

        return view('backoffice.promos.create', [
            'user' => $user,
            'outletOptions' => Outlet::where('is_active', true)->orderBy('name')->get(),
            'variantOptions' => $this->productVariantOptions(),
            'dayOptions' => $this->dayOptions,
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            abort(403, 'Role kamu tidak punya akses create promo.');
        }

        $validated = $this->validatePromo($request);

        DB::transaction(function () use ($validated, $request) {
            $promo = Promo::create($this->normalizePromoData($validated, $request));

            $promo->outlets()->sync($this->normalizeOutletIds($validated));
            $this->syncPromoRules($promo, $validated);
        });

        return redirect()
            ->route('backoffice.promos.index')
            ->with('success', 'Promo berhasil dibuat.');
    }

    public function edit(Promo $promo)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            return redirect()
                ->route('backoffice.index')
                ->with('error', 'Role kamu tidak punya akses edit promo.');
        }

        return view('backoffice.promos.edit', [
            'user' => $user,
            'promo' => $promo->load([
                'outlet',
                'outlets',
                'requirements.variant.product',
                'rewards.variant.product',
                'requirementVariant.product',
                'rewardVariant.product',
            ]),
            'outletOptions' => Outlet::where('is_active', true)->orderBy('name')->get(),
            'variantOptions' => $this->productVariantOptions(),
            'dayOptions' => $this->dayOptions,
        ]);
    }

    public function update(Request $request, Promo $promo)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            abort(403, 'Role kamu tidak punya akses update promo.');
        }

        $validated = $this->validatePromo($request);

        DB::transaction(function () use ($promo, $validated, $request) {
            $promo->update($this->normalizePromoData($validated, $request));

            $promo->outlets()->sync($this->normalizeOutletIds($validated));
            $this->syncPromoRules($promo, $validated);
        });

        return redirect()
            ->route('backoffice.promos.index')
            ->with('success', 'Promo berhasil diupdate.');
    }

    public function destroy(Promo $promo)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            abort(403, 'Role kamu tidak punya akses delete promo.');
        }

        $promo->delete();

        return redirect()
            ->route('backoffice.promos.index')
            ->with('success', 'Promo berhasil dihapus.');
    }
}
