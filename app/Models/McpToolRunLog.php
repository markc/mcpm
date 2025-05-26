<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class McpToolRunLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tool_name',
        'tool_input',
        'tool_output',
        'is_error',
        'error_type',
        'error_message',
        'raw_request_payload',
        'request_ip',
        'execution_time_ms',
    ];

    protected $casts = [
        'tool_input' => 'array',
        'tool_output' => 'array',
        'raw_request_payload' => 'array',
        'is_error' => 'boolean',
        'execution_time_ms' => 'decimal:2',
    ];

    public function scopeErrors($query)
    {
        return $query->where('is_error', true);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('is_error', false);
    }

    public function scopeForTool($query, string $toolName)
    {
        return $query->where('tool_name', $toolName);
    }

    /**
     * Get formatted tool input for display
     */
    public function getFormattedToolInputAttribute(): string
    {
        return $this->tool_input ? json_encode($this->tool_input, JSON_PRETTY_PRINT) : 'N/A';
    }

    /**
     * Get formatted tool output for display
     */
    public function getFormattedToolOutputAttribute(): string
    {
        return $this->tool_output ? json_encode($this->tool_output, JSON_PRETTY_PRINT) : 'N/A';
    }

    /**
     * Get formatted raw request payload for display
     */
    public function getFormattedRawRequestPayloadAttribute(): string
    {
        return $this->raw_request_payload ? json_encode($this->raw_request_payload, JSON_PRETTY_PRINT) : 'N/A';
    }
}
