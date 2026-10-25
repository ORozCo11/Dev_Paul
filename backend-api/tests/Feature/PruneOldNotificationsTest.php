<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * notifications:prune deletes read notifications past a short retention
 * window (default 30 days) and ANY notification — read or not — past a
 * longer hard cap (default 90 days), so an unread one doesn't live forever
 * just because nobody clicked it. Everything else must survive untouched.
 */
class PruneOldNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeNotification(User $user, ?string $readAt, string $createdAt): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'title'   => 'Test',
            'message' => 'Test message',
            'type'    => 'ticket_closed',
        ]);

        // Backdate created_at/read_at directly — Eloquent's create() always
        // stamps "now", so the age has to be set after the fact.
        DB::table('notifications')->where('notification_id', $notification->notification_id)->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'read_at'    => $readAt,
        ]);

        return $notification->fresh();
    }

    #[Test]
    public function it_deletes_read_notifications_older_than_thirty_days()
    {
        $user = User::factory()->create();
        $old = $this->makeNotification($user, now()->subDays(40)->toDateTimeString(), now()->subDays(40)->toDateTimeString());

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['notification_id' => $old->notification_id]);
    }

    #[Test]
    public function it_keeps_read_notifications_within_thirty_days()
    {
        $user = User::factory()->create();
        $recent = $this->makeNotification($user, now()->subDays(10)->toDateTimeString(), now()->subDays(10)->toDateTimeString());

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertDatabaseHas('notifications', ['notification_id' => $recent->notification_id]);
    }

    #[Test]
    public function it_deletes_unread_notifications_older_than_ninety_days_regardless_of_read_status()
    {
        $user = User::factory()->create();
        $old = $this->makeNotification($user, null, now()->subDays(100)->toDateTimeString());

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['notification_id' => $old->notification_id]);
    }

    #[Test]
    public function it_keeps_unread_notifications_within_ninety_days()
    {
        $user = User::factory()->create();
        $recentUnread = $this->makeNotification($user, null, now()->subDays(60)->toDateTimeString());

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertDatabaseHas('notifications', ['notification_id' => $recentUnread->notification_id]);
    }

    #[Test]
    public function it_respects_custom_thresholds()
    {
        $user = User::factory()->create();
        $readSevenDaysAgo = $this->makeNotification($user, now()->subDays(7)->toDateTimeString(), now()->subDays(7)->toDateTimeString());

        $this->artisan('notifications:prune', ['--read-days' => 5, '--max-days' => 90])->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['notification_id' => $readSevenDaysAgo->notification_id]);
    }
}
