<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ToolHandler extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'handler_type',
        'display_name',
        'description',
        'version',
        'class_name',
        'namespace',
        'file_path',
        'script_content',
        'environment_variables',
        'timeout_seconds',
        'is_active',
        'is_built_in',
        'author',
        'dependencies',
        'input_schema_template',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_built_in' => 'boolean',
        'dependencies' => 'array',
        'environment_variables' => 'array',
        'input_schema_template' => 'array',
        'sort_order' => 'integer',
        'timeout_seconds' => 'integer',
    ];

    /**
     * Get tools that use this handler
     */
    public function tools(): HasMany
    {
        // Since full_class_name is computed, we'll create a basic relationship
        // and override the usage count method
        return $this->hasMany(Tool::class, 'handler_class', 'namespace');
    }

    /**
     * Scope to get only active handlers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get handlers ordered by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('display_name');
    }

    /**
     * Scope to get only built-in handlers
     */
    public function scopeBuiltIn($query)
    {
        return $query->where('is_built_in', true);
    }

    /**
     * Scope to get only custom handlers
     */
    public function scopeCustom($query)
    {
        return $query->where('is_built_in', false);
    }

    /**
     * Get the full class name (namespace + class_name) for PHP handlers
     */
    public function getFullClassNameAttribute(): string
    {
        if ($this->handler_type === 'bash') {
            return 'bash:' . $this->name; // Special identifier for bash handlers
        }
        
        return $this->namespace . '\\' . $this->class_name;
    }

    /**
     * Check if the handler is valid (PHP class exists or bash script is valid)
     */
    public function isValidHandler(): bool
    {
        if ($this->handler_type === 'bash') {
            // For bash handlers, check if script content exists and is not empty
            return !empty($this->script_content);
        }
        
        // For PHP handlers, check class exists and implements interface
        $fullClassName = $this->full_class_name;
        
        if (!class_exists($fullClassName)) {
            return false;
        }

        $interfaces = class_implements($fullClassName);
        return in_array('App\\Contracts\\ToolInterface', $interfaces ?: []);
    }

    /**
     * Get an instance of the handler (for validation/testing purposes)
     */
    public function getInstance(): object
    {
        if (!$this->isValidHandler()) {
            throw new \InvalidArgumentException("Handler is not valid");
        }

        if ($this->handler_type === 'bash') {
            // For bash handlers, we can't create an instance without a tool
            // This method is mainly for validation, so we'll return a mock
            return new class($this) implements \App\Contracts\BashToolInterface {
                private $handler;
                public function __construct($handler) { $this->handler = $handler; }
                public function getName(): string { return $this->handler->name; }
                public function getDescription(): string { return $this->handler->description; }
                public function execute(array $input): array { throw new \BadMethodCallException('Use Tool->getInstance() for execution'); }
                public function validateInput(array $input): bool { return true; }
                public function getScriptContent(): string { return $this->handler->script_content ?? ''; }
                public function getEnvironmentVariables(): array { return $this->handler->environment_variables ?? []; }
                public function getTimeoutSeconds(): int { return $this->handler->timeout_seconds ?? 30; }
            };
        }

        // For PHP handlers, instantiate the class
        $fullClassName = $this->full_class_name;
        return new $fullClassName();
    }

    /**
     * Get usage count (number of tools using this handler)
     */
    public function getUsageCountAttribute(): int
    {
        return Tool::where('handler_class', $this->full_class_name)->count();
    }
}
