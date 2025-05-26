# MCP Tool Server

A Laravel + Filament application implementing Anthropic's Model Context Protocol (MCP) for dynamic tool execution by AI models.

## What It Does

This server allows AI models to discover and execute tools through a REST API. Tools can be PHP classes or Bash scripts, managed through a web admin interface.

## Core Features

- **MCP Protocol**: REST API for tool discovery and execution
- **Dual Handlers**: Support for PHP classes and Bash scripts
- **Admin Interface**: Filament-based tool management
- **Usage Logging**: Track tool execution and performance
- **Security**: Input validation, timeouts, sandboxed execution

## API Endpoints

```
GET  /api/mcp/tools           # Discover available tools
POST /api/mcp/run_tool        # Execute a tool
```

## Quick Start

```bash
# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Database setup
touch database/database.sqlite
php artisan migrate:fresh --seed

# Start development
composer run dev
```

## Usage Example

**Execute Calculator Tool:**
```bash
curl -X POST http://localhost:8000/api/mcp/run_tool \
  -H "Content-Type: application/json" \
  -d '{
    "tool_name": "calculator",
    "tool_input": {"operation": "add", "a": 5, "b": 3}
  }'
```

**Response:**
```json
{
  "content": [{"type": "text", "text": "8"}]
}
```

## Admin Interface

Access the admin panel at `/admin` to:
- Create and manage tools
- Test tools with dynamic forms
- Monitor usage statistics
- Manage PHP and Bash handlers

## Built-in Tools

- **Calculator**: Basic math operations
- **DateTime**: Current time with timezone support  
- **Echo**: Simple text echo for testing

## Development Commands

- `composer run dev` - Full development environment
- `composer test` - Run test suite
- `./vendor/bin/pint` - Format PHP code
- `npm run build` - Build frontend assets

## Technology Stack

- **Backend**: Laravel 12 (PHP 8.4+)
- **Admin Panel**: Filament 3.3
- **Database**: SQLite
- **Testing**: Pest PHP
- **Frontend**: Vite + TailwindCSS