<?php

namespace App\Filament\Resources\Lifts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LiftsTable
{
	public static function configure(Table $table): Table
	{
		return $table
			->columns([
				TextColumn::make('name')
					->searchable(),
				IconColumn::make('is_active')
					->icon(fn(bool $state): Heroicon => match ($state) {
						true => Heroicon::CheckCircle,
						false => Heroicon::XCircle
					})
					->color(fn(bool $state): string => match ($state) {
						true => 'success',
						false => 'danger'
					})
					->searchable(),
				TextColumn::make('raise_time')
					->numeric()
					->sortable(),
				TextColumn::make('length')
					->numeric()
					->sortable(),
				TextColumn::make('created_at')
					->dateTime('d.m.Y H:i:s')
					->sortable()
					->toggleable(),
				TextColumn::make('updated_at')
					->dateTime('d.m.Y H:i:s')
					->sortable()
					->toggleable(),
			])
			->filters([
				//
			])
			->recordActions([
				EditAction::make(),
			])
			->toolbarActions([
				BulkActionGroup::make([
					DeleteBulkAction::make(),
				]),
			]);
	}
}
