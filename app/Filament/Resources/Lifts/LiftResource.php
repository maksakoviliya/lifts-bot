<?php

declare(strict_types=1);

namespace App\Filament\Resources\Lifts;

use App\Filament\Resources\Lifts\Pages\CreateLift;
use App\Filament\Resources\Lifts\Pages\EditLift;
use App\Filament\Resources\Lifts\Pages\ListLifts;
use App\Filament\Resources\Lifts\Schemas\LiftForm;
use App\Filament\Resources\Lifts\Tables\LiftsTable;
use App\Models\Lift;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LiftResource extends Resource
{
    protected static ?string $model = Lift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return LiftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LiftsTable::configure($table);
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
            'index' => ListLifts::route('/'),
            'create' => CreateLift::route('/create'),
            'edit' => EditLift::route('/{record}/edit'),
        ];
    }
}
