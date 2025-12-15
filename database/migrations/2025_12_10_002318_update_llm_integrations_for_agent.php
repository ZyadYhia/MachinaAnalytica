<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('llm_integrations', function (Blueprint $table) {
            // Update default value for active_integration
            $table->string('active_integration')->default('agent')->change();
        });

        // Migrate existing users to 'agent'
        DB::table('llm_integrations')->update([
            'active_integration' => 'agent',
            'active_model' => 'agent-default',
            'integration_status' => 'online', // Assuming agent is always online initially or let health check update it
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('llm_integrations', function (Blueprint $table) {
            $table->string('active_integration')->default('none')->change();
        });
    }
};
