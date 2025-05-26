<?php

namespace App\Http\Controllers;

use App\Models\McpToolRunLog;
use App\Services\ToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class McpToolController extends Controller
{
    public function __construct(
        private ToolRegistry $toolRegistry
    ) {}

    /**
     * Handle MCP tool run requests
     */
    public function handleToolRun(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        Log::info('MCP Request Received:', $request->all());

        try {
            $validatedData = $request->validate([
                'tool_name' => 'required|string',
                'tool_input' => 'present',
            ]);

            // Ensure tool_input is an array (Laravel converts JSON objects to arrays)
            if (! is_array($validatedData['tool_input'])) {
                $validatedData['tool_input'] = [];
            }
        } catch (ValidationException $e) {
            Log::error('MCP Validation Error:', $e->errors());

            // Log validation error
            McpToolRunLog::create([
                'tool_name' => $request->input('tool_name', 'unknown'),
                'tool_input' => $request->input('tool_input'),
                'tool_output' => null,
                'is_error' => true,
                'error_type' => 'invalid_request_format',
                'error_message' => 'Request must include tool_name (string) and tool_input (object).',
                'raw_request_payload' => $request->all(),
                'request_ip' => $request->ip(),
                'execution_time_ms' => (microtime(true) - $startTime) * 1000,
            ]);

            return response()->json([
                'tool_output' => null,
                'is_error' => true,
                'error_type' => 'invalid_request_format',
                'error_message' => 'Request must include tool_name (string) and tool_input (object).',
            ], 400);
        }

        $toolName = $validatedData['tool_name'];
        $toolInput = $validatedData['tool_input'];

        $toolOutput = null;
        $isError = false;
        $errorType = null;
        $errorMessage = null;

        try {
            if (! $this->toolRegistry->hasToolWithGetter($toolName)) {
                $isError = true;
                $errorType = 'unknown_tool';
                $errorMessage = "Tool '{$toolName}' is not recognized by this server.";
                Log::warning("Unknown tool requested: {$toolName}");
            } else {
                $toolOutput = $this->toolRegistry->executeTool($toolName, $toolInput);
            }
        } catch (\InvalidArgumentException $e) {
            $isError = true;
            $errorType = 'invalid_tool_input';
            $errorMessage = $e->getMessage();
            Log::error("Error in tool '{$toolName}': {$errorMessage}", ['input' => $toolInput]);
        } catch (\Exception $e) {
            $isError = true;
            $errorType = 'tool_execution_error';
            $errorMessage = "An unexpected error occurred while running tool '{$toolName}'.";
            Log::critical("Critical error in tool '{$toolName}': {$e->getMessage()}", ['exception' => $e]);
        }

        $executionTime = (microtime(true) - $startTime) * 1000;

        $responsePayload = [
            'tool_output' => $toolOutput,
            'is_error' => $isError,
        ];

        if ($isError) {
            $responsePayload['error_type'] = $errorType;
            $responsePayload['error_message'] = $errorMessage;
        }

        // Log the MCP interaction
        McpToolRunLog::create([
            'tool_name' => $toolName,
            'tool_input' => $toolInput,
            'tool_output' => $toolOutput,
            'is_error' => $isError,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'raw_request_payload' => $request->all(),
            'request_ip' => $request->ip(),
            'execution_time_ms' => $executionTime,
        ]);

        Log::info('MCP Response Payload:', $responsePayload);

        return response()->json($responsePayload);
    }

    /**
     * List available tools with their schemas
     */
    public function listTools(): JsonResponse
    {
        $tools = $this->toolRegistry->getToolsForDiscovery();

        return response()->json(['tools' => $tools]);
    }
}
