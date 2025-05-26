<?php

namespace App\Filament\Resources;

use App\Filament\Resources\McpToolRunLogResource\Pages;
use App\Models\McpToolRunLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class McpToolRunLogResource extends Resource
{
    protected static ?string $model = McpToolRunLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'MCP Tools';

    protected static ?string $recordTitleAttribute = 'tool_name';

    protected static ?string $label = 'Tool Run Log';

    protected static ?string $pluralLabel = 'Tool Run Logs';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('tool_name')
                    ->disabled()
                    ->required(),
                Forms\Components\DateTimePicker::make('created_at')
                    ->label('Timestamp')
                    ->disabled(),
                Forms\Components\Toggle::make('is_error')
                    ->disabled(),
                Forms\Components\TextInput::make('error_type')
                    ->disabled(),
                Forms\Components\Textarea::make('error_message')
                    ->columnSpanFull()
                    ->disabled(),
                Forms\Components\TextInput::make('request_ip')
                    ->disabled(),
                Forms\Components\TextInput::make('execution_time_ms')
                    ->label('Execution Time (ms)')
                    ->disabled()
                    ->numeric(),
                Forms\Components\Textarea::make('formatted_tool_input')
                    ->label('Tool Input (JSON)')
                    ->columnSpanFull()
                    ->disabled(),
                Forms\Components\Textarea::make('formatted_tool_output')
                    ->label('Tool Output (JSON)')
                    ->columnSpanFull()
                    ->disabled(),
                Forms\Components\Textarea::make('formatted_raw_request_payload')
                    ->label('Raw Request (JSON)')
                    ->columnSpanFull()
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tool_name')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\IconColumn::make('is_error')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('error_type')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('danger')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('execution_time_ms')
                    ->label('Exec Time (ms)')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => $state ? number_format((float) $state, 2) : '—'),
                Tables\Columns\TextColumn::make('request_ip')
                    ->label('IP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_error')
                    ->label('Error Status')
                    ->placeholder('All requests')
                    ->trueLabel('Errors only')
                    ->falseLabel('Successful only'),
                Tables\Filters\SelectFilter::make('tool_name')
                    ->options(function () {
                        return McpToolRunLog::distinct('tool_name')
                            ->pluck('tool_name', 'tool_name')
                            ->toArray();
                    }),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Request Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('tool_name')
                            ->badge(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Timestamp')
                            ->dateTime(),
                        Infolists\Components\IconEntry::make('is_error')
                            ->boolean()
                            ->trueIcon('heroicon-o-x-circle')
                            ->falseIcon('heroicon-o-check-circle')
                            ->trueColor('danger')
                            ->falseColor('success'),
                        Infolists\Components\TextEntry::make('execution_time_ms')
                            ->label('Execution Time')
                            ->formatStateUsing(fn (string $state): string => number_format((float) $state, 2).' ms'),
                        Infolists\Components\TextEntry::make('request_ip')
                            ->label('IP Address'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Error Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('error_type')
                            ->color('danger'),
                        Infolists\Components\TextEntry::make('error_message')
                            ->columnSpanFull()
                            ->color('danger'),
                    ])
                    ->visible(fn (McpToolRunLog $record): bool => $record->is_error),

                Infolists\Components\Section::make('Tool Input')
                    ->schema([
                        Infolists\Components\TextEntry::make('formatted_tool_input')
                            ->label('')
                            ->columnSpanFull()
                            ->copyable(),
                    ]),

                Infolists\Components\Section::make('Tool Output')
                    ->schema([
                        Infolists\Components\TextEntry::make('formatted_tool_output')
                            ->label('')
                            ->columnSpanFull()
                            ->copyable(),
                    ])
                    ->visible(fn (McpToolRunLog $record): bool => ! $record->is_error && $record->tool_output),

                Infolists\Components\Section::make('Raw Request')
                    ->schema([
                        Infolists\Components\TextEntry::make('formatted_raw_request_payload')
                            ->label('')
                            ->columnSpanFull()
                            ->copyable(),
                    ])
                    ->collapsible(),
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
            'view' => Pages\ViewMcpToolRunLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}
