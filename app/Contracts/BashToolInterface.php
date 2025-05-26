<?php

namespace App\Contracts;

interface BashToolInterface
{
    /**
     * Get the tool name
     */
    public function getName(): string;

    /**
     * Get the tool description
     */
    public function getDescription(): string;

    /**
     * Execute the bash script with the given input
     *
     * @param  array  $input  The input parameters for the tool
     * @return array The output result from the bash script
     */
    public function execute(array $input): array;

    /**
     * Validate the input parameters
     */
    public function validateInput(array $input): bool;

    /**
     * Get the bash script content
     */
    public function getScriptContent(): string;

    /**
     * Get environment variables for the script execution
     */
    public function getEnvironmentVariables(): array;

    /**
     * Get the execution timeout in seconds
     */
    public function getTimeoutSeconds(): int;
}
