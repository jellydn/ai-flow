<?php

namespace App\Filament\Resources\Launchers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class LauncherForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required()
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create')
                    ->helperText('Immutable after create — API and URLs depend on this value.'),
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('input_type')
                    ->label('Input type')
                    ->disabled()
                    ->dehydrated(),
                Textarea::make('prompt_template')
                    ->label('Prompt template')
                    ->required()
                    ->rows(12)
                    ->columnSpanFull(),
                Textarea::make('output_schema')
                    ->label('Output schema (JSON)')
                    ->required()
                    ->rules(['json'])
                    ->rows(12)
                    ->columnSpanFull()
                    ->formatStateUsing(function ($state): string {
                        if (is_array($state)) {
                            return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
                        }

                        return is_string($state) ? $state : '{}';
                    })
                    ->dehydrateStateUsing(function (?string $state): array {
                        if ($state === null || trim($state) === '') {
                            throw ValidationException::withMessages([
                                'output_schema' => 'Output schema must be valid JSON.',
                            ]);
                        }

                        $decoded = json_decode($state, true);
                        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                            throw ValidationException::withMessages([
                                'output_schema' => 'Output schema must be a valid JSON object.',
                            ]);
                        }

                        return $decoded;
                    }),
                Toggle::make('active')
                    ->required(),
            ]);
    }
}
