<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SECURITY REGRESSION TESTS — upload extension spoofing / RCE via polyglot files.
 *
 * UploadsImages::storeUploadedImage() used to name the stored file after the
 * CLIENT-supplied original extension (getClientOriginalExtension()), even
 * though every call site validates the file's REAL content (the `image`
 * rule, or `mimes:...`, both of which check actual bytes via finfo — see
 * Illuminate\Validation\Concerns\ValidatesAttributes::validateMimes(), which
 * calls $value->guessExtension(), never the client's claimed name). A
 * "polyglot" upload — genuine image/PDF bytes with an original filename like
 * "shell.php" — passed content validation but was then stored AS
 * "shell.php", servable directly from the web root if the Supabase upload
 * ever falls back to the local `public` disk.
 *
 * `Illuminate\Http\Testing\File` (what `UploadedFile::fake()` returns) fakes
 * getMimeType() to whatever `$mimeType` is passed to create(), rather than
 * actually running finfo over dummy bytes — so passing a $mimeType that
 * disagrees with the fake filename's extension is exactly how these tests
 * stand in for "the real, server-detected content type", the same way
 * Laravel's own `mimes:`/`image` validation rules read it in production.
 *
 * storeVehicleDocument/updateVehicleDocument previously validated the file
 * as only ['required','file','max:10240'] — no content-type restriction at
 * all — so any file, any content, went straight through.
 */
class VehicleDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    private function vehicle(): Vehicle
    {
        $category = VehicleCategory::create([
            'category_name' => 'Ambulance ' . uniqid(),
            'description' => 'For testing',
        ]);

        return Vehicle::create([
            'vehicle_name' => 'Test Ambulance',
            'plate_number' => 'TST ' . random_int(1000, 9999),
            'category_id' => $category->category_id,
            'brand' => 'Toyota',
            'model' => 'HiAce',
            'year_model' => 2022,
            'capacity' => '1000 kg',
            'vehicle_color' => 'White',
            'current_location' => 'Main Depot',
        ]);
    }

    #[Test]
    public function a_polyglot_upload_is_stored_with_its_real_content_extension_not_the_client_claimed_one(): void
    {
        Storage::fake('supabase');
        $this->actingAsAdmin();
        $vehicle = $this->vehicle();

        // Original filename claims ".html" (an attacker's polyglot filename
        // — note Laravel's `mimes:` rule already hard-blocks the .php family
        // of extensions outright regardless of content, so .html is the
        // realistic case this fix actually needed to cover), but the file's
        // actual detected content type is a genuine PDF — exactly what lets
        // it pass `mimes:...,pdf,...` validation in the first place. The fix
        // must store this under ".pdf", never ".html" (which a browser would
        // happily render/execute as markup — stored XSS — if ever served
        // directly from a local-disk fallback).
        $file = UploadedFile::fake()->create('shell.html', 50, 'application/pdf');

        $response = $this->postJson("/api/vehicles/{$vehicle->vehicle_id}/documents", [
            'title' => 'Registration',
            'file' => $file,
        ]);

        $response->assertCreated();

        $document = VehicleDocument::first();
        $this->assertNotNull($document);
        $this->assertStringEndsWith('.pdf', $document->file_url);
        $this->assertStringNotContainsString('.html', $document->file_url);
    }

    #[Test]
    public function vehicle_documents_upload_rejects_a_disallowed_file_type(): void
    {
        Storage::fake('supabase');
        $this->actingAsAdmin();
        $vehicle = $this->vehicle();

        // No real image/PDF/doc content at all — a Windows executable.
        // Previously this endpoint validated only
        // ['required','file','max:10240'], so this sailed straight through.
        $file = UploadedFile::fake()->create('malware.exe', 50, 'application/x-msdownload');

        $response = $this->postJson("/api/vehicles/{$vehicle->vehicle_id}/documents", [
            'title' => 'Registration',
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('vehicle_documents', 0);
    }

    #[Test]
    public function vehicle_documents_upload_rejects_a_plain_text_file_masquerading_as_a_pdf_name(): void
    {
        Storage::fake('supabase');
        $this->actingAsAdmin();
        $vehicle = $this->vehicle();

        // Filename claims ".pdf", but the real content type is plain text —
        // the spoofed-extension case the `mimes:` rule (content-based) must
        // catch even though the bare `'file'` rule it replaces would not.
        $file = UploadedFile::fake()->create('registration.pdf', 10, 'text/plain');

        $response = $this->postJson("/api/vehicles/{$vehicle->vehicle_id}/documents", [
            'title' => 'Registration',
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('vehicle_documents', 0);
    }

    #[Test]
    public function a_legitimate_pdf_upload_still_works_end_to_end(): void
    {
        Storage::fake('supabase');
        $this->actingAsAdmin();
        $vehicle = $this->vehicle();

        $file = UploadedFile::fake()->create('registration.pdf', 50, 'application/pdf');

        $response = $this->postJson("/api/vehicles/{$vehicle->vehicle_id}/documents", [
            'title' => 'Registration',
            'category' => 'Legal',
            'file' => $file,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('vehicle_documents', [
            'vehicle_id' => $vehicle->vehicle_id,
            'title' => 'Registration',
        ]);

        $document = VehicleDocument::first();
        $this->assertStringEndsWith('.pdf', $document->file_url);
    }

    #[Test]
    public function a_legitimate_image_upload_still_works_end_to_end(): void
    {
        Storage::fake('supabase');
        $this->actingAsAdmin();
        $vehicle = $this->vehicle();

        $file = UploadedFile::fake()->create('insurance.jpg', 50, 'image/jpeg');

        $response = $this->postJson("/api/vehicles/{$vehicle->vehicle_id}/documents", [
            'title' => 'Insurance',
            'file' => $file,
        ]);

        $response->assertCreated();

        $document = VehicleDocument::first();
        // Symfony's mime->extension map lists "jpg" first for image/jpeg.
        $this->assertStringEndsWith('.jpg', $document->file_url);
    }

    #[Test]
    public function updating_a_document_with_a_polyglot_file_also_stores_the_real_content_extension(): void
    {
        Storage::fake('supabase');
        $this->actingAsAdmin();
        $vehicle = $this->vehicle();

        $this->postJson("/api/vehicles/{$vehicle->vehicle_id}/documents", [
            'title' => 'Registration',
            'file' => UploadedFile::fake()->create('registration.pdf', 50, 'application/pdf'),
        ])->assertCreated();

        $document = VehicleDocument::first();

        $response = $this->putJson("/api/documents/{$document->document_id}", [
            'file' => UploadedFile::fake()->create('shell.html', 50, 'application/pdf'),
        ]);

        $response->assertOk();
        $document->refresh();
        $this->assertStringEndsWith('.pdf', $document->file_url);
        $this->assertStringNotContainsString('.html', $document->file_url);
    }
}
