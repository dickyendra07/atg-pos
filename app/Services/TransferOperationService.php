<?php

namespace App\Services;

use App\Exceptions\TransferOperationBusy;
use App\Exceptions\TransferOperationConflict;
use App\Models\TransferOperation;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Idempotency for operations that create transfers: ONE business operation = ONE committed set of postings.
 *
 * claim() must be the FIRST statement of the posting transaction. The UNIQUE index on operation_key is the atomic
 * claim: the first request inserts the row (uncommitted until its postings commit); a second request with the same
 * key blocks on that index until the first one finishes, then either gets the duplicate-key answer (the first
 * committed: this is a replay, post nothing) or inserts successfully (the first rolled back: this is the retry).
 * Because the claim shares the transaction with the stock postings, a rollback removes the claim as well, and a
 * crash can never leave a claim without its postings or postings without a claim.
 */
class TransferOperationService
{
    /** Bumped only if the canonical form below ever changes; keeps old and new fingerprints from colliding. */
    private const FINGERPRINT_VERSION = 1;

    /**
     * Claims the key, or recognises an operation that already committed.
     *
     * @return array{0: TransferOperation, 1: bool} [the operation, true when this call claimed it (new work) / false when it is a replay]
     *
     * @throws TransferOperationConflict the key belongs to another user, kind or payload
     * @throws TransferOperationBusy     the first request did not finish within the database lock wait
     */
    public function claim(string $operationKey, int $userId, string $kind, string $fingerprint): array
    {
        try {
            return [TransferOperation::create([
                'operation_key' => $operationKey,
                'user_id' => $userId,
                'kind' => $kind,
                'fingerprint' => $fingerprint,
                'transfer_ids' => [],
            ]), true];
        } catch (UniqueConstraintViolationException $e) {
            // Only a clash on the operation-key index is a replay. Any other unique violation keeps its meaning.
            if (! str_contains($e->getMessage(), 'operation_key')) {
                throw $e;
            }

            $original = $e;
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1205 || str_contains($e->getMessage(), 'Lock wait timeout exceeded')) {
                throw new TransferOperationBusy();
            }

            throw $e;
        }

        // Shared lock = latest committed row (never an older snapshot) and compatible with the other waiters.
        $existing = TransferOperation::where('operation_key', $operationKey)->sharedLock()->first();

        if (! $existing) {
            throw $original;   // a duplicate key with no row to explain it: never guess, let it surface
        }

        if (
            (int) $existing->user_id !== $userId
            || $existing->kind !== $kind
            || ! hash_equals((string) $existing->fingerprint, $fingerprint)
        ) {
            throw new TransferOperationConflict();
        }

        return [$existing, false];
    }

    /**
     * SHA-256 of the normalized business payload (never of the operation key itself).
     *
     * Order of items is part of the operation: they are posted, and their rows created, in the submitted order, and
     * a repeated ingredient is a separate line that is kept. Optional empty values are normalized to null.
     *
     * @param  array{type: string, id: int}  $from
     * @param  array{type: string, id: int}  $to
     * @param  array<int, array{ingredient_id: int|string, qty: mixed}>  $items
     */
    public static function fingerprint(array $from, array $to, string $sender, ?string $receiver, ?string $note, array $items): string
    {
        $blank = static fn (?string $value): ?string => ($value = trim((string) $value)) === '' ? null : $value;

        $canonical = [
            'v' => self::FINGERPRINT_VERSION,
            'from' => $from['type'].':'.(int) $from['id'],
            'to' => $to['type'].':'.(int) $to['id'],
            'sender' => trim($sender),
            'receiver' => $blank($receiver),
            'note' => $blank($note),
            'items' => array_map(
                static fn (array $item): array => [(int) $item['ingredient_id'], self::canonicalQty($item['qty'])],
                array_values($items)
            ),
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * The quantity exactly as it will be stored (DECIMAL with 2 decimals): "5", "5.0", 5, 5.00 and "+05.00" all
     * become "5.00". A value that cannot be stored exactly (more than 2 significant decimals, an exponent, text) is
     * refused instead of being rounded into a different, valid-looking quantity.
     *
     * @throws \InvalidArgumentException
     */
    public static function canonicalQty(mixed $qty): string
    {
        $text = match (true) {
            is_int($qty) => (string) $qty,
            is_float($qty) => rtrim(rtrim(sprintf('%.12F', $qty), '0'), '.'),
            is_string($qty) => trim($qty),
            default => throw new \InvalidArgumentException('Quantity must be a number.'),
        };

        if (! preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $text, $m) || ($m[2] === '' && ($m[3] ?? '') === '')) {
            throw new \InvalidArgumentException("Quantity '{$text}' is not a plain decimal number.");
        }

        $fraction = $m[3] ?? '';

        if (strlen(rtrim($fraction, '0')) > 2) {
            throw new \InvalidArgumentException("Quantity '{$text}' has more than 2 decimals and cannot be stored exactly.");
        }

        $whole = ltrim($m[2], '0') ?: '0';
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $negative = $m[1] === '-' && ($whole !== '0' || $fraction !== '00');

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }
}
