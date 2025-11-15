<?php

namespace App\Filament\Resources\Lifts\Schemas;

use App\Enums\Lift\Status;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentColor;

class LiftForm
{
	public static function configure(Schema $schema): Schema
	{
		return $schema
			->components(
				Flex::make([
					Section::make([
						TextInput::make('name')
							->required(),
						TextInput::make('raise_time')
							->numeric(),
						TextInput::make('length')
							->numeric(),
						KeyValue::make('data')
					]),
					Section::make([
						Toggle::make('is_active')
							->offColor('danger')
							->onColor('success')
					])->grow(false),
				])->columnSpanFull()
			);
	}
}
