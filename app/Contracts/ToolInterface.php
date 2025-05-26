<?php

namespace App\Contracts;

use App\Models\Tool;

interface ToolInterface
{
    /**
     * Create a new tool instance
     */
    public function __construct(Tool $tool);

    /**
     * Execute the tool with given input
     *
     * @param  array  $input  The input parameters for the tool
     * @return array The tool output
     *
     * @throws \InvalidArgumentException When input is invalid
     */
    public function execute(array $input): array;

    /**
     * Validate the input against the tool's schema
     *
     * @param  array  $input  The input to validate
     * @return bool True if valid
     *
     * @throws \InvalidArgumentException When input is invalid
     */
    public function validateInput(array $input): bool;

    /**
     * Get the tool's name
     */
    public function getName(): string;

    /**
     * Get the tool's description
     */
    public function getDescription(): string;

    /**
     * Get the tool's input schema
     */
    public function getInputSchema(): array;

    /**
     * Get additional tool settings
     */
    public function getSettings(): array;
}
