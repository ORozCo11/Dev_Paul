<?php

namespace Tests\Unit\Rules;

use App\Rules\TextOnly;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * UNIT TESTS
 *
 * These test the TextOnly rule in complete isolation — no database, no HTTP,
 * no Laravel app boot. They prove the single requirement:
 * a "text only" field must NOT accept numbers.
 */
class TextOnlyRuleTest extends TestCase
{
    /**
     * Run the rule and return any validation messages it produced.
     * An empty array means the value passed.
     */
    private function failures(mixed $value): array
    {
        $messages = [];

        (new TextOnly())->validate('vehicle_color', $value, function (string $message) use (&$messages) {
            $messages[] = $message;
        });

        return $messages;
    }

    public static function validTextValues(): array
    {
        return [
            'single word' => ['Red'],
            'two words' => ['Pearl White'],
            'with slash separator' => ['White / Blue'],
            'with hyphen' => ['Pearl-White'],
        ];
    }

    public static function valuesContainingNumbers(): array
    {
        return [
            'pure digits' => ['12345'],
            'letters then digits' => ['Red123'],
            'digit inside word' => ['Bl4ue'],
            'word and number' => ['Blue 2'],
        ];
    }

    #[Test]
    #[DataProvider('validTextValues')]
    public function it_accepts_text_without_numbers(string $value): void
    {
        $this->assertEmpty(
            $this->failures($value),
            "Expected '{$value}' to be accepted as text-only."
        );
    }

    #[Test]
    #[DataProvider('valuesContainingNumbers')]
    public function it_rejects_any_value_that_contains_numbers(string $value): void
    {
        $messages = $this->failures($value);

        $this->assertNotEmpty(
            $messages,
            "Expected '{$value}' to be rejected because it contains numbers."
        );
        $this->assertStringContainsString('numbers are not allowed', $messages[0]);
    }
}
