# MCP Tool Server with Dynamic Handler Management

A comprehensive Laravel + Filament application implementing Anthropic's Model Context Protocol (MCP) with dynamic tool handler management supporting both PHP classes and Bash scripts.

## 🎯 Project Overview

This project implements a complete MCP Tool Server that allows AI models to execute tools dynamically through a REST API. It features a sophisticated admin interface for managing tool handlers, creating tools, and monitoring usage with support for both PHP-based and Bash script-based tool implementations.

### Core Features

- **MCP Protocol Compliance**: Full REST API implementation for tool discovery and execution
- **Dynamic Tool Management**: Database-driven tool registry with caching
- **Dual Handler Architecture**: Support for both PHP classes and Bash scripts
- **Admin Interface**: Comprehensive Filament-based management panel
- **Usage Analytics**: Tool execution logging and statistics
- **Security Features**: Input validation, timeout protection, sandboxed execution
- **Testing Interface**: Built-in tool testing with dynamic form generation

### Technology Stack

- **Backend**: Laravel 12 (PHP 8.4+)
- **Admin Panel**: Filament 3.3
- **Database**: SQLite (configurable)
- **Testing**: Pest PHP
- **Process Management**: Laravel Process (for Bash execution)
- **Caching**: Redis/Database cache

## 🏗️ Architecture Overview

### MCP Protocol Implementation

The server implements Anthropic's Model Context Protocol specification:

```
GET  /api/mcp/tools           # Tool discovery endpoint
POST /api/mcp/run_tool        # Tool execution endpoint
```

### Handler Architecture

#### PHP Handlers
- Implement `App\Contracts\ToolInterface`
- Extend `App\Tools\BaseTool` for common functionality
- Stored as classes in `app/Tools/` directory

#### Bash Handlers
- Stored as script content in database
- Executed via `App\Services\BashToolExecutor`
- Input parameters injected as environment variables
- JSON output parsing for structured results

### Database Schema

```sql
-- Core tool definitions
CREATE TABLE tools (
    id INTEGER PRIMARY KEY,
    name VARCHAR UNIQUE,           -- Tool identifier
    display_name VARCHAR,          -- Human-readable name
    description TEXT,              -- Tool description
    input_schema JSON,             -- JSON Schema for validation
    handler_class VARCHAR,         -- Handler reference
    settings JSON,                 -- Additional configuration
    is_active BOOLEAN DEFAULT 1,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Dynamic handler registry
CREATE TABLE tool_handlers (
    id INTEGER PRIMARY KEY,
    name VARCHAR UNIQUE,           -- Handler identifier
    handler_type ENUM('php','bash'), -- Handler type
    display_name VARCHAR,          -- Human-readable name
    description TEXT,              -- Handler description
    version VARCHAR DEFAULT '1.0.0',
    
    -- PHP Handler fields
    class_name VARCHAR NULL,       -- PHP class name
    namespace VARCHAR NULL,        -- PHP namespace
    file_path VARCHAR NULL,        -- File path
    
    -- Bash Handler fields
    script_content TEXT NULL,      -- Bash script
    environment_variables JSON NULL, -- Environment variables
    timeout_seconds INTEGER DEFAULT 30, -- Execution timeout
    
    -- Metadata
    author VARCHAR NULL,
    dependencies JSON NULL,        -- Required packages
    input_schema_template JSON NULL, -- Default schema
    is_active BOOLEAN DEFAULT 1,
    is_built_in BOOLEAN DEFAULT 0, -- Prevents deletion
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Execution logging
CREATE TABLE mcp_tool_run_logs (
    id INTEGER PRIMARY KEY,
    tool_name VARCHAR,             -- Executed tool
    tool_input JSON,               -- Input parameters
    tool_output JSON NULL,         -- Execution result
    is_error BOOLEAN DEFAULT 0,
    error_type VARCHAR NULL,
    error_message TEXT NULL,
    raw_request_payload JSON NULL,
    request_ip VARCHAR NULL,
    execution_time_ms FLOAT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## 🚀 Recreating from Scratch

### Prerequisites

- PHP 8.4+
- Composer
- Node.js & NPM
- SQLite/MySQL/PostgreSQL

### Step 1: Fresh Laravel + Filament Installation

```bash
# Create new Laravel project
composer create-project laravel/laravel mcp-tool-server
cd mcp-tool-server

# Install Filament
composer require filament/filament:"^3.2"
php artisan filament:install --panels

# Install additional dependencies
composer require laravel/tinker
composer require pestphp/pest --dev
php artisan pest:install

