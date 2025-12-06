<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('llm_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // jan, anythingllm, openai, claude, etc.
            $table->string('key'); // Setting key like 'api_url', 'auth_token', 'temperature', etc.
            $table->text('value')->nullable(); // Setting value (encrypted for sensitive data)
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'provider', 'key']);
            $table->index(['user_id', 'provider']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_settings');
    }
};
