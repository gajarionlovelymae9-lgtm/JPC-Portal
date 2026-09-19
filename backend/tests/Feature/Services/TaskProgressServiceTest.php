<?php

namespace Tests\Feature\Services;

use App\Exceptions\TaskManagement\InvalidProgressException;
use App\Services\TaskManagement\TaskProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Planning\PlanningTestCase;

class TaskProgressServiceTest extends PlanningTestCase
{
    use RefreshDatabase;

    public function test_progress_history_records_0_to_100(): void
    {
        $service = new TaskProgressService;
        $assignment = $this->makeAssignment($this->makeTask());
        $employee = $this->context()['employee'];

        foreach ([0, 25, 50, 75, 100] as $pct) {
            $service->updateProgress($assignment, (float) $pct, "Progress {$pct}%", $employee);
        }

        $this->assertCount(5, $assignment->fresh()->progress);
        $this->assertSame(100.0, (float) $assignment->fresh()->progress_percentage);
    }

    public function test_negative_progress_throws(): void
    {
        $service = new TaskProgressService;
        $assignment = $this->makeAssignment($this->makeTask());
        $employee = $this->context()['employee'];

        $this->expectException(InvalidProgressException::class);
        $service->updateProgress($assignment, -1.0, 'bad', $employee);
    }

    public function test_progress_above_100_throws(): void
    {
        $service = new TaskProgressService;
        $assignment = $this->makeAssignment($this->makeTask());
        $employee = $this->context()['employee'];

        $this->expectException(InvalidProgressException::class);
        $service->updateProgress($assignment, 101.0, 'bad', $employee);
    }

    public function test_backward_progress_throws(): void
    {
        $service = new TaskProgressService;
        $assignment = $this->makeAssignment($this->makeTask());
        $employee = $this->context()['employee'];

        $service->updateProgress($assignment, 75.0, 'first', $employee);

        $this->expectException(InvalidProgressException::class);
        $service->updateProgress($assignment, 50.0, 'regression', $employee);
    }
}
