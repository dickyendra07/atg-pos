<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared write rules for the two category domains (menu/product categories and ingredient categories),
 * moved out of CategoryManagementController unchanged so the Product Workspace can create a Menu
 * Category with exactly the same authorization, validation, uniqueness and code generation.
 */
class CategoryWriter
{
    /** Roles that may manage categories (primary role or any pivot role). */
    public const ROLE_CODES = ['owner', 'admin_pusat', 'admin_outlet', 'staff_gudang'];

    public const DENIED_MESSAGE = 'Role kamu tidak punya akses ke halaman Category.';

    public static function canManage(User $user): bool
    {
        return $user->hasAnyRoleCode(self::ROLE_CODES);
    }

    public static function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function makeCode(string $modelClass, string $name, ?int $ignoreId = null): string
    {
        $base = Str::upper(Str::slug($name, '_')) ?: 'CATEGORY';
        $code = $base;
        $counter = 1;

        while ($modelClass::query()->where('code', $code)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $code = $base.'_'.$counter++;
        }

        return $code;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function validated(Request $request, string $modelClass, bool $needsBrand, ?Model $category = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($needsBrand) {
            $rules['brand_id'] = ['required', 'exists:brands,id'];
        }

        $data = $request->validate($rules, [
            'name.required' => 'Nama category wajib diisi.',
            'brand_id.required' => 'Brand wajib dipilih.',
        ]);

        $data['name'] = self::normalizeName($data['name']);

        if ($data['name'] === '') {
            throw ValidationException::withMessages(['name' => 'Nama category wajib diisi.']);
        }

        $duplicate = $modelClass::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($data['name'])])
            ->when($category, fn ($q) => $q->where('id', '!=', $category->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'Category dengan nama tersebut sudah ada (huruf besar/kecil dan spasi dianggap sama).']);
        }

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function create(string $modelClass, array $data): Model
    {
        $data['code'] = $this->makeCode($modelClass, $data['name']);

        return $modelClass::create($data);
    }
}
