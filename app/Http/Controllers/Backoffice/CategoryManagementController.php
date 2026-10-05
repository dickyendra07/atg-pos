<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Model;
use App\Services\CategoryWriter;
use App\Support\BackofficeReturnUrl;
use Illuminate\Http\Request;

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

        abort_unless(CategoryWriter::canManage($user), 403, CategoryWriter::DENIED_MESSAGE);

        return $user;
    }

    protected function writer(): CategoryWriter
    {
        return app(CategoryWriter::class);
    }

    protected function validated(Request $request, ?Model $category = null): array
    {
        return $this->writer()->validated($request, $this->modelClass(), (bool) $this->config()['needs_brand'], $category);
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
        $category = $this->writer()->create($this->modelClass(), $this->validated($request));

        return BackofficeReturnUrl::redirect($request, $this->config()['route'].'.index', [], 'category-'.$category->id)
            ->with('success', 'Kategori berhasil ditambahkan.');
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

        return BackofficeReturnUrl::redirect($request, $this->config()['route'].'.index', [], 'category-'.$category->id)
            ->with('success', 'Kategori berhasil diperbarui.');
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

            return BackofficeReturnUrl::redirect($request, $this->config()['route'].'.index', [], 'category-'.$category->id)
                ->with('error', 'Kategori masih digunakan oleh '.$category->{$countKey}.' '.$noun.'. Pindahkan kategori '.$noun.' terlebih dahulu sebelum menghapus.');
        }

        $category->delete();

        // The row is gone, so no anchor.
        return BackofficeReturnUrl::redirect($request, $this->config()['route'].'.index')
            ->with('success', 'Kategori berhasil dihapus.');
    }
}
