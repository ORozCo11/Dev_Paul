<?php

namespace Tests\Feature;

use App\Models\{Barangay, ManagedFile, MaintenanceType, User, Vehicle, VehicleCategory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegressionAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(): Vehicle
    {
        $category = VehicleCategory::create(['category_name' => 'QA Rescue', 'domain' => 'Land']);
        return Vehicle::create(['vehicle_name' => 'QA Unit', 'plate_number' => 'QA 1021', 'category_id' => $category->category_id, 'brand' => 'Toyota', 'model' => 'HiAce', 'year_model' => 2022, 'capacity' => '12', 'vehicle_color' => 'White', 'current_location' => 'Depot']);
    }

    public function test_plain_api_requests_are_unauthorized_instead_of_crashing(): void
    {
        $this->get('/api/vehicles')->assertUnauthorized();
        $this->get('/api/vehicles', ['Accept' => 'text/html'])->assertUnauthorized();
    }

    public function test_schedule_submission_cannot_create_a_shared_catalog_entry(): void
    {
        $custodian = User::factory()->create(['role' => 'Custodian']);
        $mechanic = User::factory()->create(['role' => 'Maintenance Personnel']);
        Sanctum::actingAs($custodian);
        $vehicle = $this->vehicle();
        $this->postJson('/api/maintenance-types', ['name' => 'Explicit Catalog Entry'])->assertCreated();
        $this->assertDatabaseHas('maintenance_types', ['name' => 'Explicit Catalog Entry']);
        $this->postJson('/api/maintenance-schedules', ['vehicle_id' => $vehicle->vehicle_id, 'maintenance_type' => 'Unauthorized Catalog', 'scheduled_date' => now()->addMonth()->toDateString(), 'assigned_to' => $mechanic->id])->assertCreated();
        $this->assertDatabaseMissing('maintenance_types', ['name' => 'Unauthorized Catalog']);
        MaintenanceType::create(['name' => 'Approved Name']);
        $this->assertSame('Approved Name', MaintenanceType::resolve('approved name'));
        $this->postJson('/api/issues', ['vehicle_id' => $vehicle->vehicle_id, 'issue_type' => 'Unapproved Fault', 'severity_level' => 'High', 'issue_description' => 'Brake test failed.'])->assertCreated();
        $this->assertDatabaseMissing('fault_categories', ['name' => 'Unapproved Fault']);
        $this->postJson('/api/tickets/propose', ['vehicle_id' => $vehicle->vehicle_id, 'ticket_description' => 'Repair the brake problem.', 'priority' => 'High', 'sub_issues' => [['title' => 'Repair brakes', 'maintenance_type' => 'Unapproved Repair Type']]])->assertCreated();
        $this->assertDatabaseMissing('maintenance_types', ['name' => 'Unapproved Repair Type']);
    }

    public function test_document_bytes_are_private_links_expire_and_deletion_revokes_them(): void
    {
        Storage::fake('supabase');
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'Admin']);
        Sanctum::actingAs($admin);
        $vehicle = $this->vehicle();
        $result = $this->postJson("/api/vehicles/{$vehicle->vehicle_id}/documents", ['title' => 'QA document', 'file' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')])->assertCreated();
        $url = $result->json('file_url');
        $asset = ManagedFile::firstOrFail();
        $this->assertStringContainsString('signature=', $url);
        Storage::disk('supabase')->assertExists($asset->path);
        Storage::disk('public')->assertMissing($asset->path);
        // Windows' local fake cannot report POSIX visibility; the private
        // root and absence from the public disk are still checked here.
        if (PHP_OS_FAMILY !== 'Windows') $this->assertSame('private', Storage::disk('supabase')->getVisibility($asset->path));
        $this->get($asset->reference())->assertForbidden();
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
        $renewed = $this->getJson('/api/files/refresh?'.http_build_query(['url' => $url]))->assertOk()->json('url');
        $this->get($renewed)->assertOk();
        $this->getJson('/api/files/refresh?'.http_build_query(['url' => $asset->reference()]))->assertUnprocessable();
        $this->travelBack();
        $this->deleteJson('/api/documents/'.$result->json('document_id'))->assertOk();
        Storage::disk('supabase')->assertMissing($asset->path);
        $this->get($url)->assertNotFound();
    }

    public function test_a_foreign_file_reference_is_not_signed_by_an_authorized_response(): void
    {
        Storage::fake('supabase');
        $other = Barangay::create(['name' => 'Other Barangay']);
        $admin = User::factory()->create(['role' => 'Admin']);
        Sanctum::actingAs($admin);
        $asset = ManagedFile::create(['id' => 'foreign.pdf', 'disk' => 'supabase', 'path' => 'foreign.pdf', 'directory' => 'vehicle-documents', 'barangay_id' => $other->id]);
        $vehicle = $this->vehicle();
        \App\Models\VehicleDocument::create(['vehicle_id' => $vehicle->vehicle_id, 'title' => 'Broken foreign reference', 'file_url' => $asset->reference(), 'added_by' => $admin->id]);
        $result = $this->getJson("/api/vehicles/{$vehicle->vehicle_id}/documents")->assertOk();
        $this->assertSame($asset->reference(), $result->json('0.file_url'));
    }

    public function test_failed_cloud_upload_falls_back_to_private_local_storage(): void
    {
        Storage::fake('local'); Storage::fake('public');
        $cloud = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $cloud->shouldReceive('putFileAs')->once()
            ->with('vehicle-documents', \Mockery::type(UploadedFile::class), \Mockery::type('string'), 'private')
            ->andThrow(new \RuntimeException('Cloud unavailable'));
        Storage::set('supabase', $cloud);
        Sanctum::actingAs(User::factory()->create(['role' => 'Admin']));
        $vehicle = $this->vehicle();
        $this->postJson("/api/vehicles/{$vehicle->vehicle_id}/documents", ['title' => 'Private fallback', 'file' => UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf')])->assertCreated();
        $asset = ManagedFile::firstOrFail();
        $this->assertSame('local', $asset->disk);
        Storage::disk('local')->assertExists($asset->path);
        Storage::disk('public')->assertMissing($asset->path);
    }

    public function test_legacy_migration_preserves_bytes_and_revokes_the_public_copy(): void
    {
        Storage::fake('local'); Storage::fake('public');
        $admin = User::factory()->create(['role' => 'Admin']);
        Sanctum::actingAs($admin);
        $vehicle = $this->vehicle();
        $url = rtrim(config('app.url'), '/').'/storage/vehicle-documents/old.pdf';
        Storage::disk('public')->put('vehicle-documents/old.pdf', '%PDF-1.4 legacy bytes');
        \App\Models\VehicleDocument::create(['vehicle_id' => $vehicle->vehicle_id, 'title' => 'Legacy', 'file_url' => $url, 'added_by' => $admin->id]);
        $this->artisan('media:privatize')->assertSuccessful();
        Storage::disk('public')->assertExists('vehicle-documents/old.pdf');
        $this->artisan('media:privatize', ['--apply' => true])->assertSuccessful();
        Storage::disk('public')->assertMissing('vehicle-documents/old.pdf');
        $asset = ManagedFile::where('original_url', $url)->firstOrFail();
        $this->assertSame('%PDF-1.4 legacy bytes', Storage::disk('local')->get($asset->path));
        $this->assertStringContainsString('signature=', $this->getJson("/api/vehicles/{$vehicle->vehicle_id}/documents")->json('0.file_url'));
    }

    public function test_file_link_renewal_rechecks_the_current_repair_assignment(): void
    {
        $mechanic = User::factory()->create(['role' => 'Maintenance Personnel']);
        $replacement = User::factory()->create(['role' => 'Maintenance Personnel']);
        $vehicle = $this->vehicle();
        $ticket = \App\Models\MaintenanceTicket::create(['vehicle_id' => $vehicle->vehicle_id, 'ticket_title' => 'QA repair', 'ticket_description' => 'QA repair evidence', 'created_by' => $mechanic->id, 'assigned_mechanic_id' => $mechanic->id]);
        $asset = ManagedFile::create(['id' => 'repair-proof.png', 'disk' => 'local', 'path' => 'repair-proof.png', 'directory' => 'repair-attachments']);
        $line = $ticket->subIssues()->create(['title' => 'Repair', 'created_by' => $mechanic->id, 'assigned_mechanic_id' => $mechanic->id, 'attachment_files' => [['url' => $asset->reference(), 'name' => 'Proof']]]);
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('files.show', now()->addMinutes(5), ['file' => $asset->id]);
        Sanctum::actingAs($mechanic);
        $this->getJson('/api/files/refresh?'.http_build_query(['url' => $url]))->assertOk();
        $ticket->update(['assigned_mechanic_id' => $replacement->id]);
        $line->update(['assigned_mechanic_id' => $replacement->id]);
        $this->getJson('/api/files/refresh?'.http_build_query(['url' => $url]))->assertForbidden();
    }
}
