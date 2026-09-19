<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\ReportFile;
use App\Models\ReportVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReportFile>
 */
class ReportFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::random(40);

        return [
            'report_id' => Report::factory(),
            'report_version_id' => ReportVersion::factory(),
            'original_filename' => $this->faker->randomElement(['report.pdf', 'report.docx', 'report.png']),
            'stored_filename' => $name.'.pdf',
            'storage_disk' => 'local',
            'file_path' => 'reports/'.$name.'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(1024, 512000),
            'checksum' => hash('sha256', Str::random(32)),
            'uploaded_by' => User::factory(),
        ];
    }

    public function pdf(): static
    {
        $name = Str::random(40);

        return $this->state(fn (): array => [
            'original_filename' => 'report.pdf',
            'stored_filename' => $name.'.pdf',
            'file_path' => 'reports/'.$name.'.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function docx(): static
    {
        $name = Str::random(40);

        return $this->state(fn (): array => [
            'original_filename' => 'report.docx',
            'stored_filename' => $name.'.docx',
            'file_path' => 'reports/'.$name.'.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function image(): static
    {
        $name = Str::random(40);

        return $this->state(fn (): array => [
            'original_filename' => 'report.png',
            'stored_filename' => $name.'.png',
            'file_path' => 'reports/'.$name.'.png',
            'mime_type' => 'image/png',
        ]);
    }
}