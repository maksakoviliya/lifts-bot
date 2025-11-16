<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
				TextInput::make('telegram_id'),
				TextInput::make('username'),
				TextInput::make('first_name'),
				TextInput::make('last_name'),
				TextInput::make('usage_count'),
                TextInput::make('password')
                    ->password()
                    ->required(),
            ]);
    }
}
