Okay, I understand now! You're referring to the **Model-Client Protocol (MCP) by Anthropic**, which is a specification for how Large Language Models (LLMs) should interact with external "tools." This is a fascinating and highly relevant area.

My apologies for the initial misunderstanding; "MCP" has other common meanings in software.

Let's dive into how you can build a Laravel + Filament system that acts as an **MCP-compliant Tool Server**. This is the most direct way for a first-time MCP user to engage with the protocol.

**Goal:** Create a Laravel application that exposes one or more "tools" that an LLM (like Claude, or any LLM that can speak MCP) can call. Filament will be used for managing and monitoring these tools and their interactions.

**Core Concept of MCP Tool Interaction:**

1.  **LLM (Model):** Decides it needs to use an external tool to answer a query or perform an action.
2.  **LLM -> Tool Server (Your Laravel App):** Sends an HTTP request (usually POST) with a JSON payload. This payload is the `tool_run` request and typically includes:
    *   `tool_name`: The identifier for the tool the LLM wants to use.
    *   `tool_input`: A JSON object containing the parameters for the tool.
3.  **Tool Server (Your Laravel App):**
    *   Receives the `tool_run` request.
    *   Identifies the requested tool.
    *   Executes the tool's logic using the provided `tool_input`.
    *   Constructs a `tool_return` JSON response, which includes:
        *   `tool_output`: The result from the tool (can be any JSON value).
        *   `is_error`: A boolean indicating if an error occurred.
        *   `error_type` (string, optional): A category for the error.
        *   `error_message` (string, optional): A human-readable error message.
4.  **Tool Server -> LLM:** Sends the HTTP response containing the `tool_return` JSON.
5.  **LLM:** Uses the `tool_output` (or error information) to continue its process or generate a response for the end-user.

---

**First Test Candidate: Laravel MCP Tool Server with a Simple "Calculator" Tool**

This will give you a hands-on understanding of implementing an MCP-compliant tool.

**Part 1: Laravel MCP Tool Server Implementation**

1.  **Create a New Laravel Project:**
    ```bash
    composer create-project laravel/laravel mcp-tool-server
    cd mcp-tool-server
    php artisan serve // Keep this running
    ```

2.  **Define an API Route for Tool Runs:**
    In `routes/api.php`:
    ```php
    use Illuminate\Support\Facades\Route;
    use App\Http\Controllers\McpToolController;

    Route::post('/mcp/run_tool', [McpToolController::class, 'handleToolRun'])
        ->name('mcp.run_tool');
    ```

3.  **Create the `McpToolController`:**
    ```bash
    php artisan make:controller McpToolController
    ```
    Populate `app/Http/Controllers/McpToolController.php`:
    ```php
    namespace App\Http\Controllers;

    use Illuminate\Http\Request;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Validation\ValidationException;

    class McpToolController extends Controller
    {
        public function handleToolRun(Request $request): JsonResponse
        {
            Log::info('MCP Request Received:', $request->all());

            try {
                $validatedData = $request->validate([
                    'tool_name' => 'required|string',
                    'tool_input' => 'required|array',
                ]);
            } catch (ValidationException $e) {
                Log::error('MCP Validation Error:', $e->errors());
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
                switch ($toolName) {
                    case 'calculator':
                        $toolOutput = $this->runCalculatorTool($toolInput);
                        break;
                    case 'get_current_datetime':
                        $toolOutput = $this->runGetCurrentDateTimeTool($toolInput);
                        break;
                    // Add more tools here
                    default:
                        $isError = true;
                        $errorType = 'unknown_tool';
                        $errorMessage = "Tool '{$toolName}' is not recognized by this server.";
                        Log::warning("Unknown tool requested: {$toolName}");
                        // Per MCP, unknown tool should still be a 200 OK with error fields
                        break;
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

            $responsePayload = [
                'tool_output' => $toolOutput, // Will be null if an error occurred before output generation
                'is_error' => $isError,
            ];

            if ($isError) {
                $responsePayload['error_type'] = $errorType;
                $responsePayload['error_message'] = $errorMessage;
            }

            Log::info('MCP Response Payload:', $responsePayload);
            return response()->json($responsePayload); // MCP expects a 200 OK even for tool errors
        }

        /**
         * Simple calculator tool.
         * Expected input: { "operation": "add|subtract|multiply|divide", "a": number, "b": number }
         * Output: { "result": number }
         */
        private function runCalculatorTool(array $input): array
        {
            if (!isset($input['operation'], $input['a'], $input['b'])) {
                throw new \InvalidArgumentException("Calculator tool requires 'operation', 'a', and 'b' inputs.");
            }
            if (!is_numeric($input['a']) || !is_numeric($input['b'])) {
                throw new \InvalidArgumentException("Inputs 'a' and 'b' for calculator must be numeric.");
            }

            $a = floatval($input['a']);
            $b = floatval($input['b']);
            $result = 0;

            switch (strtolower($input['operation'])) {
                case 'add':
                    $result = $a + $b;
                    break;
                case 'subtract':
                    $result = $a - $b;
                    break;
                case 'multiply':
                    $result = $a * $b;
                    break;
                case 'divide':
                    if ($b == 0) {
                        throw new \InvalidArgumentException("Cannot divide by zero.");
                    }
                    $result = $a / $b;
                    break;
                default:
                    throw new \InvalidArgumentException("Unsupported operation: '{$input['operation']}'. Supported: add, subtract, multiply, divide.");
            }
            return ['result' => $result];
        }

        /**
         * Gets the current date and time.
         * Expected input: { "timezone": "IANA_timezone_string" } (optional, defaults to UTC)
         * Output: { "datetime_iso8601": "YYYY-MM-DDTHH:MM:SSZ", "timezone": "used_timezone" }
         */
        private function runGetCurrentDateTimeTool(array $input): array
        {
            $timezoneString = $input['timezone'] ?? 'UTC';
            try {
                $timezone = new \DateTimeZone($timezoneString);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException("Invalid timezone string: '{$timezoneString}'. Please use a valid IANA timezone identifier.");
            }

            $now = new \DateTime('now', $timezone);
            return [
                'datetime_iso8601' => $now->format(\DateTime::ATOM), // Or \DateTime::ISO8601
                'timezone' => $timezone->getName(),
            ];
        }
    }
    ```

