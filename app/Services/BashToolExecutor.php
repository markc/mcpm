<?php

namespace App\Services;

use App\Contracts\BashToolInterface;
use App\Contracts\ToolInterface;
use App\Models\Tool;
use App\Models\ToolHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class BashToolExecutor implements BashToolInterface, ToolInterface
{
    private ToolHandler $handler;

    private Tool $tool;

    public function __construct(Tool $tool)
    {
        $this->tool = $tool;

        // Extract handler name from handler_class (format: bash:handler_name)
        if (! str_starts_with($tool->handler_class, 'bash:')) {
            throw new \InvalidArgumentException('Tool must use a bash handler');
        }

        $handlerName = substr($tool->handler_class, 5);
        $this->handler = ToolHandler::where('name', $handlerName)
            ->where('handler_type', 'bash')
            ->firstOrFail();
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

    public function getScriptContent(): string
    {
        return $this->handler->script_content ?? '';
    }

    public function getEnvironmentVariables(): array
    {
        return $this->handler->environment_variables ?? [];
    }

    public function getTimeoutSeconds(): int
    {
        return $this->handler->timeout_seconds ?? 30;
    }

    public function validateInput(array $input): bool
    {
        // Basic validation - can be enhanced based on input schema
        if (! is_array($input)) {
            return false;
        }

        // If there's an input schema template, validate against it
        if ($this->handler->input_schema_template) {
            return $this->validateAgainstSchema($input, $this->handler->input_schema_template);
        }

        return true;
    }

    public function execute(array $input): array
    {
        if (! $this->validateInput($input)) {
            throw new \InvalidArgumentException('Invalid input parameters');
        }

        $scriptContent = $this->getScriptContent();
        if (empty($scriptContent)) {
            throw new \InvalidArgumentException('No script content available');
        }

        // Create a temporary script file
        $tempScriptPath = $this->createTempScript($scriptContent);

        try {
            // Prepare environment variables
            $envVars = $this->prepareEnvironmentVariables($input);

            // Execute the script
            $result = $this->executeScript($tempScriptPath, $envVars);

            return $this->parseScriptOutput($result);

        } finally {
            // Clean up temp file
            if (file_exists($tempScriptPath)) {
                unlink($tempScriptPath);
            }
        }
    }

    private function validateAgainstSchema(array $input, array $schema): bool
    {
        // Basic schema validation
        if (! isset($schema['properties'])) {
            return true;
        }

        $required = $schema['required'] ?? [];

        // Check required fields
        foreach ($required as $field) {
            if (! array_key_exists($field, $input)) {
                return false;
            }
        }

        // Check field types
        foreach ($schema['properties'] as $field => $fieldSchema) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];
            $expectedType = $fieldSchema['type'] ?? 'string';

            if (! $this->validateFieldType($value, $expectedType)) {
                return false;
            }
        }

        return true;
    }

    private function validateFieldType($value, string $expectedType): bool
    {
        return match ($expectedType) {
            'string' => is_string($value),
            'number' => is_numeric($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_array($value) || is_object($value),
            default => true
        };
    }

    private function createTempScript(string $scriptContent): string
    {
        $tempDir = sys_get_temp_dir();
        $tempFile = tempnam($tempDir, 'mcp_bash_tool_'.$this->handler->name.'_');

        // Add bash shebang if not present
        if (! str_starts_with($scriptContent, '#!')) {
            $scriptContent = "#!/bin/bash\nset -e\n".$scriptContent;
        }

        file_put_contents($tempFile, $scriptContent);
        chmod($tempFile, 0755);

        return $tempFile;
    }

    private function prepareEnvironmentVariables(array $input): array
    {
        $envVars = $this->getEnvironmentVariables();

        // Add input parameters as environment variables with MCP_INPUT_ prefix
        foreach ($input as $key => $value) {
            $envKey = 'MCP_INPUT_'.strtoupper($key);
            $envVars[$envKey] = is_array($value) ? json_encode($value) : (string) $value;
        }

        // Add tool metadata
        $envVars['MCP_TOOL_NAME'] = $this->getName();
        $envVars['MCP_TOOL_TYPE'] = 'bash';

        return $envVars;
    }

    private function executeScript(string $scriptPath, array $envVars): \Illuminate\Process\ProcessResult
    {
        Log::info("Executing bash script: {$this->handler->name}", [
            'script_path' => $scriptPath,
            'timeout' => $this->getTimeoutSeconds(),
            'env_vars_count' => count($envVars),
        ]);

        $result = Process::timeout($this->getTimeoutSeconds())
            ->env($envVars)
            ->run(['bash', $scriptPath]);

        if (! $result->successful()) {
            Log::error("Bash script failed: {$this->handler->name}", [
                'exit_code' => $result->exitCode(),
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);

            throw new \RuntimeException(
                "Script execution failed with exit code {$result->exitCode()}: {$result->errorOutput()}"
            );
        }

        return $result;
    }

    private function parseScriptOutput(\Illuminate\Process\ProcessResult $result): array
    {
        $output = trim($result->output());
        $errorOutput = trim($result->errorOutput());

        // Try to parse output as JSON first
        if (! empty($output)) {
            $decoded = json_decode($output, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'result' => $decoded,
                    'raw_output' => $output,
                    'exit_code' => $result->exitCode(),
                    'execution_time' => $result->runTime ?? 0,
                ];
            }
        }

        // If not JSON, return as raw output
        return [
            'result' => $output,
            'raw_output' => $output,
            'error_output' => $errorOutput,
            'exit_code' => $result->exitCode(),
            'execution_time' => $result->runTime ?? 0,
        ];
    }
}
