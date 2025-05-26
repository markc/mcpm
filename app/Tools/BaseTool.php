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
        return $this->tool->input_schema;
    }

    public function getSettings(): array
    {
        return $this->tool->settings ?? [];
    }

    public function validateInput(array $input): bool
    {
        // Simple validation - check required fields
        $schema = $this->getInputSchema();

        if (isset($schema['required'])) {
            foreach ($schema['required'] as $required) {
                if (! isset($input[$required])) {
                    throw new \InvalidArgumentException("Missing required parameter: {$required}");
                }
            }
        }

        // Additional validation can be added here
        return true;
    }

    /**
     * Abstract method that concrete tools must implement
     */
    abstract public function execute(array $input): array;

    /**
     * Helper method to get a setting value with default
     */
    protected function getSetting(string $key, $default = null)
    {
        return $this->getSettings()[$key] ?? $default;
    }

    /**
     * Helper method to validate numeric input
     */
    protected function validateNumeric(array $input, string $field): float
    {
        if (! isset($input[$field])) {
            throw new \InvalidArgumentException("Missing required parameter: {$field}");
        }

        if (! is_numeric($input[$field])) {
            throw new \InvalidArgumentException("Parameter '{$field}' must be numeric");
        }

        return floatval($input[$field]);
    }

    /**
     * Helper method to validate string input
     */
    protected function validateString(array $input, string $field): string
    {
        if (! isset($input[$field])) {
            throw new \InvalidArgumentException("Missing required parameter: {$field}");
        }

        if (! is_string($input[$field])) {
            throw new \InvalidArgumentException("Parameter '{$field}' must be a string");
        }

        return $input[$field];
    }

    /**
     * Helper method to validate enum input
     */
    protected function validateEnum(array $input, string $field, array $allowedValues): string
    {
        $value = $this->validateString($input, $field);

        if (! in_array($value, $allowedValues)) {
            $allowed = implode(', ', $allowedValues);
            throw new \InvalidArgumentException("Parameter '{$field}' must be one of: {$allowed}");
        }

        return $value;
    }
}