# Configure environment
cp .env.example .env
php artisan key:generate
```

### Step 2: Database Configuration

Update `.env`:
```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite
```

Create database:
```bash
touch database/database.sqlite
```

### Step 3: Core Contracts and Interfaces

Create the base tool interface:

```php
# app/Contracts/ToolInterface.php
<?php

namespace App\Contracts;

use App\Models\Tool;

interface ToolInterface
{
    public function __construct(Tool $tool);
    public function execute(array $input): array;
    public function validateInput(array $input): bool;
    public function getName(): string;
    public function getDescription(): string;
    public function getInputSchema(): array;
    public function getSettings(): array;
}
```

Create bash tool interface:

```php
# app/Contracts/BashToolInterface.php
<?php

namespace App\Contracts;

interface BashToolInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function execute(array $input): array;
    public function validateInput(array $input): bool;
    public function getScriptContent(): string;
    public function getEnvironmentVariables(): array;
    public function getTimeoutSeconds(): int;
}
```

### Step 4: Base Tool Implementation

Create the base tool class:

```php
# app/Tools/BaseTool.php
<?php

namespace App\Tools;

use App\Contracts\ToolInterface;
use App\Models\Tool;

abstract class BaseTool implements ToolInterface
{
    protected Tool $tool;

    public function __construct(Tool $tool)
    {
        $this->tool = $tool;
    }

    public function getName(): string
    {
        return $this->tool->name;
    }

    public function getDescription(): string
    {
        return $this->tool->description;
    }

    public function getInputSchema(): array
    {
        return $this->tool->input_schema ?? [];
    }

    public function getSettings(): array
    {
        return $this->tool->settings ?? [];
    }

    public function validateInput(array $input): bool
    {
        $schema = $this->getInputSchema();
        
        if (!isset($schema['properties'])) {
            return true;
        }

        $required = $schema['required'] ?? [];
        
        foreach ($required as $field) {
            if (!array_key_exists($field, $input)) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        return true;
    }

    // Helper validation methods
    protected function validateString(array $input, string $field): string
    {
        if (!isset($input[$field])) {
            throw new \InvalidArgumentException("Missing required field: {$field}");
        }

        if (!is_string($input[$field])) {
            throw new \InvalidArgumentException("Field '{$field}' must be a string");
        }

        return $input[$field];
    }

    protected function validateNumber(array $input, string $field): float
    {
        if (!isset($input[$field])) {
            throw new \InvalidArgumentException("Missing required field: {$field}");
        }

        if (!is_numeric($input[$field])) {
            throw new \InvalidArgumentException("Field '{$field}' must be a number");
        }

        return (float) $input[$field];
    }

    protected function validateEnum(array $input, string $field, array $allowedValues): string
    {
        $value = $this->validateString($input, $field);
        
        if (!in_array($value, $allowedValues)) {
            $allowed = implode(', ', $allowedValues);
            throw new \InvalidArgumentException("Field '{$field}' must be one of: {$allowed}");
        }

        return $value;
    }
}
```

### Step 5: Database Models and Migrations

Create Tool model:

```bash
php artisan make:model Tool -m
```

```php
# app/Models/Tool.php
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

    public function runLogs(): HasMany
    {
        return $this->hasMany(McpToolRunLog::class, 'tool_name', 'name');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('display_name');
    }

    public function getInstance(): object
    {
        $handlerClass = $this->handler_class;

        // Handle bash tools
        if (str_starts_with($handlerClass, 'bash:')) {
            return new \App\Services\BashToolExecutor($this);
        }

        // Handle PHP tools
        if (!class_exists($handlerClass)) {
            throw new \InvalidArgumentException("Tool handler class {$handlerClass} does not exist");
        }

        return new $handlerClass($this);
    }

