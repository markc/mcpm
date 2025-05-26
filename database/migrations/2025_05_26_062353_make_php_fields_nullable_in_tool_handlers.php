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
            $table->string('class_name')->nullable()->change();
            $table->string('namespace')->nullable()->change();
            $table->string('file_path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tool_handlers', function (Blueprint $table) {
            $table->string('class_name')->nullable(false)->change();
            $table->string('namespace')->nullable(false)->change();
            $table->string('file_path')->nullable(false)->change();
        });
    }
};
