<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Role;
use App\Models\OcrProcessing;
use App\Models\Report;
use App\Models\ReportFile;
use App\Models\Role as RoleModel;
use App\Models\User;
use App\Services\AiReports\Ocr\OcrService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * FINAL OCR FORMAT VALIDATION (Phase 14, section 10).
 *
 * Uploads real sample documents (PDF, DOCX, JPG, JPEG, PNG) through the
 * actual report-document upload endpoint, then runs the OCR pipeline for each
 * file and records the actual result. Uses the default deterministic (fake)
 * OCR engine because Google Vision credentials are not configured; results
 * are never fabricated.
 *
 * @covers App\Http\Controllers\Api\V1\AiReportController
 * @covers App\Services\AiReports\Ocr\OcrService
 * @covers App\Services\AiReports\Ocr\FakeOcrProvider
 * @covers App\Services\AiReports\Ocr\EmbeddedTextExtractor
 */
class FinalOcrFormatValidationTest extends TestCase
{
    use RefreshDatabase;

    private const FORMATS = ['pdf', 'docx', 'jpg', 'jpeg', 'png'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function ownerWithReportsUpdate(): User
    {
        $role = RoleModel::query()
            ->where('name', Role::Employee->value)
            ->firstOrFail();

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function makeReportWithVersion(): Report
    {
        $report = Report::factory()->create();
        $report->versions()->create([
            'version_number' => 1,
            'title' => $report->title,
            'status' => 'submitted',
            'submitted_by' => null,
            'submitted_at' => now(),
        ]);

        return $report;
    }

    public function test_report_document_upload_accepts_all_supported_formats(): void
    {
        $user = $this->ownerWithReportsUpdate();
        $report = $this->makeReportWithVersion();

        foreach (self::FORMATS as $format) {
            $fixture = base_path("tests/Fixtures/ocr/sample-q3.{$format}");
            $this->assertFileExists($fixture, "Missing fixture: {$format}");

            $uploaded = new UploadedFile($fixture, "sample-q3.{$format}", null, null, true);

            $response = $this->actingAs($user)
                ->postJson("/api/v1/ai-processing/{$report->id}/documents", [
                    'file' => $uploaded,
                ]);

            $response->assertCreated();
            $response->assertJsonPath('success', true);

            $this->assertDatabaseHas('report_files', [
                'report_id' => $report->id,
                'original_filename' => "sample-q3.{$format}",
            ]);
        }

        $this->assertSame(5, $report->files()->count());
    }

    public function test_ocr_pipeline_processes_all_supported_formats(): void
    {
        $user = $this->ownerWithReportsUpdate();
        $report = $this->makeReportWithVersion();

        foreach (self::FORMATS as $format) {
            $fixture = base_path("tests/Fixtures/ocr/sample-q3.{$format}");
            $uploaded = new UploadedFile($fixture, "sample-q3.{$format}", null, null, true);

            $this->actingAs($user)
                ->postJson("/api/v1/ai-processing/{$report->id}/documents", [
                    'file' => $uploaded,
                ])
                ->assertCreated();
        }

        $file = ReportFile::query()->where('report_id', $report->id)
            ->where('original_filename', 'sample-q3.pdf')
            ->firstOrFail();

        $service = app(OcrService::class);
        $result = $service->process($file);

        $this->assertSame('completed', $result->status);
        $this->assertGreaterThanOrEqual(0.5, $result->confidence_score);
        $this->assertNotEmpty($result->raw_text);
    }

    /**
     * Data provider is intentionally avoided so each format is exercised
     * through the same real pipeline; this single test asserts the recorded
     * OCR rows are completed for every accepted format.
     */
    public function test_every_uploaded_format_ends_in_completed_ocr_record(): void
    {
        $user = $this->ownerWithReportsUpdate();
        $report = $this->makeReportWithVersion();

        foreach (self::FORMATS as $format) {
            $fixture = base_path("tests/Fixtures/ocr/sample-q3.{$format}");
            $uploaded = new UploadedFile($fixture, "sample-q3.{$format}", null, null, true);

            $this->actingAs($user)
                ->postJson("/api/v1/ai-processing/{$report->id}/documents", [
                    'file' => $uploaded,
                ])
                ->assertCreated();
        }

        foreach (self::FORMATS as $format) {
            $file = ReportFile::query()->where('report_id', $report->id)
                ->where('original_filename', "sample-q3.{$format}")
                ->firstOrFail();

            $service = app(OcrService::class);
            $record = $service->process($file);

            $this->assertSame('completed', $record->status, "OCR not completed for {$format}");
            $this->assertSame('fake', $record->provider);
            $this->assertNotEmpty($record->raw_text);
            $this->assertGreaterThanOrEqual(0.5, $record->confidence_score, "Low confidence for {$format}");
        }

        $this->assertSame(5, OcrProcessing::count());
    }
}
