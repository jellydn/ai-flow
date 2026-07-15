<?php

namespace App\Filament\Resources\Launchers\Pages;

use App\Filament\Resources\Launchers\LauncherResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLauncher extends EditRecord
{
    protected static string $resource = LauncherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
