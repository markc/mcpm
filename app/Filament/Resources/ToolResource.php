<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ToolResource\Pages;
use App\Models\Tool;
use App\Services\ToolRegistry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ToolResource extends Resource
{
    protected static ?string $model = Tool::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'MCP Tools';

    protected static ?string $recordTitleAttribute = 'display_name';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->rules(['regex:/^[a-z_]+$/'])
                            ->helperText('Tool identifier (lowercase, underscores only)')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('display_name', ucwords(str_replace('_', ' ', $state)))
                            ),

                        Forms\Components\TextInput::make('display_name')
                            ->required()
                            ->helperText('Human-readable name for the tool'),

                        Forms\Components\Textarea::make('description')
                            ->required()
                            ->rows(3)
                            ->helperText('Detailed description of what this tool does'),

                        Forms\Components\Select::make('handler_class')
                            ->label('Handler Class')
                            ->required()
                            ->options(function () {
                                $handlerDiscovery = app(\App\Services\HandlerDiscoveryService::class);

                                return $handlerDiscovery->getHandlerOptions();
                            })
                            ->searchable()
                            ->helperText('The PHP class that implements this tool')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $handlerDiscovery = app(\App\Services\HandlerDiscoveryService::class);
                                    $handler = $handlerDiscovery->getHandler($state);

                                    if ($handler && $handler->input_schema_template) {
                                        $set('input_schema', $handler->input_schema_template);
                                    }
                                }
                            }),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Whether this tool is available for use'),

                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Display order (lower numbers appear first)'),
                    ])->columns(2),

                Forms\Components\Section::make('Input Schema')
                    ->description('Define the JSON schema for tool input validation')
                    ->schema([
                        Forms\Components\Textarea::make('input_schema')
                            ->label('Input Schema (JSON)')
                            ->rows(15)
                            ->placeholder('{
  "type": "object",
  "properties": {
    "message": {
      "type": "string",
      "description": "The message to process"
    }
  },
  "required": ["message"]
}')
                            ->helperText('Define the JSON schema for input validation. Use standard JSON Schema format.')
                            ->formatStateUsing(function (?array $state): ?string {
                                return $state ? json_encode($state, JSON_PRETTY_PRINT) : null;
                            })
                            ->dehydrateStateUsing(function (?string $state): ?array {
                                if (empty($state)) {
                                    return null;
                                }

                                $decoded = json_decode($state, true);
                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    throw new \Exception('Invalid JSON schema: '.json_last_error_msg());
                                }

                                return $decoded;
                            })
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Settings')
                    ->description('Additional configuration options for this tool')
                    ->schema([
                        Forms\Components\KeyValue::make('settings')
                            ->label('')
                            ->keyLabel('Setting Name')
                            ->valueLabel('Setting Value')
                            ->addActionLabel('Add Setting')
                            ->helperText('Optional key-value pairs for tool configuration'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('runLogs'))
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Identifier')
                    ->searchable()
                    ->fontFamily('mono')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('handler_class')
                    ->label('Handler')
                    ->formatStateUsing(fn (string $state): string => class_basename($state)
                    )
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('run_logs_count')
                    ->label('Usage')
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All tools')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                Tables\Filters\SelectFilter::make('handler_class')
                    ->label('Handler Type')
                    ->options(function () {
                        $handlerDiscovery = app(\App\Services\HandlerDiscoveryService::class);

                        return $handlerDiscovery->getHandlerOptions();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('test')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->modalWidth('2xl')
                    ->form(function (Tool $record) {
                        return self::buildTestForm($record);
                    })
                    ->action(function (Tool $record, array $data) {
                        try {
                            // Check if raw JSON was provided
                            if (! empty($data['_raw_json'])) {
                                $input = json_decode($data['_raw_json'], true);
                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    throw new \Exception('Invalid JSON format: '.json_last_error_msg());
                                }
                            } else {
                                // Remove form metadata and build input from form fields
                                $input = array_filter($data, function ($key) {
                                    return ! in_array($key, ['_test_mode', '_form_id', '_raw_json']);
                                }, ARRAY_FILTER_USE_KEY);
                            }

                            $registry = app(ToolRegistry::class);
                            $result = $registry->executeTool($record->name, $input);

                            Notification::make()
                                ->title('✅ Tool Test Successful')
                                ->body('**Input:**'."\n".'```json'."\n".json_encode($input, JSON_PRETTY_PRINT)."\n".'```'."\n\n".'**Result:**'."\n".'```json'."\n".json_encode($result, JSON_PRETTY_PRINT)."\n".'```')
                                ->success()
                                ->persistent()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('❌ Tool Test Failed')
                                ->body('**Error:** '.$e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                Tables\Actions\ViewAction::make()->label(''),
                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

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
                Infolists\Components\Section::make('Tool Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('display_name')
                            ->label('Display Name'),
                        Infolists\Components\TextEntry::make('name')
                            ->label('Identifier')
                            ->fontFamily('mono'),
                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('handler_class')
                            ->label('Handler Class')
                            ->fontFamily('mono'),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Status')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('sort_order')
                            ->label('Sort Order'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Input Schema')
                    ->schema([
                        Infolists\Components\TextEntry::make('formatted_input_schema')
                            ->label('')
                            ->formatStateUsing(fn (Tool $record): string => json_encode($record->input_schema, JSON_PRETTY_PRINT)
                            )
                            ->fontFamily('mono')
                            ->copyable(),
                    ]),

                Infolists\Components\Section::make('Settings')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('settings')
                            ->label('')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Tool $record): bool => ! empty($record->settings)),

                Infolists\Components\Section::make('Usage Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('usage_stats.total_runs')
                            ->label('Total Runs')
                            ->formatStateUsing(fn (Tool $record): string => (string) $record->getUsageStats()['total_runs']
                            ),
                        Infolists\Components\TextEntry::make('usage_stats.success_rate')
                            ->label('Success Rate')
                            ->formatStateUsing(fn (Tool $record): string => $record->getUsageStats()['success_rate'].'%'
                            ),
                        Infolists\Components\TextEntry::make('usage_stats.avg_execution_time')
                            ->label('Avg Execution Time')
                            ->formatStateUsing(fn (Tool $record): string => $record->getUsageStats()['avg_execution_time'].' ms'
                            ),
                    ])
                    ->columns(3),
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
            'index' => Pages\ListTools::route('/'),
            'create' => Pages\CreateTool::route('/create'),
            'view' => Pages\ViewTool::route('/{record}'),
            'edit' => Pages\EditTool::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count();
    }

    protected static function buildTestForm(Tool $record): array
    {
        $exampleData = self::generateExampleData($record->input_schema);

        $fields = [
            Forms\Components\Section::make('Test Tool: '.$record->display_name)
                ->description($record->description.($exampleData ? "\n\n**Example data available** - check the Advanced section to auto-fill sample values." : ''))
                ->schema(self::buildFormFieldsFromSchema($record->input_schema))
                ->collapsible(false),
        ];

        // Add a fallback JSON input option
        $fields[] = Forms\Components\Section::make('Advanced')
            ->description('Use raw JSON if the form above doesn\'t meet your needs')
            ->schema([
                Forms\Components\Textarea::make('_raw_json')
                    ->label('Raw JSON Input (Optional)')
                    ->rows(4)
                    ->placeholder($exampleData ? json_encode($exampleData, JSON_PRETTY_PRINT) : '{"param1": "value1", "param2": "value2"}')
                    ->helperText('This will override the form fields above if provided. Copy the placeholder for a quick test.')
                    ->columnSpanFull(),
            ])
            ->collapsible()
            ->collapsed();

        return $fields;
    }

    protected static function buildFormFieldsFromSchema(array $schema): array
    {
        $fields = [];

        // Handle standard JSON schema format
        if (! isset($schema['properties']) || ! is_array($schema['properties'])) {
            return [
                Forms\Components\Placeholder::make('no_schema')
                    ->label('No Parameters')
                    ->content('This tool doesn\'t require any input parameters.'),
            ];
        }

        $required = $schema['required'] ?? [];

        foreach ($schema['properties'] as $name => $property) {
            $type = $property['type'] ?? 'string';
            $description = $property['description'] ?? '';
            $default = $property['default'] ?? null;
            $enum = $property['enum'] ?? null;
            $isRequired = in_array($name, $required);

            $field = match ($type) {
                'boolean' => Forms\Components\Toggle::make($name)
                    ->label(ucwords(str_replace('_', ' ', $name)))
                    ->helperText($description)
                    ->default($default !== null ? (bool) $default : false),

                'number' => Forms\Components\TextInput::make($name)
                    ->label(ucwords(str_replace('_', ' ', $name)))
                    ->numeric()
                    ->helperText($description)
                    ->default($default)
                    ->placeholder($enum ? 'e.g., '.implode(', ', array_slice($enum, 0, 3)) : 'Enter a number'),

                'array' => Forms\Components\TagsInput::make($name)
                    ->label(ucwords(str_replace('_', ' ', $name)))
                    ->helperText($description.($default ? ' (Default: '.json_encode($default).')' : ''))
                    ->placeholder('Enter values and press Enter'),

                default => $enum
                    ? Forms\Components\Select::make($name)
                        ->label(ucwords(str_replace('_', ' ', $name)))
                        ->options(array_combine($enum, $enum))
                        ->helperText($description)
                        ->default($default)
                        ->placeholder('Select an option')
                    : Forms\Components\TextInput::make($name)
                        ->label(ucwords(str_replace('_', ' ', $name)))
                        ->helperText($description)
                        ->default($default)
                        ->placeholder($enum ? 'e.g., '.implode(', ', array_slice($enum, 0, 3)) : 'Enter text'),
            };

            if ($isRequired) {
                $field = $field->required();
            }

            $fields[] = $field;
        }

        if (empty($fields)) {
            $fields[] = Forms\Components\Placeholder::make('no_params')
                ->label('No Parameters Required')
                ->content('This tool doesn\'t require any input parameters.');
        }

        return $fields;
    }

    protected static function generateExampleData(array $schema): ?array
    {
        if (! isset($schema['properties']) || ! is_array($schema['properties'])) {
            return null;
        }

        $exampleData = [];
        foreach ($schema['properties'] as $name => $property) {
            $type = $property['type'] ?? 'string';
            $enum = $property['enum'] ?? null;
            $default = $property['default'] ?? null;

            if ($default !== null) {
                $exampleData[$name] = $default;

                continue;
            }

            $exampleData[$name] = match ($type) {
                'boolean' => true,
                'number' => $enum ? $enum[0] : 42,
                'array' => ['example1', 'example2'],
                default => $enum ? $enum[0] : match ($name) {
                    'message' => 'Hello, World!',
                    'operation' => 'add',
                    'a', 'b' => 10,
                    'timezone' => 'UTC',
                    'format' => 'Y-m-d H:i:s',
                    default => 'example_value'
                }
            };
        }

        return empty($exampleData) ? null : $exampleData;
    }
}
