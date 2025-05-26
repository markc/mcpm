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
        Schema::table('tool_handlers', function (Blueprint $table) {
            $table->enum('handler_type', ['php', 'bash'])->default('php')->after('name');
            $table->text('script_content')->nullable()->after('file_path'); // For storing bash scripts
            $table->json('environment_variables')->nullable()->after('script_content'); // Env vars for bash
            $table->integer('timeout_seconds')->default(30)->after('environment_variables'); // Execution timeout

            // Update indexes
            $table->index(['handler_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tool_handlers', function (Blueprint $table) {
            $table->dropIndex(['handler_type', 'is_active']);
            $table->dropColumn(['handler_type', 'script_content', 'environment_variables', 'timeout_seconds']);
        });
    }
};
