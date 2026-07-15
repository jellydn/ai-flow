<?php

namespace App\Filament\Resources\Launchers\Pages;

use App\Filament\Resources\Launchers\LauncherResource;
use Filament\Resources\Pages\ListRecords;

class ListLaunchers extends ListRecords
{
    protected static string $resource = LauncherResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
