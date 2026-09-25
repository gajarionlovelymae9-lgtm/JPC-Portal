<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompt_versions', function (Blueprint $table) {
            $table->id();
            $table->string('purpose', 60)->index(); // summary | validation | reference | similarity | insight | chat
            $table->string('provider', 100)->default('fake');
            $table->string('model', 120)->nullable();
            $table->string('version', 40)->index(); // e.g. Summary Prompt v1.0
            $table->text('description')->nullable();
            $table->date('versioned_at')->nullable();
            $table->timestamps();

            $table->unique(['purpose', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_versions');
    }
};