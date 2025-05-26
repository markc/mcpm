<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tool extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'input_schema',
        'handler_class',
        'is_active',
        'settings',
        'sort_order',
    ];

    protected $casts = [
        'input_schema' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get tool run logs for this tool
     */
    public function runLogs(): HasMany
    {
        return $this->hasMany(McpToolRunLog::class, 'tool_name', 'name');
    }

    /**
     * Scope to get only active tools
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get tools ordered by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('display_name');
    }

    /**
     * Get the tool instance
     */
    public function getInstance(): object
    {
        $handlerClass = $this->handler_class;

        // Handle bash tools
        if (str_starts_with($handlerClass, 'bash:')) {
            return new \App\Services\BashToolExecutor($this);
        }

        // Handle PHP tools
        if (! class_exists($handlerClass)) {
            throw new \InvalidArgumentException("Tool handler class {$handlerClass} does not exist");
        }

        return new $handlerClass($this);
    }

    /**
     * Get formatted input schema for display
     */
    public function getFormattedInputSchemaAttribute(): string
    {
        return $this->input_schema ? json_encode($this->input_schema, JSON_PRETTY_PRINT) : 'N/A';
    }

    /**
     * Get tool usage statistics
     */
    public function getUsageStats(): array
    {
        $totalRuns = $this->runLogs()->count();
        $successfulRuns = $this->runLogs()->where('is_error', false)->count();
        $errorRuns = $this->runLogs()->where('is_error', true)->count();
        $avgExecutionTime = $this->runLogs()->avg('execution_time_ms');

        return [
            'total_runs' => $totalRuns,
            'successful_runs' => $successfulRuns,
            'error_runs' => $errorRuns,
            'success_rate' => $totalRuns > 0 ? round(($successfulRuns / $totalRuns) * 100, 2) : 0,
            'avg_execution_time' => $avgExecutionTime ? round($avgExecutionTime, 2) : 0,
        ];
    }
}
