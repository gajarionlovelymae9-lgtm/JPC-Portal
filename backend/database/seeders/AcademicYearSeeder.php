<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds the current academic year from the JPC configuration, plus the
 * immediately preceding academic year so date filtering and history can be
 * exercised in development.
 *
 * Dates are read from config/jpc.php (env-driven) so the school's official
 * calendar does not have to be hardcoded in application logic. Idempotent.
 */
class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        $code = config('jpc.academic_year.code');
        $startDate = config('jpc.academic_year.start_date');
        $endDate = config('jpc.academic_year.end_date');

        AcademicYear::updateOrCreate(
            ['name' => $code],
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'active',
            ],
        );

        $priorStart = Carbon::parse($startDate)->subYear();
        $priorEnd = Carbon::parse($endDate)->subYear();

        AcademicYear::updateOrCreate(
            ['name' => $this->priorYearName($code)],
            [
                'start_date' => $priorStart->toDateString(),
                'end_date' => $priorEnd->toDateString(),
                'status' => 'completed',
            ],
        );
    }

    /**
     * Derive "2025-2026" from a configured "2026-2027" code.
     */
    private function priorYearName(string $code): string
    {
        if (preg_match('/^(\d{4})-(\d{4})$/', $code, $matches) === 1) {
            return ((int) $matches[1] - 1).'-'.((int) $matches[2] - 1);
        }

        return $code.' (prior)';
    }
}
