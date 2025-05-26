<?php

namespace App\Filament\Resources\McpToolRunLogResource\Pages;

use App\Filament\Resources\McpToolRunLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMcpToolRunLogs extends ListRecords
{
    protected static string $resource = McpToolRunLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
