<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * "Numbers only" field rule.
 *
 * Passes when the value contains digits 0-9 ONLY. It rejects anything
 * containing a letter (or any non-digit symbol).
 *
 * Use for fields that should never contain letters, e.g. a vehicle's year model.
 *   Accepted: "2024", 2024
 *   Rejected: "2024abc", "twenty", "20a4", "20.5"
 */
class NumberOnly implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = is_string($value) ? $value : (string) $value;

        // Core requirement: a "numbers only" field must not accept letters.
        if (preg_match('/\p{L}/u', $value)) {
            $fail('The :attribute field must contain numbers only (letters are not allowed).');

            return;
        }

        // Beyond letters, only the digits 0-9 are allowed (no spaces/symbols/decimals).
        if (! preg_match('/^\d+$/', $value)) {
            $fail('The :attribute field may only contain digits (0-9).');
        }
    }
}
