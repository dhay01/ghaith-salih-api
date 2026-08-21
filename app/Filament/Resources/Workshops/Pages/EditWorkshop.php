<?php

namespace App\Filament\Resources\Workshops\Pages;

use App\Filament\Concerns\TranslatesFormData;
use App\Filament\Resources\Workshops\WorkshopResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkshop extends EditRecord
{
    use TranslatesFormData;

    protected static string $resource = WorkshopResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
