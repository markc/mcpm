<?php

namespace App\Filament\Resources\ToolHandlerResource\Pages;

use App\Filament\Resources\ToolHandlerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditToolHandler extends EditRecord
{
    protected static string $resource = ToolHandlerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
