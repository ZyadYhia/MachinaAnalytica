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
        Schema::create('compressor_air_blowers', function (Blueprint $table) {
            $table->id();
            $table->float('flow', 8);
            $table->float('temperature', 8);
            $table->float('pressure', 8);
            $table->float('vibration', 8);
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compressor_air_blowers');
    }
};
