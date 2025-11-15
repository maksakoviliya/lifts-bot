<?php

namespace App\Filament\Resources\Lifts\Pages;

use App\Filament\Resources\Lifts\LiftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLifts extends ListRecords
{
    protected static string $resource = LiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
