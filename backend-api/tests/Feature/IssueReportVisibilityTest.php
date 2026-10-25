<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleIssueReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The issue list scopes a Custodian to their own reports plus anything
 * Maintenance filed, and a mechanic to only their own — but opening a report
 * by id skipped that scoping entirely, so any of them could read any report
 * just by changing the number in the URL. showIssue() now applies the same
 * rule as issues().
 */
class IssueReportVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $category = VehicleCategory::create(['category_name' => 'Ambulance', 'description' => 'Test']);
        $this->vehicle = Vehicle::create([
            'vehicle_name' => 'Test Ambulance', 'plate_number' => 'TST 1234', 'category_id' => $category->category_id,
            'brand' => 'Toyota', 'model' => 'HiAce', 'year_model' => 2022, 'capacity' => '1000 kg',
            'vehicle_color' => 'White', 'current_location' => 'Main Depot',
        ]);
    }

    private function reportBy(User $user): VehicleIssueReport
    {
        return VehicleIssueReport::create([
            'vehicle_id' => $this->vehicle->vehicle_id,
            'issue_type' => 'Other',
            'issue_description' => 'Brakes squeal.',
            'severity_level' => 'Low',
            'reported_by' => $user->id,
            'status' => 'Pending',
        ]);
    }

    #[Test]
    public function a_custodian_cannot_open_another_custodians_report(): void
    {
        $owner = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $other = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $issue = $this->reportBy($owner);

        Sanctum::actingAs($other, ['*']);
        $this->getJson("/api/issues/{$issue->issue_report_id}")->assertForbidden();

        Sanctum::actingAs($owner, ['*']);
        $this->getJson("/api/issues/{$issue->issue_report_id}")->assertOk();
    }

    #[Test]
    public function a_custodian_can_still_open_a_report_filed_by_maintenance(): void
    {
        $mechanic = User::factory()->create(['role' => 'Maintenance Personnel', 'roles' => ['Maintenance Personnel']]);
        $issue = $this->reportBy($mechanic);

        Sanctum::actingAs(User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]), ['*']);
        $this->getJson("/api/issues/{$issue->issue_report_id}")->assertOk();
    }

    #[Test]
    public function a_mechanic_can_only_open_their_own_reports(): void
    {
        $custodian = User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]);
        $issue = $this->reportBy($custodian);

        Sanctum::actingAs(User::factory()->create(['role' => 'Maintenance Personnel', 'roles' => ['Maintenance Personnel']]), ['*']);
        $this->getJson("/api/issues/{$issue->issue_report_id}")->assertForbidden();
    }

    #[Test]
    public function an_admin_can_open_any_report(): void
    {
        $issue = $this->reportBy(User::factory()->create(['role' => 'Custodian', 'roles' => ['Custodian']]));

        Sanctum::actingAs(User::factory()->create(['role' => 'Admin', 'roles' => ['Admin']]), ['*']);
        $this->getJson("/api/issues/{$issue->issue_report_id}")->assertOk();
    }
}
