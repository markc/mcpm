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
        Schema::create('tool_handlers', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('name')->unique(); // e.g., 'calculator'
            $table->string('display_name'); // e.g., 'Calculator Tool'
            $table->text('description');
            $table->string('version')->default('1.0.0');

            // Technical Details
            $table->string('class_name'); // e.g., 'CalculatorTool'
            $table->string('namespace')->default('App\\Tools'); // e.g., 'App\\Tools'
            $table->string('file_path'); // e.g., 'app/Tools/CalculatorTool.php'

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_built_in')->default(false); // Prevents deletion of core handlers

            // Metadata
            $table->string('author')->nullable();
            $table->json('dependencies')->nullable(); // Required packages, etc.
            $table->json('input_schema_template')->nullable(); // Default schema template

            // Performance
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'sort_order']);
            $table->index('is_built_in');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_handlers');
    }
};
