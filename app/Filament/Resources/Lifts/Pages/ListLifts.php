<?php

declare(strict_types=1);

namespace App\Filament\Resources\Lifts\Pages;

use App\Filament\Resources\Lifts\LiftResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

class ListLifts extends ListRecords
{
    protected static string $resource = LiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
	        Action::make('refresh')
	            ->icon(Heroicon::ArrowPath)
	            ->action(function () {
		            Artisan::call('app:parse-lifts-command');
	            })
        ];
    }
}
