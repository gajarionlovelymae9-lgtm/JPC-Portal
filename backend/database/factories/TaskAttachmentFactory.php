<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskAttachment>
 */
class TaskAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $file = $this->faker->randomElement([
            'monthly_report_sample.pdf',
            'lesson_plan_sample.docx',
            'student_progress_sample.xlsx',
            'faculty_accomplishment_sample.pdf',
        ]);

        return [
            'task_id' => Task::factory(),
            'task_assignment_id' => null,
            'uploaded_by' => User::factory(),
            'file_name' => $file,
            'file_path' => 'development/task-attachments/'.$file,
            'mime_type' => $this->faker->randomElement([
                'application/pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]),
            'file_size' => $this->faker->numberBetween(50_000, 2_000_000),
        ];
    }
}
