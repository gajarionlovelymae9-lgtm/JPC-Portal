<?php

namespace App\Jobs\DataQuality;

use App\Models\User;
use App\Services\DataQuality\DataQualityScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDataQualityScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $scanType = 'full',
        public ?array $scope = null,
        public ?User $initiatedBy = null
    ) {}

    public function handle(DataQualityScanner $scanner): void
    {
        $scanner->runScan($this->scanType, $this->scope, $this->initiatedBy);
    }
}
