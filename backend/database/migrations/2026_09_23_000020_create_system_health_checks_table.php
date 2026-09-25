<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_health_checks', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->index(); // ok | degraded
            $table->string('source', 40)->default('manual'); // manual | scheduled | api
            $table->json('checks')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamp('checked_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health_checks');
    }
};