    public function getFormattedInputSchemaAttribute(): string
    {
        return $this->input_schema ? json_encode($this->input_schema, JSON_PRETTY_PRINT) : 'N/A';
    }

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
```

Create detailed migrations and models for ToolHandler and McpToolRunLog following the database schema above.

### Step 6: Core Services

Create ToolRegistry service, HandlerDiscoveryService, and BashToolExecutor with full implementations as shown in the project.

### Step 7: Example Tool Implementations

Create Calculator, DateTime, and Echo tools with complete functionality.

### Step 8: MCP API Controller

Create the main controller with comprehensive error handling and logging.

### Step 9: Filament Admin Interface

Create complete Filament resources for Tool and ToolHandler management with dynamic forms, testing interfaces, and comprehensive CRUD operations.

### Step 10: Service Registration

Register all services in AppServiceProvider with proper dependency injection.

### Step 11: Seeders and Initial Data

Create seeders to populate built-in handlers and example tools.

### Step 12: Testing Setup

Create comprehensive test suite covering all functionality.

### Step 13: Final Configuration

Configure Filament admin panel with collapsible sidebar and proper theming.

## 🎯 Usage Examples

### API Endpoints

**Tool Discovery:**
```bash
curl http://localhost:8000/api/mcp/tools
```

**Execute Calculator:**
```bash
curl -X POST http://localhost:8000/api/mcp/run_tool \
  -H "Content-Type: application/json" \
  -d '{"tool_name": "calculator", "tool_input": {"operation": "add", "a": 5, "b": 3}}'
```

**Execute Bash Script:**
```bash
curl -X POST http://localhost:8000/api/mcp/run_tool \
  -H "Content-Type: application/json" \
  -d '{"tool_name": "system_status", "tool_input": {}}'
```

### Admin Interface Features

1. **Tool Management**: Create, edit, test tools with dynamic forms
2. **Handler Management**: Manage PHP classes and Bash scripts
3. **Usage Analytics**: Monitor tool execution and performance
4. **Real-time Testing**: Test tools directly in admin interface
5. **Schema Validation**: Built-in JSON schema validation
6. **Logging**: Comprehensive execution logging

## 🔧 Advanced Configuration

### Environment Variables for Bash Tools

Bash scripts receive input parameters as environment variables:
- `$MCP_TOOL_NAME` - Tool name
- `$MCP_TOOL_TYPE` - Always "bash"
- `$MCP_INPUT_*` - Input parameters (e.g., `$MCP_INPUT_MESSAGE`)

### Custom PHP Tool Handler

```php
<?php

namespace App\Tools;

class CustomTool extends BaseTool
{
    public function execute(array $input): array
    {
        // Your custom logic here
        $parameter = $this->validateString($input, 'parameter');
        
        return [
            'result' => 'Custom result: ' . $parameter,
            'processed_at' => now()->toISOString()
        ];
    }
}
```

### Custom Bash Script Example

```bash
#!/bin/bash
set -e

# Access input parameters
echo "Processing: $MCP_INPUT_MESSAGE"

# Your bash logic here
result=$(echo "$MCP_INPUT_MESSAGE" | tr '[:lower:]' '[:upper:]')

# Output JSON
echo "{\"result\": \"$result\", \"processed_at\": \"$(date -Iseconds)\"}"
```

## 🚀 Performance Optimization

- **Caching**: Tool and handler registry caching (300s TTL)
- **Database Indexes**: Optimized queries for active tools/handlers
- **Lazy Loading**: Deferred service instantiation
- **Connection Pooling**: Database connection optimization
- **Process Isolation**: Sandboxed bash execution

## 🔒 Security Features

- **Input Validation**: JSON Schema validation for all inputs
- **Timeout Protection**: Configurable execution timeouts
- **Sandboxed Execution**: Isolated bash script execution
- **Audit Logging**: Comprehensive execution logging
- **IP Tracking**: Request origin tracking
- **Error Handling**: Secure error message sanitization

## 📚 Additional Resources

- **Laravel Documentation**: https://laravel.com/docs
- **Filament Documentation**: https://filamentphp.com/docs
- **MCP Specification**: https://spec.modelcontextprotocol.io/specification/
- **JSON Schema**: https://json-schema.org/

## Development Commands

### Running the Application
- `composer run dev` - Starts the full development environment with Laravel server, queue worker, logs, and Vite frontend build
- `php artisan serve` - Start Laravel development server only
- `npm run dev` - Start Vite frontend development server only

### Testing
- `composer test` - Run all tests (clears config first)
- `php artisan test` - Run tests directly with Artisan
- `./vendor/bin/pest` - Run Pest tests directly
- `./vendor/bin/pest --filter=TestName` - Run specific test

### Code Quality
- `./vendor/bin/pint` - Format PHP code using Laravel Pint (PSR-12 style)

### Building
- `npm run build` - Build frontend assets for production

### Database
- `php artisan migrate` - Run database migrations
- `php artisan migrate:fresh --seed` - Fresh migration with seeding
- `php artisan db:seed --class=ToolHandlerSeeder` - Seed built-in handlers

## 🧪 Testing

Run the complete test suite:

```bash
php artisan test
```

Key test scenarios:
- PHP tool execution (Calculator, DateTime, Echo)
- Bash tool execution (System Info)
- Input validation and error handling
- Tool discovery API
- Admin interface functionality

The project includes comprehensive test coverage ensuring reliability and maintainability.

This implementation provides a production-ready MCP Tool Server with comprehensive tool management capabilities, supporting both PHP and Bash-based tool handlers through a sophisticated admin interface.