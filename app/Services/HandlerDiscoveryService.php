<?php

namespace App\Services;

use App\Models\ToolHandler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HandlerDiscoveryService
{
    private const CACHE_KEY = 'tool_handlers_active';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get all active handlers
     */
    public function getActiveHandlers(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return ToolHandler::active()->ordered()->get();
        });
    }

    /**
     * Get handler options for dropdown/select components
     */
    public function getHandlerOptions(): array
    {
        return $this->getActiveHandlers()
            ->mapWithKeys(function (ToolHandler $handler) {
                return [$handler->full_class_name => $handler->display_name];
            })
            ->toArray();
    }

    /**
     * Get a specific handler by full class name
     */
    public function getHandler(string $fullClassName): ?ToolHandler
    {
        return $this->getActiveHandlers()
            ->firstWhere('full_class_name', $fullClassName);
    }

    /**
     * Get a handler instance by full class name
     */
    public function getHandlerInstance(string $fullClassName): ?object
    {
        $handler = $this->getHandler($fullClassName);
        
        if (!$handler) {
            return null;
        }

        try {
            return $handler->getInstance();
        } catch (\Exception $e) {
            Log::error("Failed to instantiate handler {$fullClassName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate that a handler exists and is valid
     */
    public function isValidHandler(string $fullClassName): bool
    {
        $handler = $this->getHandler($fullClassName);
        
        return $handler && $handler->isValidHandler();
    }

    /**
     * Clear the handlers cache
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Refresh the handlers cache
     */
    public function refreshCache(): Collection
    {
        $this->clearCache();
        return $this->getActiveHandlers();
    }

    /**
     * Discover and register built-in handlers
     */
    public function discoverBuiltInHandlers(): array
    {
        $builtInHandlers = [
            [
                'name' => 'calculator',
                'handler_type' => 'php',
                'display_name' => 'Calculator Tool',
                'description' => 'Performs basic arithmetic operations (add, subtract, multiply, divide)',
                'class_name' => 'CalculatorTool',
                'namespace' => 'App\\Tools',
                'file_path' => 'app/Tools/CalculatorTool.php',
                'version' => '1.0.0',
                'author' => 'System',
                'is_built_in' => true,
                'input_schema_template' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['add', 'subtract', 'multiply', 'divide'],
                            'description' => 'The arithmetic operation to perform'
                        ],
                        'a' => [
                            'type' => 'number',
                            'description' => 'First operand'
                        ],
                        'b' => [
                            'type' => 'number',
                            'description' => 'Second operand'
                        ]
                    ],
                    'required' => ['operation', 'a', 'b']
                ]
            ],
            [
                'name' => 'datetime',
                'handler_type' => 'php',
                'display_name' => 'DateTime Tool',
                'description' => 'Returns current date and time in various formats and timezones',
                'class_name' => 'DateTimeTool',
                'namespace' => 'App\\Tools',
                'file_path' => 'app/Tools/DateTimeTool.php',
                'version' => '1.0.0',
                'author' => 'System',
                'is_built_in' => true,
                'input_schema_template' => [
                    'type' => 'object',
                    'properties' => [
                        'timezone' => [
                            'type' => 'string',
                            'description' => 'IANA timezone identifier (defaults to UTC)',
                            'default' => 'UTC'
                        ],
                        'format' => [
                            'type' => 'string',
                            'description' => 'PHP date format string',
                            'default' => 'Y-m-d H:i:s'
                        ]
                    ]
                ]
            ],
            [
                'name' => 'echo',
                'handler_type' => 'php',
                'display_name' => 'Echo Tool',
                'description' => 'Echoes back the provided message, useful for testing',
                'class_name' => 'EchoTool',
                'namespace' => 'App\\Tools',
                'file_path' => 'app/Tools/EchoTool.php',
                'version' => '1.0.0',
                'author' => 'System',
                'is_built_in' => true,
                'input_schema_template' => [
                    'type' => 'object',
                    'properties' => [
                        'message' => [
                            'type' => 'string',
                            'description' => 'Message to echo back'
                        ],
                        'delay' => [
                            'type' => 'number',
                            'description' => 'Optional delay in seconds (max 5)',
                            'minimum' => 0,
                            'maximum' => 5
                        ]
                    ],
                    'required' => ['message']
                ]
            ],
            // Example bash handler
            [
                'name' => 'system_info',
                'handler_type' => 'bash',
                'display_name' => 'System Info Tool',
                'description' => 'Returns basic system information using bash commands',
                'version' => '1.0.0',
                'author' => 'System',
                'is_built_in' => true,
                'class_name' => null,
                'namespace' => null,
                'file_path' => null,
                'timeout_seconds' => 10,
                'script_content' => '#!/bin/bash
set -e

# Get system information
hostname=$(hostname)
uptime_info=$(uptime)
disk_usage=$(df -h / | tail -1)
memory_info=$(free -h | grep Mem)

# Output as JSON
echo "{"
echo "  \"hostname\": \"$hostname\","
echo "  \"uptime\": \"$uptime_info\","
echo "  \"disk_usage\": \"$disk_usage\","
echo "  \"memory_info\": \"$memory_info\","
echo "  \"timestamp\": \"$(date -Iseconds)\""
echo "}"',
                'input_schema_template' => [
                    'type' => 'object',
                    'properties' => [],
                    'required' => []
                ]
            ]
        ];

        $registered = [];
        
        foreach ($builtInHandlers as $handlerData) {
            $existing = ToolHandler::where('name', $handlerData['name'])->first();
            
            if (!$existing) {
                $handler = ToolHandler::create($handlerData);
                $registered[] = $handler;
                Log::info("Registered built-in handler: {$handler->display_name}");
            }
        }

        if (!empty($registered)) {
            $this->clearCache();
        }

        return $registered;
    }

    /**
     * Get handler usage statistics
     */
    public function getHandlerStats(): array
    {
        $handlers = ToolHandler::all();
        
        return [
            'total' => $handlers->count(),
            'active' => $handlers->where('is_active', true)->count(),
            'built_in' => $handlers->where('is_built_in', true)->count(),
            'custom' => $handlers->where('is_built_in', false)->count(),
            'in_use' => $handlers->filter(function ($handler) {
                return $handler->usage_count > 0;
            })->count(),
        ];
    }

    /**
     * Validate all registered handlers
     */
    public function validateAllHandlers(): array
    {
        $results = [];
        
        foreach (ToolHandler::all() as $handler) {
            $results[$handler->name] = [
                'handler' => $handler,
                'valid' => $handler->isValidHandler(),
                'error' => null
            ];
            
            if (!$results[$handler->name]['valid']) {
                try {
                    $handler->getInstance();
                } catch (\Exception $e) {
                    $results[$handler->name]['error'] = $e->getMessage();
                }
            }
        }
        
        return $results;
    }
}