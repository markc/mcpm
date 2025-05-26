<?php

use App\Http\Controllers\McpToolController;
use Illuminate\Support\Facades\Route;

Route::post('/mcp/run_tool', [McpToolController::class, 'handleToolRun'])
    ->name('mcp.run_tool');

Route::get('/mcp/tools', [McpToolController::class, 'listTools'])
    ->name('mcp.list_tools');
