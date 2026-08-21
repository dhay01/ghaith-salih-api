<?php

namespace App\Filament\Resources\Photos\Pages;

use App\Filament\Concerns\TranslatesFormData;
use App\Filament\Resources\Photos\PhotoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPhoto extends EditRecord
{
    use TranslatesFormData;

    protected static string $resource = PhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
