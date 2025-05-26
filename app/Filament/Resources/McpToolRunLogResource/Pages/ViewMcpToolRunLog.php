<?php

namespace App\Filament\Resources\McpToolRunLogResource\Pages;

use App\Filament\Resources\McpToolRunLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMcpToolRunLog extends ViewRecord
{
    protected static string $resource = McpToolRunLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
