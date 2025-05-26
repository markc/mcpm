<?php

namespace App\Services;

use App\Contracts\ToolInterface;
use App\Models\Tool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ToolRegistry
{
    private Collection $tools;

    private array $instances = [];

    public function __construct()
    {
        $this->loadTools();
    }

    /**
     * Load all active tools from the database
     */
    private function loadTools(): void
    {
        $this->tools = Cache::remember('mcp_tools', 300, function () {
            return Tool::active()->ordered()->get();
        });
    }

    /**
     * Clear the tools cache and reload
     */
    public function clearCache(): void
    {
        Cache::forget('mcp_tools');
        $this->instances = [];
        $this->loadTools();
    }

    /**
     * Get all available tools
     */
    public function getAllTools(): Collection
    {
        return $this->tools;
    }

    /**
     * Get a specific tool by name
     */
    public function getTool(string $name): ?Tool
    {
        return $this->tools->firstWhere('name', $name);
    }

    /**
     * Get tool instance (with caching)
     */
    public function getToolInstance(string $name): ?ToolInterface
    {
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $tool = $this->getTool($name);
        if (! $tool) {
            return null;
        }

        try {
            $instance = $tool->getInstance();

            if (! $instance instanceof ToolInterface) {
                throw new \InvalidArgumentException(
                    "Tool handler {$tool->handler_class} must implement ToolInterface"
                );
            }

            $this->instances[$name] = $instance;

            return $instance;
        } catch (\Exception $e) {
            \Log::error("Failed to instantiate tool {$name}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Execute a tool with given input
     */
    public function executeTool(string $name, array $input): array
    {
        $toolInstance = $this->getToolInstance($name);

        if (! $toolInstance) {
            throw new \InvalidArgumentException("Tool '{$name}' is not available");
        }

        // Validate input
        $toolInstance->validateInput($input);

        // Execute the tool
        return $toolInstance->execute($input);
    }

    /**
     * Check if a tool exists and is available
     */
    public function hasToolWithGetter(string $name): bool
    {
        return $this->getTool($name) !== null;
    }

    /**
     * Get tools formatted for MCP discovery endpoint
     */
    public function getToolsForDiscovery(): array
    {
        $tools = [];

        foreach ($this->tools as $tool) {
            $tools[$tool->name] = [
                'name' => $tool->name,
                'description' => $tool->description,
                'input_schema' => $tool->input_schema,
            ];
        }

        return $tools;
    }

    /**
     * Refresh the tool cache
     */
    public function refresh(): void
    {
        Cache::forget('mcp_tools');
        $this->instances = [];
        $this->loadTools();
    }

    /**
     * Get tool statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_tools' => $this->tools->count(),
            'active_tools' => $this->tools->where('is_active', true)->count(),
            'inactive_tools' => $this->tools->where('is_active', false)->count(),
        ];
    }
}
