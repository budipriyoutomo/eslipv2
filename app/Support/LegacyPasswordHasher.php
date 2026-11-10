<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Verifies passwords stored by the legacy CodeIgniter app (unsalted sha1).
 *
 * The legacy login ran CI's `trim` form rule before sha1(), so both the raw and the trimmed
 * input are accepted. Comparison is timing-safe.
 */
final readonly class LegacyPasswordHasher
{
    public function check(#[\SensitiveParameter] string $plain, string $stored): bool
    {
        $stored = strtolower(trim($stored));

        if (! self::looksLikeSha1($stored)) {
            return false;
        }

        foreach (array_unique([$plain, trim($plain)]) as $candidate) {
            if (hash_equals($stored, sha1($candidate))) {
                return true;
            }
        }

        return false;
    }

    public static function looksLikeSha1(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{40}\z/i', $value) === 1;
    }
}
