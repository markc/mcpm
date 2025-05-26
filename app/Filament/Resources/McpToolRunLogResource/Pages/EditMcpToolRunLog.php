<?php

namespace App\Filament\Resources\McpToolRunLogResource\Pages;

use App\Filament\Resources\McpToolRunLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMcpToolRunLog extends EditRecord
{
    protected static string $resource = McpToolRunLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
