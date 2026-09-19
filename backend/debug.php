<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    $ctrl = $app->make(App\Http\Controllers\Api\V1\Reports\ReportController::class);
    echo "CONTROLLER OK\n";
    $svc = $app->make(App\Services\Reports\PerformanceCalculationService::class);
    echo "SERVICE OK\n";
    $method = new ReflectionMethod($ctrl, 'overview');
    echo "METHOD EXISTS: " . $method->getName() . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
