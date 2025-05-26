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
        Schema::create('mcp_tool_run_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tool_name');
            $table->json('tool_input')->nullable();
            $table->json('tool_output')->nullable();
            $table->boolean('is_error')->default(false);
            $table->string('error_type')->nullable();
            $table->text('error_message')->nullable();
            $table->json('raw_request_payload')->nullable();
            $table->ipAddress('request_ip')->nullable();
            $table->decimal('execution_time_ms', 8, 2)->nullable();
            $table->timestamps();

            $table->index(['tool_name', 'created_at']);
            $table->index(['is_error', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_tool_run_logs');
    }
};
