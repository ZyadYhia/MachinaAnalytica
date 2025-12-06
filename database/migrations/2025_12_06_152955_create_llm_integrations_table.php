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
        Schema::create('llm_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('active_integration')->default('none'); // jan, anythingllm, none
            $table->string('integration_status')->default('offline'); // online, offline
            $table->string('active_model')->nullable();
            $table->string('model_provider')->nullable();
            $table->string('chat_mode')->default('sync'); // sync, async
            $table->timestamp('last_health_check_at')->nullable();
            $table->json('provider_config')->nullable(); // Store provider-specific configurations
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['active_integration', 'integration_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_integrations');
    }
};