4.  **Test with `curl` or a REST Client (e.g., Postman, Insomnia):**

    *   **Calculator - Success:**
        ```bash
        curl -X POST http://127.0.0.1:8000/api/mcp/run_tool \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d '{
            "tool_name": "calculator",
            "tool_input": {
                "operation": "multiply",
                "a": 7,
                "b": 6
            }
        }'
        ```
        Expected Output:
        ```json
        {
            "tool_output": {
                "result": 42
            },
            "is_error": false
        }
        ```

    *   **Calculator - Tool Input Error:**
        ```bash
        curl -X POST http://127.0.0.1:8000/api/mcp/run_tool \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d '{
            "tool_name": "calculator",
            "tool_input": {
                "operation": "divide",
                "a": 10,
                "b": 0
            }
        }'
        ```
        Expected Output:
        ```json
        {
            "tool_output": null,
            "is_error": true,
            "error_type": "invalid_tool_input",
            "error_message": "Cannot divide by zero."
        }
        ```

    *   **Get DateTime - Success (Default UTC):**
        ```bash
        curl -X POST http://127.0.0.1:8000/api/mcp/run_tool \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d '{
            "tool_name": "get_current_datetime",
            "tool_input": {}
        }'
        ```
        Expected Output (example):
        ```json
        {
            "tool_output": {
                "datetime_iso8601": "2025-05-26T14:50:00+00:00",
                "timezone": "UTC"
            },
            "is_error": false
        }
        ```

    *   **Get DateTime - Success (Specific Timezone):**
        ```bash
        curl -X POST http://127.0.0.1:8000/api/mcp/run_tool \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d '{
            "tool_name": "get_current_datetime",
            "tool_input": {
                "timezone": "America/New_York"
            }
        }'
        ```

    *   **Unknown Tool:**
        ```bash
        curl -X POST http://127.0.0.1:8000/api/mcp/run_tool \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d '{
            "tool_name": "make_coffee",
            "tool_input": {}
        }'
        ```
        Expected Output:
        ```json
        {
            "tool_output": null,
            "is_error": true,
            "error_type": "unknown_tool",
            "error_message": "Tool 'make_coffee' is not recognized by this server."
        }
        ```

    *   **Invalid MCP Request Format:**
        ```bash
        curl -X POST http://127.0.0.1:8000/api/mcp/run_tool \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d '{
            "name_of_tool": "calculator",
            "input_for_tool": {}
        }'
        ```
        Expected Output (HTTP 400):
        ```json
        {
            "tool_output": null,
            "is_error": true,
            "error_type": "invalid_request_format",
            "error_message": "Request must include tool_name (string) and tool_input (object)."
        }
        ```

