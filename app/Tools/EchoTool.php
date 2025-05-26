<?php

namespace App\Tools;

class EchoTool extends BaseTool
{
    public function execute(array $input): array
    {
        $message = $this->validateString($input, 'message');

        // Optional: Add delay for testing purposes
        $delay = $input['delay'] ?? 0;
        if ($delay > 0 && $delay <= 5) { // Max 5 seconds
            usleep($delay * 1000000); // Convert to microseconds
        }

        return [
            'echoed_message' => $message,
            'timestamp' => now()->toISOString(),
            'length' => strlen($message),
        ];
    }
}
