<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Staged, per-Variant Recipe import shared by the legacy CSV and the client Excel import.
 *
 * The controllers only PARSE the file and hand every row over (stage() / rejectRow()); nothing is written
 * while rows are read. apply() then writes one Variant at a time, each in its own transaction:
 *
 *  - ALL-OR-NOTHING PER VARIANT: if any row of a Variant is rejected (unknown or inactive Ingredient, an
 *    Ingredient missing at a required sellable outlet, invalid qty, Variant outside the user's mutation
 *    rights, several active Recipes), NOTHING of that Variant is written - the Recipe stays exactly as it
 *    was - and every problem is reported with its row number. Other Variants are independent.
 *  - the Ingredient rules are RecipeWriter's (ingredientProblem()), the outlet rules are RecipeAccessPolicy's:
 *    the import has no rules of its own, so it can never accept what the Recipe Editor refuses.
 *  - a Recipe is only ACTIVATED when RecipeWriter::activationProblems() is empty for the finished Recipe;
 *    otherwise it stays (or is created) inactive and the reason is reported. An already active Recipe is
 *    never switched off by a failed row, and a second active Recipe is never created.
 *  - same data semantics as before: existing Recipe (and its ID) is reused, imported Ingredients update the
 *    qty/unit of an existing row or add a new row, rows not in the file are left alone, nothing is removed.
 *  - the Ingredient outlet assignment is never changed, no stock is touched.
 *
 * Active flag per Variant: CSV passes the is_active column (the Recipe is only requested active when EVERY
 * row of the Variant says so); the Excel import passes true (it has no column), which is now a request that
 * is checked like any other, not a forced value.
 */
class RecipeImportWriter
{
    /** Same minimum as RecipeWriter::qtyRule() ('numeric|min:0.01'); kept here because the rule is a validator string. */
    private const MIN_QTY = 0.01;

    private int $imported = 0;

    private int $updated = 0;

    private int $skipped = 0;

    private int $notActivated = 0;

    /** @var string[] */
    private array $errors = [];

    /** @var array<int, array<string, mixed>> staged groups keyed by Variant ID */
    private array $groups = [];

    private array $requiredCache = [];

    private array $namesCache = [];

    public function __construct(
        private readonly RecipeWriter $writer,
        private readonly RecipeAccessPolicy $policy,
        private readonly User $user,
    ) {}

    /** A row that cannot be tied to a resolvable Variant (nothing else is affected). */
    public function skipRow(string $message): void
    {
        $this->skipped++;
        $this->errors[] = $message;
    }

    /** A row of a resolved Variant that is invalid: the whole Variant is left untouched. */
    public function rejectRow(ProductVariant $variant, int $rowNumber, string $message, string $deniedLabel): void
    {
        $group = &$this->group($variant, $rowNumber, $deniedLabel);
        $group['rows']++;

        if ($group['denied'] === null) {
            $group['invalid'] = true;
            $group['row_errors'][] = "Baris {$rowNumber}: {$message}";
        }
    }

    /**
     * A candidate row of a resolved Variant. Validates it NOW against the shared Recipe rules (no write).
     *
     * @param  string  $deniedLabel  how the Variant is named in the "dilewati" line when the user may not change it
     */
    public function stage(ProductVariant $variant, int $rowNumber, string $deniedLabel, ?Ingredient $ingredient, string $ingredientName, ?float $qty, ?bool $requestActive, string $unitFallback = ''): void
    {
        $group = &$this->group($variant, $rowNumber, $deniedLabel);
        $group['rows']++;

        if ($group['denied'] !== null) {
            return;
        }

        $problem = null;

        if (! $ingredient) {
            $problem = "ingredient '{$ingredientName}' tidak ditemukan di master Ingredient.";
        } elseif ($qty === null || $qty < self::MIN_QTY) {
            $problem = "qty untuk ingredient '{$ingredientName}' tidak valid (minimal ".self::MIN_QTY.', sama seperti Recipe Editor).';
        } elseif ($found = $this->writer->ingredientProblem($ingredient, $this->required($variant))) {
            $problem = $found;
        }

        if ($problem) {
            $group['invalid'] = true;
            $group['row_errors'][] = "Baris {$rowNumber}: {$problem}";

            return;
        }

        $group['items'][$ingredient->id] = ['ingredient' => $ingredient, 'qty' => $qty, 'unit' => $ingredient->unit ?: $unitFallback];
        $group['active_votes'][] = $requestActive;
    }

    /** Writes every valid Variant (one transaction each) and returns the report. */
    public function apply(): array
    {
        foreach ($this->groups as $group) {
            if ($group['denied'] !== null) {
                $this->skipped += $group['rows'];

                continue;
            }

            if ($group['invalid']) {
                $this->skipped += $group['rows'];
                array_push($this->errors, ...$group['row_errors']);
                $this->errors[] = "Variant '{$group['variant']->code}' tidak diimport sama sekali ({$group['rows']} baris): ada baris bermasalah, Recipe-nya tidak diubah. Perbaiki baris di atas lalu import ulang.";

                continue;
            }

            $this->applyGroup($group);
        }

        return [
            'imported' => $this->imported,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'not_activated' => $this->notActivated,
            'errors' => $this->errors,
        ];
    }

