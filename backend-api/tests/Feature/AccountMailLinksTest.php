<?php

namespace Tests\Feature;

use App\Mail\AccountMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The credentials email always carries a link to the sign-in page, on the
 * frontend address in FRONTEND_URL, including while VMS runs locally.
 */
class AccountMailLinksTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function emails_show_the_sign_in_link_even_while_the_frontend_is_local(): void
    {
        config(['app.frontend_url' => 'http://localhost:5173']);

        $html = (new AccountMail(User::factory()->create(), 'Credentials', 'credentials', ['temporaryPassword' => 'Abcd2345Xy', 'createdBy' => 'Admin', 'expiresAt' => now()->addHours(72)]))->render();

        $this->assertStringContainsString('Sign in to VMS', $html);
        $this->assertStringContainsString('http://localhost:5173/login', $html);
    }

    #[Test]
    public function emails_show_link_buttons_once_deployed(): void
    {
        config(['app.frontend_url' => 'https://vms.example.ph']);

        $html = (new AccountMail(User::factory()->create(), 'Credentials', 'credentials', ['temporaryPassword' => 'Abcd2345Xy', 'createdBy' => 'Admin', 'expiresAt' => now()->addHours(72)]))->render();

        $this->assertStringContainsString('Sign in to VMS', $html);
        $this->assertStringContainsString('https://vms.example.ph/login', $html);
    }
}
