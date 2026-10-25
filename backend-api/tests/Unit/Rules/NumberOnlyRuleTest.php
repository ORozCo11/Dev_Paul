<?php

namespace Tests\Unit\Rules;

use App\Rules\NumberOnly;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * UNIT TESTS
 *
 * These test the NumberOnly rule in complete isolation — no database, no HTTP,
 * no Laravel app boot. They prove the single requirement:
 * a "numbers only" field must NOT accept letters.
 */
class NumberOnlyRuleTest extends TestCase
{
    /**
     * Run the rule and return any validation messages it produced.
     * An empty array means the value passed.
     */
    private function failures(mixed $value): array
    {
        $messages = [];

        (new NumberOnly())->validate('year_model', $value, function (string $message) use (&$messages) {
            $messages[] = $message;
        });

        return $messages;
    }

    public static function validNumberValues(): array
    {
        return [
            'four digit year' => ['2024'],
            'integer type' => [2024],
            'single digit' => ['7'],
        ];
    }

    public static function valuesContainingLetters(): array
    {
        return [
            'digits then letters' => ['2024abc'],
            'spelled out' => ['twenty'],
            'letter inside number' => ['20a4'],
            'decimal (non-digit symbol)' => ['20.5'],
        ];
    }

    #[Test]
    #[DataProvider('validNumberValues')]
    public function it_accepts_numbers_without_letters(mixed $value): void
    {
        $this->assertEmpty(
            $this->failures($value),
            "Expected '{$value}' to be accepted as numbers-only."
        );
    }

    #[Test]
    #[DataProvider('valuesContainingLetters')]
    public function it_rejects_any_value_that_contains_letters(string $value): void
    {
        $messages = $this->failures($value);

        $this->assertNotEmpty(
            $messages,
            "Expected '{$value}' to be rejected because it contains letters."
        );
    }
}
