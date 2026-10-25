<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * "Text only" field rule.
 *
 * Passes when the value contains letters, spaces and a few common separators
 * (/ - . , ') ONLY. It rejects anything containing a digit.
 *
 * Use for fields that should never contain numbers, e.g. a vehicle's colour.
 *   Accepted: "Red", "White / Blue", "Pearl-White"
 *   Rejected: "Red123", "12345", "Blue2"
 */
class TextOnly implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = is_string($value) ? $value : (string) $value;

        // Core requirement: a "text only" field must not accept numbers.
        if (preg_match('/\d/', $value)) {
            $fail('The :attribute field must contain text only (numbers are not allowed).');

            return;
        }

        // Beyond digits, only letters, spaces and basic separators are allowed.
        if (! preg_match("/^[\p{L}\s\/\-.,']+$/u", $value)) {
            $fail('The :attribute field may only contain letters and spaces.');
        }
    }
}
