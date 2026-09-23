<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared CRUD for the two separate category domains (ingredient categories and menu/product
 * categories). Each subclass points at its own model/table, so the two never mix.
 */
abstract class CategoryManagementController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    abstract protected function usageRelation(): string;

    /** @return array{title:string,kicker:string,route:string,usage_label:string,needs_brand:bool} */
    abstract protected function config(): array;

    protected function authorizeAccess(Request $request)
    {
        $user = $request->user()->loadMissing(['role', 'roles']);

        abort_unless($user->hasAnyRoleCode(['owner', 'admin_pusat', 'admin_outlet', 'staff_gudang']), 403, 'Role kamu tidak punya akses ke halaman Category.');

        return $user;
    }

    protected function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    protected function makeCode(string $name, ?int $ignoreId = null): string
    {
        $base = Str::upper(Str::slug($name, '_')) ?: 'CATEGORY';
        $model = $this->modelClass();
        $code = $base;
        $counter = 1;

        while ($model::query()->where('code', $code)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $code = $base.'_'.$counter++;
        }

        return $code;
    }

    protected function validated(Request $request, ?Model $category = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($this->config()['needs_brand']) {
            $rules['brand_id'] = ['required', 'exists:brands,id'];
        }

        $data = $request->validate($rules, [
            'name.required' => 'Nama category wajib diisi.',
            'brand_id.required' => 'Brand wajib dipilih.',
        ]);

        $data['name'] = $this->normalizeName($data['name']);

        if ($data['name'] === '') {
            throw ValidationException::withMessages(['name' => 'Nama category wajib diisi.']);
        }

        $model = $this->modelClass();
        $duplicate = $model::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($data['name'])])
            ->when($category, fn ($q) => $q->where('id', '!=', $category->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'Category dengan nama tersebut sudah ada (huruf besar/kecil dan spasi dianggap sama).']);
        }

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    protected function viewData(array $extra = []): array
    {
        return array_merge([
            'cfg' => $this->config(),
            'brands' => $this->config()['needs_brand'] ? Brand::where('is_active', true)->orderBy('name')->get() : collect(),
        ], $extra);
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess($request);
        $model = $this->modelClass();
        $search = trim((string) $request->input('search'));

        $categories = $model::query()
            ->with('brand')->withCount($this->usageRelation())
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->get();

        return view('backoffice.categories.index', $this->viewData([
            'user' => $user,
            'categories' => $categories,
            'usageCountKey' => $this->usageRelation().'_count',
            'search' => $search,
        ]));
    }

    public function create(Request $request)
    {
        return view('backoffice.categories.form', $this->viewData([
            'user' => $this->authorizeAccess($request),
            'category' => null,
        ]));
    }

    public function store(Request $request)
    {
        $this->authorizeAccess($request);
        $data = $this->validated($request);
        $data['code'] = $this->makeCode($data['name']);
        $model = $this->modelClass();
        $model::create($data);

        return redirect()->route($this->config()['route'].'.index')->with('success', 'Category berhasil dibuat.');
    }

    public function edit(Request $request, int $category)
    {
        $user = $this->authorizeAccess($request);
        $model = $this->modelClass();

        return view('backoffice.categories.form', $this->viewData([
            'user' => $user,
            'category' => $model::findOrFail($category),
        ]));
    }

    public function update(Request $request, int $category)
    {
        $this->authorizeAccess($request);
        $model = $this->modelClass();
        $category = $model::findOrFail($category);
        $category->update($this->validated($request, $category));

        return redirect()->route($this->config()['route'].'.index')->with('success', 'Category berhasil diupdate.');
    }

    public function destroy(Request $request, int $category)
    {
        $this->authorizeAccess($request);
        abort_unless($this->config()['deletable'] ?? false, 404);

        $model = $this->modelClass();
        $countKey = $this->usageRelation().'_count';
        $category = $model::withCount($this->usageRelation())->findOrFail($category);

        // The FK to this table cascade-deletes its usage relation, so a category still in
        // use is never handed to delete() — this is the safety guard, not just a UX message.
        if ($category->{$countKey} > 0) {
            $noun = $this->config()['usage_noun'] ?? 'item';

            return redirect()
                ->route($this->config()['route'].'.index')
                ->with('error', 'Kategori masih digunakan oleh '.$category->{$countKey}.' '.$noun.'. Pindahkan kategori '.$noun.' terlebih dahulu sebelum menghapus.');
        }

        $category->delete();

        return redirect()->route($this->config()['route'].'.index')->with('success', 'Category berhasil dihapus.');
    }
}
