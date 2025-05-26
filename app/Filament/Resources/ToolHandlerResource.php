<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ToolHandlerResource\Pages;
use App\Models\ToolHandler;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ToolHandlerResource extends Resource
{
    protected static ?string $model = ToolHandler::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static ?string $navigationGroup = 'MCP Tools';

    protected static ?string $recordTitleAttribute = 'display_name';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Select::make('handler_type')
                            ->required()
                            ->options([
                                'php' => 'PHP Class',
                                'bash' => 'Bash Script',
                            ])
                            ->default('php')
                            ->live()
                            ->helperText('Type of handler implementation'),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->rules(['regex:/^[a-z_]+$/'])
                            ->helperText('Handler identifier (lowercase, underscores only)')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => 
                                $set('display_name', ucwords(str_replace('_', ' ', $state)) . ' Tool')
                            ),

                        Forms\Components\TextInput::make('display_name')
                            ->required()
                            ->helperText('Human-readable name for the handler'),

                        Forms\Components\Textarea::make('description')
                            ->required()
                            ->rows(3)
                            ->helperText('Detailed description of what this handler does'),

                        Forms\Components\TextInput::make('version')
                            ->default('1.0.0')
                            ->helperText('Semantic version (e.g., 1.0.0)'),

                        Forms\Components\TextInput::make('author')
                            ->helperText('Author name or organization'),
                    ])->columns(2),

                Forms\Components\Section::make('Technical Configuration')
                    ->schema([
                        // PHP-specific fields
                        Forms\Components\TextInput::make('class_name')
                            ->required(fn (Forms\Get $get): bool => $get('handler_type') === 'php')
                            ->rules(['regex:/^[A-Z][a-zA-Z0-9_]*$/'])
                            ->helperText('PHP class name (PascalCase)')
                            ->visible(fn (Forms\Get $get): bool => $get('handler_type') === 'php')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($get('handler_type') === 'php') {
                                    $namespace = $get('namespace') ?? 'App\\Tools';
                                    $fileName = $state . '.php';
                                    $set('file_path', 'app/Tools/' . $fileName);
                                }
                            }),

                        Forms\Components\TextInput::make('namespace')
                            ->default('App\\Tools')
                            ->required(fn (Forms\Get $get): bool => $get('handler_type') === 'php')
                            ->helperText('PHP namespace for the handler class')
                            ->visible(fn (Forms\Get $get): bool => $get('handler_type') === 'php'),

                        Forms\Components\TextInput::make('file_path')
                            ->required(fn (Forms\Get $get): bool => $get('handler_type') === 'php')
                            ->helperText('File path relative to project root')
                            ->suffixIcon('heroicon-m-document')
                            ->visible(fn (Forms\Get $get): bool => $get('handler_type') === 'php'),

                        // Bash-specific fields
                        Forms\Components\TextInput::make('timeout_seconds')
                            ->numeric()
                            ->default(30)
                            ->helperText('Script execution timeout in seconds (max 300)')
                            ->rules(['min:1', 'max:300'])
                            ->visible(fn (Forms\Get $get): bool => $get('handler_type') === 'bash'),

                        // Common fields
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Whether this handler is available for use'),

                        Forms\Components\Toggle::make('is_built_in')
                            ->label('Built-in Handler')
                            ->default(false)
                            ->helperText('Built-in handlers cannot be deleted')
                            ->disabled(fn (Forms\Get $get): bool => $get('is_built_in') === true),

                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Display order (lower numbers appear first)'),
                    ])->columns(2),

                // Bash Environment Variables
                Forms\Components\Section::make('Environment Variables')
                    ->description('Define environment variables for bash script execution')
                    ->schema([
                        Forms\Components\KeyValue::make('environment_variables')
                            ->label('')
                            ->keyLabel('Variable Name')
                            ->valueLabel('Variable Value')
                            ->addActionLabel('Add Environment Variable')
                            ->helperText('Environment variables available to the bash script'),
                    ])
                    ->visible(fn (Forms\Get $get): bool => $get('handler_type') === 'bash')
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Dependencies')
                    ->description('Define any required packages or dependencies')
                    ->schema([
                        Forms\Components\KeyValue::make('dependencies')
                            ->label('')
                            ->keyLabel('Package/Dependency')
                            ->valueLabel('Version/Notes')
                            ->addActionLabel('Add Dependency')
                            ->helperText('Optional dependencies this handler requires'),
                    ])
                    ->collapsible()
                    ->collapsed(),

                Forms\Components\Section::make('Default Input Schema Template')
                    ->description('Default JSON schema template for tools using this handler')
                    ->schema([
                        Forms\Components\Textarea::make('input_schema_template')
                            ->label('')
                            ->rows(10)
                            ->placeholder('{
  "type": "object",
  "properties": {
    "param1": {
      "type": "string",
      "description": "Description of parameter"
    }
  },
  "required": ["param1"]
}')
                            ->helperText('JSON schema template that tools can use as a starting point')
                            ->formatStateUsing(function (?array $state): ?string {
                                return $state ? json_encode($state, JSON_PRETTY_PRINT) : null;
                            })
                            ->dehydrateStateUsing(function (?string $state): ?array {
                                if (empty($state)) {
                                    return null;
                                }
                                
                                $decoded = json_decode($state, true);
                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    throw new \Exception('Invalid JSON schema: ' . json_last_error_msg());
                                }
                                
                                return $decoded;
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),

                // PHP Handler Code Section
                Forms\Components\Section::make('PHP Handler Code')
                    ->description('Upload or edit the PHP handler file')
                    ->schema([
                        Forms\Components\FileUpload::make('handler_file')
                            ->label('Upload PHP Handler File')
                            ->acceptedFileTypes(['application/x-php', '.php'])
                            ->directory('tool-handlers')
                            ->visibility('private')
                            ->helperText('Upload a PHP file containing the handler class')
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if ($state) {
                                    $className = $get('class_name');
                                    if ($className) {
                                        $set('file_path', 'storage/app/tool-handlers/' . $state);
                                    }
                                }
                            }),

                        Forms\Components\Textarea::make('handler_code')
                            ->label('PHP Handler Code (Optional)')
                            ->rows(15)
                            ->placeholder('<?php

namespace App\Tools;

use App\Contracts\ToolInterface;
use App\Tools\BaseTool;

class YourTool extends BaseTool implements ToolInterface
{
    public function getName(): string
    {
        return "your_tool";
    }

    public function getDescription(): string
    {
        return "Description of your tool";
    }

    public function execute(array $input): array
    {
        // Your tool logic here
        return ["result" => "success"];
    }
}')
                            ->helperText('Write PHP handler code directly (will create/update the file)')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Forms\Get $get): bool => $get('handler_type') === 'php')
                    ->collapsible()
                    ->collapsed(),

                // Bash Script Section
                Forms\Components\Section::make('Bash Script')
                    ->description('Write the bash script that will be executed')
                    ->schema([
                        Forms\Components\Textarea::make('script_content')
                            ->label('Bash Script Content')
                            ->required(fn (Forms\Get $get): bool => $get('handler_type') === 'bash')
                            ->rows(20)
                            ->placeholder('#!/bin/bash

# Input parameters are available as environment variables with MCP_INPUT_ prefix
# For example: $MCP_INPUT_MESSAGE, $MCP_INPUT_COUNT, etc.

echo "Tool: $MCP_TOOL_NAME"
echo "Input message: $MCP_INPUT_MESSAGE"

# Process your logic here
result="Hello, $MCP_INPUT_MESSAGE!"

# Output JSON for structured results
echo "{\"result\": \"$result\", \"status\": \"success\"}"')
                            ->helperText('Write your bash script. Input parameters are available as environment variables with MCP_INPUT_ prefix. Output JSON for structured results.')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('bash_help')
                            ->label('')
                            ->content('**Available Environment Variables:**
- `$MCP_TOOL_NAME` - Name of the tool
- `$MCP_TOOL_TYPE` - Always "bash" for bash handlers
- `$MCP_INPUT_*` - Input parameters (e.g., `$MCP_INPUT_MESSAGE` for "message" parameter)

**Output Format:**
- Output JSON to stdout for structured results
- Use stderr for error messages
- Exit with non-zero code for errors')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Forms\Get $get): bool => $get('handler_type') === 'bash')
                    ->collapsible(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('handler_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'php' => 'info',
                        'bash' => 'warning',
                        default => 'gray'
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),

                Tables\Columns\TextColumn::make('name')
                    ->label('Identifier')
                    ->searchable()
                    ->fontFamily('mono')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('description')
                    ->limit(60)
                    ->searchable(),

                Tables\Columns\TextColumn::make('version')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\IconColumn::make('is_built_in')
                    ->label('Built-in')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('warning')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Tools Using')
                    ->badge()
                    ->color('info')
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('author')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All handlers')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                Tables\Filters\TernaryFilter::make('is_built_in')
                    ->label('Built-in Status')
                    ->placeholder('All handlers')
                    ->trueLabel('Built-in only')
                    ->falseLabel('Custom only'),

                Tables\Filters\SelectFilter::make('handler_type')
                    ->label('Handler Type')
                    ->options([
                        'php' => 'PHP',
                        'bash' => 'Bash',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('test')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->action(function (ToolHandler $record) {
                        try {
                            $isValid = $record->isValidHandler();
                            
                            if ($isValid) {
                                Notification::make()
                                    ->title('✅ Handler Validation Successful')
                                    ->body('Handler class exists and implements ToolInterface correctly.')
                                    ->success()
                                    ->persistent()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('❌ Handler Validation Failed')
                                    ->body('Handler class does not exist or does not implement ToolInterface.')
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('❌ Handler Test Failed')
                                ->body('Error: ' . $e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->before(function (ToolHandler $record) {
                        if ($record->is_built_in) {
                            Notification::make()
                                ->title('Cannot Delete Built-in Handler')
                                ->body('Built-in handlers cannot be deleted.')
                                ->danger()
                                ->send();
                            
                            return false;
                        }
                        
                        if ($record->usage_count > 0) {
                            Notification::make()
                                ->title('Cannot Delete Handler')
                                ->body("This handler is used by {$record->usage_count} tool(s). Remove the tools first.")
                                ->danger()
                                ->send();
                            
                            return false;
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            $builtInCount = $records->where('is_built_in', true)->count();
                            $inUseCount = $records->filter(function ($record) {
                                return $record->usage_count > 0;
                            })->count();
                            
                            if ($builtInCount > 0) {
                                Notification::make()
                                    ->title('Cannot Delete Built-in Handlers')
                                    ->body("{$builtInCount} built-in handlers cannot be deleted.")
                                    ->danger()
                                    ->send();
                                
                                return false;
                            }
                            
                            if ($inUseCount > 0) {
                                Notification::make()
                                    ->title('Cannot Delete Handlers')
                                    ->body("{$inUseCount} handlers are in use by tools.")
                                    ->danger()
                                    ->send();
                                
                                return false;
                            }
                        }),

                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Handler Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('display_name')
                            ->label('Display Name'),
                        Infolists\Components\TextEntry::make('name')
                            ->label('Identifier')
                            ->fontFamily('mono'),
                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('version')
                            ->badge(),
                        Infolists\Components\TextEntry::make('author'),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('is_built_in')
                            ->label('Built-in')
                            ->boolean(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Technical Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('full_class_name')
                            ->label('Full Class Name')
                            ->fontFamily('mono')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('file_path')
                            ->label('File Path')
                            ->fontFamily('mono')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('sort_order')
                            ->label('Sort Order'),
                    ])
                    ->columns(1),

                Infolists\Components\Section::make('Dependencies')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('dependencies')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (ToolHandler $record): bool => !empty($record->dependencies)),

                Infolists\Components\Section::make('Input Schema Template')
                    ->schema([
                        Infolists\Components\TextEntry::make('input_schema_template')
                            ->label('')
                            ->formatStateUsing(fn (ToolHandler $record): string => 
                                $record->input_schema_template 
                                    ? json_encode($record->input_schema_template, JSON_PRETTY_PRINT)
                                    : 'No template defined'
                            )
                            ->fontFamily('mono')
                            ->copyable(),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Usage Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('usage_count')
                            ->label('Tools Using This Handler')
                            ->formatStateUsing(fn (ToolHandler $record): string => (string) $record->usage_count),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListToolHandlers::route('/'),
            'create' => Pages\CreateToolHandler::route('/create'),
            'edit' => Pages\EditToolHandler::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count();
    }
}
