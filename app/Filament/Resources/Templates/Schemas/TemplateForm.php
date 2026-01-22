<?php

namespace App\Filament\Resources\Templates\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Textarea::make('html_content')
                    ->label('Codice HTML Template')
                    ->required()
                    ->rows(20)
                    ->helperText('Inserisci il codice HTML del template. Usa {{body}} per indicare dove verrà inserito il contenuto del messaggio. Altri placeholders: {{name}}, {{email}}, {{unsubscribe_url}}')
                    ->placeholder('<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    <div style="max-width: 600px; margin: 0 auto;">
        {{body}}
    </div>
</body>
</html>'),
            ]);
    }
}