    private function applyGroup(array $group): void
    {
        /** @var ProductVariant $variant */
        $variant = $group['variant'];

        try {
            // Everything the Variant contributes to the report is collected here and only added to the
            // totals AFTER the transaction has committed, so a rolled-back Variant counts as skipped only.
            $result = DB::transaction(function () use ($group, $variant) {
                ProductVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();

                $recipes = Recipe::where('product_variant_id', $variant->id)->orderBy('id')->get();

                if ($recipes->where('is_active', true)->count() > 1) {
                    return ['abort' => RecipeWriter::AMBIGUOUS_MESSAGE.' Recipe Variant ini tidak diubah oleh import.'];
                }

                $recipe = $recipes->firstWhere('is_active', true) ?? $recipes->first();

                if ($recipe && (int) $recipe->product_id !== (int) $variant->product_id) {
                    return ['abort' => 'Recipe Variant ini tidak terhubung ke Product yang benar. Recipe tidak diubah oleh import.'];
                }

                $created = false;

                if (! $recipe) {
                    $recipe = Recipe::create([
                        'product_id' => $variant->product_id,
                        'product_variant_id' => $variant->id,
                        'name' => RecipeWriter::defaultName($variant),
                        'is_active' => false,
                    ]);
                    $created = true;
                }

                $imported = 0;
                $updated = 0;

                foreach ($group['items'] as $row) {
                    $item = RecipeItem::where('recipe_id', $recipe->id)->where('ingredient_id', $row['ingredient']->id)->first();

                    if ($item) {
                        $item->update(['qty' => $row['qty'], 'unit' => $row['unit']]);
                        $updated++;
                    } else {
                        RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $row['ingredient']->id, 'qty' => $row['qty'], 'unit' => $row['unit']]);
                        $imported++;
                    }
                }

                return ['imported' => $imported, 'updated' => $updated] + $this->settleActiveFlag($recipe, $variant, $group, $created);
            });
        } catch (\Throwable $e) {
            report($e);
            $this->skipped += $group['rows'];
            $this->errors[] = "Variant '{$variant->code}' tidak diimport: terjadi kesalahan saat menyimpan. Recipe-nya tidak diubah.";

            return;
        }

        if (isset($result['abort'])) {
            $this->abortGroup($group, $result['abort']);

            return;
        }

        $this->imported += $result['imported'];
        $this->updated += $result['updated'];
        $this->notActivated += $result['not_activated'];

        if ($result['error'] !== null) {
            $this->errors[] = $result['error'];
        }
    }

    /**
     * Active request is honoured only when the finished Recipe passes every activation rule. Pure with
     * respect to the report: returns what to count/say, the caller adds it once the transaction commits.
     *
     * @return array{not_activated: int, error: ?string}
     */
    private function settleActiveFlag(Recipe $recipe, ProductVariant $variant, array $group, bool $created): array
    {
        $votes = $group['active_votes'];
        $wantsActive = $votes !== [] && ! in_array(false, $votes, true) && in_array(true, $votes, true);
        $explicitInactive = in_array(false, $votes, true);

        if ($wantsActive && ! $recipe->is_active) {
            $problems = $this->writer->activationProblems($recipe->fresh(), $variant);

            if ($problems) {
                return [
                    'not_activated' => 1,
                    'error' => "Variant '{$variant->code}': Recipe ".($created ? 'dibuat' : 'diperbarui').' tetapi TIDAK diaktifkan. '.implode(' ', $problems),
                ];
            }

            $recipe->update(['is_active' => true]);
        } elseif ($explicitInactive && $recipe->is_active) {
            $recipe->update(['is_active' => false]);
        }

        return ['not_activated' => 0, 'error' => null];
    }

    private function abortGroup(array $group, string $message): void
    {
        $this->skipped += $group['rows'];
        $this->errors[] = "Variant '{$group['variant']->code}' dilewati ({$group['rows']} baris). {$message}";
    }

    /** @return array<string, mixed> */
    private function &group(ProductVariant $variant, int $rowNumber, string $deniedLabel): array
    {
        if (! isset($this->groups[$variant->id])) {
            $variant->loadMissing('product');
            $denied = $this->policy->mutationDeniedMessage($this->user, $variant);

            $this->groups[$variant->id] = [
                'variant' => $variant,
                'denied' => $denied,
                'invalid' => false,
                'rows' => 0,
                'row_errors' => [],
                'items' => [],
                'active_votes' => [],
            ];

            if ($denied !== null) {
                $this->errors[] = "Baris {$rowNumber}: variant {$deniedLabel} dilewati. {$denied}";
            }
        }

        return $this->groups[$variant->id];
    }

    private function required(ProductVariant $variant): Collection
    {
        return $this->requiredCache[$variant->id] ??= $this->writer->requiredOutletIds($variant);
    }
}
