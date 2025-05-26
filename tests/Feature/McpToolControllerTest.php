<?php

use App\Models\McpToolRunLog;
use App\Models\Tool;

beforeEach(function () {
    // Create test tools
    Tool::create([
        'name' => 'calculator',
        'display_name' => 'Calculator',
        'description' => 'Perform basic arithmetic operations',
        'handler_class' => 'App\\Tools\\CalculatorTool',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'enum' => ['add', 'subtract', 'multiply', 'divide'],
                    'description' => 'The arithmetic operation to perform',
                ],
                'a' => ['type' => 'number', 'description' => 'First operand'],
                'b' => ['type' => 'number', 'description' => 'Second operand'],
            ],
            'required' => ['operation', 'a', 'b'],
        ],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Tool::create([
        'name' => 'get_current_datetime',
        'display_name' => 'Get Current DateTime',
        'description' => 'Get the current date and time',
        'handler_class' => 'App\\Tools\\DateTimeTool',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'timezone' => [
                    'type' => 'string',
                    'description' => 'IANA timezone identifier',
                    'default' => 'UTC',
                ],
            ],
        ],
        'is_active' => true,
        'sort_order' => 2,
    ]);

    Tool::create([
        'name' => 'echo',
        'display_name' => 'Echo',
        'description' => 'Echo back the input message',
        'handler_class' => 'App\\Tools\\EchoTool',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'description' => 'Message to echo back'],
                'delay' => ['type' => 'number', 'description' => 'Optional delay'],
            ],
            'required' => ['message'],
        ],
        'is_active' => true,
        'sort_order' => 3,
    ]);
});

test('calculator tool addition works correctly', function () {
    $response = $this->postJson('/api/mcp/run_tool', [
        'tool_name' => 'calculator',
        'tool_input' => [
            'operation' => 'add',
            'a' => 5,
            'b' => 3,
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'tool_output' => ['result' => 8],
            'is_error' => false,
        ]);

    expect(McpToolRunLog::where('tool_name', 'calculator')->count())->toBe(1);
});

test('calculator tool handles division by zero', function () {
    $response = $this->postJson('/api/mcp/run_tool', [
        'tool_name' => 'calculator',
        'tool_input' => [
            'operation' => 'divide',
            'a' => 10,
            'b' => 0,
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'tool_output' => null,
            'is_error' => true,
            'error_type' => 'invalid_tool_input',
            'error_message' => 'Cannot divide by zero.',
        ]);
});

test('calculator tool rejects invalid operations', function () {
    $response = $this->postJson('/api/mcp/run_tool', [
        'tool_name' => 'calculator',
        'tool_input' => [
            'operation' => 'power',
            'a' => 2,
            'b' => 3,
        ],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'tool_output' => null,
            'is_error' => true,
            'error_type' => 'invalid_tool_input',
        ]);
});

test('datetime tool returns UTC by default', function () {
    $response = $this->postJson('/api/mcp/run_tool', [
        'tool_name' => 'get_current_datetime',
        'tool_input' => [],
    ]);

    $response->assertStatus(200)
        ->assertJson(['is_error' => false])
        ->assertJsonStructure([
            'tool_output' => ['datetime_iso8601', 'timezone'],
        ]);

    $data = $response->json();
    expect($data['tool_output']['timezone'])->toBe('UTC');
});

test('datetime tool works with specific timezone', function () {
    $response = $this->postJson('/api/mcp/run_tool', [
        'tool_name' => 'get_current_datetime',
        'tool_input' => ['timezone' => 'America/New_York'],
    ]);

    $response->assertStatus(200)
        ->assertJson(['is_error' => false]);

    $data = $response->json();
    expect($data['tool_output']['timezone'])->toBe('America/New_York');
});

test('datetime tool handles invalid timezone', function () {
    $response = $this->postJson('/api/mcp/run_tool', [
        'tool_name' => 'get_current_datetime',
        'tool_input' => ['timezone' => 'Invalid/Timezone'],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'tool_output' => null,
            'is_error' => true,
            'error_type' => 'invalid_tool_input',
        ]);
});

test('echo tool works correctly', function () {
    $message = 'Hello MCP World!';

    $response = $this->postJson('/api/mcp/run_tool', [
        'tool_name' => 'echo',
        'tool_input' => ['message' => $message],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'is_error' => false,
        ])
        ->assertJsonStructure([
            'tool_output' => ['echoed_message', 'timestamp', 'length'],
        ]);

    $data = $response->json();
    expect($data['tool_output']['echoed_message'])->toBe($message);
    expect($data['tool_output']['length'])->toBe(strlen($message));
});

test('echo tool requires message parameter', function () {
    $response = $this->postJson('/api/mcp/run_tool', [
        'tool_name' => 'echo',
        'tool_input' => [],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'tool_output' => null,
            'is_error' => true,
            'error_type' => 'invalid_tool_input',
        ]);
});

test('unknown tool returns appropriate error', function () {
    $response = $this->postJson('/api/mcp/run_tool', [
        'tool_name' => 'nonexistent_tool',
        'tool_input' => [],
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'tool_output' => null,
            'is_error' => true,
            'error_type' => 'unknown_tool',
            'error_message' => "Tool 'nonexistent_tool' is not recognized by this server.",
        ]);
});

test('invalid request format returns 400 error', function () {
    $response = $this->postJson('/api/mcp/run_tool', [
        'invalid_field' => 'value',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'tool_output' => null,
            'is_error' => true,
            'error_type' => 'invalid_request_format',
        ]);
});

test('tools list endpoint works', function () {
    $response = $this->getJson('/api/mcp/tools');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'tools' => [
                'calculator' => ['name', 'description', 'input_schema'],
                'get_current_datetime' => ['name', 'description', 'input_schema'],
                'echo' => ['name', 'description', 'input_schema'],
            ],
        ]);
});

test('tool execution creates log entry', function () {
    expect(McpToolRunLog::count())->toBe(0);

    $this->postJson('/api/mcp/run_tool', [
        'tool_name' => 'echo',
        'tool_input' => ['message' => 'test'],
    ]);

    expect(McpToolRunLog::count())->toBe(1);

    $log = McpToolRunLog::first();
    expect($log->tool_name)->toBe('echo');
    expect($log->tool_input)->toBe(['message' => 'test']);
    expect($log->tool_output['echoed_message'])->toBe('test');
    expect($log->tool_output['length'])->toBe(4);
    expect($log->is_error)->toBeFalse();
    expect($log->error_type)->toBeNull();
    expect($log->error_message)->toBeNull();
    expect($log->execution_time_ms)->toBeGreaterThan(0);
});

test('tool error creates log entry', function () {
    $this->postJson('/api/mcp/run_tool', [
        'tool_name' => 'calculator',
        'tool_input' => ['operation' => 'divide', 'a' => 1, 'b' => 0],
    ]);

    $log = McpToolRunLog::first();
    expect($log->tool_name)->toBe('calculator');
    expect($log->is_error)->toBeTrue();
    expect($log->error_type)->toBe('invalid_tool_input');
    expect($log->error_message)->toBe('Cannot divide by zero.');
    expect($log->tool_output)->toBeNull();
});
