<?php

namespace App\Filament\Resources\Launchers;

use App\Filament\Resources\Launchers\Pages\EditLauncher;
use App\Filament\Resources\Launchers\Pages\ListLaunchers;
use App\Filament\Resources\Launchers\Schemas\LauncherForm;
use App\Filament\Resources\Launchers\Tables\LaunchersTable;
use App\Models\Launcher;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LauncherResource extends Resource
{
    protected static ?string $model = Launcher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?string $navigationLabel = 'Workflow templates';

    protected static ?string $modelLabel = 'workflow template';

    protected static ?string $pluralModelLabel = 'workflow templates';

    public static function form(Schema $schema): Schema
    {
        return LauncherForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LaunchersTable::configure($table);
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
            'index' => ListLaunchers::route('/'),
            'edit' => EditLauncher::route('/{record}/edit'),
        ];
    }
}
