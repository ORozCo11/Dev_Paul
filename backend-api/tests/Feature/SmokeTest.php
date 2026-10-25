<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SMOKE TESTS
 *
 * Fast, shallow checks that the API is alive and its core guard rails respond.
 * If any of these fail, there is no point running the deeper tests.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_login_endpoint_is_reachable_and_validates_input(): void
    {
        // No credentials -> the validation layer should answer with 422.
        $this->postJson('/api/login', [])->assertStatus(422);
    }

    #[Test]
    public function protected_routes_reject_unauthenticated_requests(): void
    {
        // No bearer token -> Sanctum should answer with 401.
        $this->getJson('/api/vehicles')->assertStatus(401);
    }
}
