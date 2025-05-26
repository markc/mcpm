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
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Tool identifier (snake_case)
            $table->string('display_name'); // Human-readable name
            $table->text('description'); // Tool description
            $table->json('input_schema'); // JSON schema for input validation
            $table->string('handler_class'); // Fully qualified class name
            $table->boolean('is_active')->default(true); // Whether tool is available
            $table->json('settings')->nullable(); // Additional configuration
            $table->integer('sort_order')->default(0); // Display order
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};
