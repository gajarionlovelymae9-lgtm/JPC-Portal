<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_chat_conversations')->onDelete('cascade');
            $table->string('role');
            $table->longText('content');
            $table->string('intent')->nullable();
            $table->json('tool_results')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->index('conversation_id');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
