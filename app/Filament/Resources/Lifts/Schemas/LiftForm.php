<?php

declare(strict_types=1);

namespace App\Filament\Resources\Lifts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

final class LiftForm
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
						Hidden::make('enabled_at')->nullable(),
						Hidden::make('enabled_by')->nullable(),
						
						Toggle::make('is_active')
							->offColor('danger')
							->onColor('success'),
						Select::make('status')
							->label('Status')
							->options([
								'enabled' => 'Enabled permanently',
								'disabled' => 'Disabled permanently',
								'auto' => 'Auto',
							])
							->default('auto')
							->reactive()
							->afterStateUpdated(function ($set, $state, $get) {
								$currentUser = Auth::id();

								if ($state === 'enabled') {
									$set('enabled_at', now());
									$set('enabled_by', $currentUser);
									$set('disabled_at', null);
									$set('disabled_by', null);
									$set('is_active', true);
								} elseif ($state === 'disabled') {
									$set('disabled_at', now());
									$set('disabled_by', $currentUser);
									$set('enabled_at', null);
									$set('enabled_by', null);
									$set('is_active', false);
								} else {
									$set('enabled_at', null);
									$set('enabled_by', null);
									$set('disabled_at', null);
									$set('disabled_by', null);
								}
							})
							->dehydrated(false),
						
						DateTimePicker::make('enabled_at')
							->live()
							->visible(fn($get) => !is_null($get('enabled_at'))),

						DateTimePicker::make('disabled_at')
							->live()
							->visible(fn($get) => !is_null($get('disabled_at'))),
					])->grow(false),
				])->columnSpanFull()
			);
	}
}