You now have a basic, functioning MCP Tool Server!

---

**Part 2: Integrating Filament for Management and Logging**

This is where Filament shines, providing a UI to oversee your MCP tools.

1.  **Install Filament:**
    ```bash
    composer require filament/filament:"^3.2" -W
    php artisan filament:install --panels
    ```
    Create a Filament user when prompted. Access your panel at `/admin`.

2.  **Create a Model and Migration for MCP Tool Run Logs:**
    ```bash
    php artisan make:model McpToolRunLog -m
    ```
    Edit the generated migration file (`database/migrations/..._create_mcp_tool_run_logs_table.php`):
    ```php
    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up(): void
        {
            Schema::create('mcp_tool_run_logs', function (Blueprint $table) {
                $table->id();
                $table->string('tool_name');
                $table->json('tool_input')->nullable();
                $table->json('tool_output')->nullable();
                $table->boolean('is_error')->default(false);
                $table->string('error_type')->nullable();
                $table->text('error_message')->nullable();
                $table->text('raw_request_payload')->nullable(); // For debugging
                $table->ipAddress('request_ip')->nullable();
                $table->timestamps();
            });
        }

        public function down(): void
        {
            Schema::dropIfExists('mcp_tool_run_logs');
        }
    };
    ```
    Run the migration:
    ```bash
    php artisan migrate
    ```
    In `app/Models/McpToolRunLog.php`, add fillable properties and casts:
    ```php
    namespace App\Models;

    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;

    class McpToolRunLog extends Model
    {
        use HasFactory;

        protected $fillable = [
            'tool_name',
            'tool_input',
            'tool_output',
            'is_error',
            'error_type',
            'error_message',
            'raw_request_payload',
            'request_ip',
        ];

        protected $casts = [
            'tool_input' => 'array',
            'tool_output' => 'array',
            'is_error' => 'boolean',
            'raw_request_payload' => 'array',
        ];
    }
    ```

3.  **Update `McpToolController` to Log Runs:**
    Modify `app/Http/Controllers/McpToolController.php`'s `handleToolRun` method. Before `return response()->json(...)`:
    ```php
    // ... (inside handleToolRun, before the final return)
    use App\Models\McpToolRunLog; // Add this at the top of the class

    // ...
            Log::info('MCP Response Payload:', $responsePayload);

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
            ]);

            return response()->json($responsePayload);
    // ...
    ```

