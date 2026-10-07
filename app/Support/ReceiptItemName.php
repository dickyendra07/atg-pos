<?php

namespace App\Support;

/**
 * The one place that turns a stored Product name + Variant name into the single item label printed on
 * receipts and shift summaries.
 *
 * Variant names are very often written as the full item name ("*Sedotan Boba" under Product "Sedotan"),
 * so joining the two blindly prints "Sedotan *Sedotan Boba". The rule is generic and deterministic (no
 * product names are hard-coded) and only ever looks at the text, never at prices, quantities or ids:
 *
 *  - no usable Variant name                         => the Product name
 *  - the Variant name already STARTS with the whole
 *    Product name (as whole words, ignoring case,
 *    punctuation and a leading marker such as "*")   => the Variant name, marker removed
 *  - anything else                                  => "Product Variant", exactly as before
 *
 * Nothing is stored or rewritten: historical order items keep their Product/Variant values.
 */
final class ReceiptItemName
{
    /** Leading decoration some Variant names carry ("*Sedotan Boba", "- Sedotan Boba"). */
    private const LEADING_MARKER = '/^[\s\*\-\x{2013}\x{2014}#\x{2022}\x{00B7}.:,_]+/u';

    public static function combine(?string $productName, ?string $variantName): string
    {
        $product = self::squish($productName);
        $variant = self::squish($variantName);

        $variantWords = self::words($variant);

        // Empty, or only punctuation ("-", "*"): there is no Variant name worth printing.
        if ($variantWords === []) {
            return $product;
        }

        $productWords = self::words($product);

        if ($productWords !== [] && array_slice($variantWords, 0, count($productWords)) === $productWords) {
            return self::squish(preg_replace(self::LEADING_MARKER, '', $variant) ?? $variant);
        }

        return $product === '' ? $variant : $product.' '.$variant;
    }

    /** Lower-cased letter/digit words, so "*Sedotan  Boba" and "sedotan boba" compare equal. */
    private static function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $matches);

        return $matches[0];
    }

    private static function squish(?string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $text));
    }
}
