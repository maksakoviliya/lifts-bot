<?php

namespace App\Filament\Resources\Lifts\Pages;

use App\Filament\Resources\Lifts\LiftResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLift extends EditRecord
{
    protected static string $resource = LiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