4.  **Create a Filament Resource for `McpToolRunLog`:**
    ```bash
    php artisan make:filament-resource McpToolRunLog --view --generate
    ```
    This command creates the resource, generates schema for forms/tables, and creates view/list/edit pages.
    Customize `app/Filament/Resources/McpToolRunLogResource.php`:
    ```php
    namespace App\Filament\Resources;

    use App\Filament\Resources\McpToolRunLogResource\Pages;
    use App\Models\McpToolRunLog;
    use Filament\Forms;
    use Filament\Forms\Form;
    use Filament\Resources\Resource;
    use Filament\Tables;
    use Filament\Tables\Table;
    use Filament\Infolists;
    use Filament\Infolists\Infolist;

    class McpToolRunLogResource extends Resource
    {
        protected static ?string $model = McpToolRunLog::class;
        protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
        protected static ?string $navigationGroup = 'MCP';
        protected static ?string $recordTitleAttribute = 'tool_name';

        public static function form(Form $form): Form
        {
            // Logs are typically read-only in Filament, but a form is needed
            return $form
                ->schema([
                    Forms\Components\TextInput::make('tool_name')->disabled(),
                    Forms\Components\DateTimePicker::make('created_at')->label('Timestamp')->disabled(),
                    Forms\Components\Toggle::make('is_error')->disabled(),
                    Forms\Components\TextInput::make('error_type')->disabled(),
                    Forms\Components\Textarea::make('error_message')->columnSpanFull()->disabled(),
                    Forms\Components\Textarea::make('request_ip')->disabled(),
                    // Using KeyValue for JSON display, or dedicated JSON viewer field if available
                    Forms\Components\KeyValue::make('tool_input')->columnSpanFull()->disabled(),
                    Forms\Components\KeyValue::make('tool_output')->columnSpanFull()->disabled(),
                    Forms\Components\KeyValue::make('raw_request_payload')->columnSpanFull()->disabled(),
                ]);
        }

        public static function table(Table $table): Table
        {
            return $table
                ->columns([
                    Tables\Columns\TextColumn::make('created_at')->label('Timestamp')->dateTime()->sortable()->toggleable(),
                    Tables\Columns\TextColumn::make('tool_name')->searchable()->sortable(),
                    Tables\Columns\IconColumn::make('is_error')->boolean(),
                    Tables\Columns\TextColumn::make('error_type')->searchable()->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('request_ip')->searchable()->toggleable(isToggledHiddenByDefault: true),
                ])
                ->filters([
                    Tables\Filters\TernaryFilter::make('is_error'),
                    Tables\Filters\Filter::make('created_at')
                        ->form([
                            Forms\Components\DatePicker::make('created_from'),
                            Forms\Components\DatePicker::make('created_until'),
                        ])
                        ->query(function ($query, array $data) {
                            return $query
                                ->when($data['created_from'], fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
                                ->when($data['created_until'], fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
                        }),
                ])
                ->actions([
                    Tables\Actions\ViewAction::make(),
                    // Tables\Actions\EditAction::make(), // Usually don't edit logs
                ])
                ->bulkActions([
                    // Tables\Actions\BulkActionGroup::make([
                    //     Tables\Actions\DeleteBulkAction::make(),
                    // ]),
                ])
                ->defaultSort('created_at', 'desc');
        }

        public static function infolist(Infolist $infolist): Infolist
        {
            return $infolist
                ->schema([
                    Infolists\Components\TextEntry::make('tool_name'),
                    Infolists\Components\TextEntry::make('created_at')->label('Timestamp')->dateTime(),
                    Infolists\Components\IconEntry::make('is_error')->boolean(),
                    Infolists\Components\TextEntry::make('error_type'),
                    Infolists\Components\TextEntry::make('error_message')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('request_ip'),
                    Infolists\Components\KeyValueEntry::make('tool_input')->columnSpanFull(),
                    Infolists\Components\KeyValueEntry::make('tool_output')->columnSpanFull(),
                    Infolists\Components\KeyValueEntry::make('raw_request_payload')->label('Raw Request')->columnSpanFull(),
                ]);
        }

        public static function getRelations(): array
        {
            return [];
        }

        public static function getPages(): array
        {
            return [
                'index' => Pages\ListMcpToolRunLogs::route('/'),
                'create' => Pages\CreateMcpToolRunLog::route('/create'), // Can remove if not needed
                // 'edit' => Pages\EditMcpToolRunLog::route('/{record}/edit'), // Can remove
                'view' => Pages\ViewMcpToolRunLog::route('/{record}'),
            ];
        }

        public static function canCreate(): bool // Prevent creating logs manually via UI
        {
            return false;
        }
    }
    ```
    *   **Note:** You might want to remove the `CreateMcpToolRunLog` and `EditMcpToolRunLog` pages if you only want to view logs. Adjust `getPages()` and `canCreate()` accordingly. The `ViewAction` and `Infolist` are key for seeing details.

5.  **Test Again:**
    Run your `curl` commands again. Now, each call should generate an entry in the `mcp_tool_run_logs` table. Go to your Filament admin panel (`/admin`) and navigate to "Mcp Tool Run Logs" to see the recorded interactions. You can view the details of each run.

---

**Why this is an excellent first test for an MCP user:**

*   **Hands-on with the Protocol:** You've directly implemented the server-side of MCP, handling `tool_run` and generating `tool_return`.
*   **Practical Laravel/Filament Usage:** You're using Laravel for API logic and Filament for a real-world admin/monitoring task.
*   **Clear Feedback Loop:** Testing with `curl` and seeing logs in Filament provides immediate confirmation of what's happening.
*   **Foundation for Real LLM Integration:**
    *   Your `/api/mcp/run_tool` endpoint is now ready to be called by an actual LLM (e.g., Claude via the Anthropic API).
    *   When you configure Claude (or another LLM) to use tools, you'll provide it with:
        1.  The URL of your tool server endpoint.
        2.  A "tool specification" for each tool (e.g., for `calculator`: name, description, input JSON schema).
*   **Extensible:** You can easily add more tools to `McpToolController` and, if desired, manage their definitions (name, description, input schema) via another Filament resource.

This setup provides a robust and understandable starting point for working with Anthropic's MCP using Laravel and Filament. You can now focus on building more complex tools and integrating them with an LLM.
