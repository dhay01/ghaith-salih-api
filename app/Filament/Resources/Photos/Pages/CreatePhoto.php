<?php

namespace App\Filament\Resources\Photos\Pages;

use App\Filament\Concerns\TranslatesFormData;
use App\Filament\Resources\Photos\PhotoResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePhoto extends CreateRecord
{
    use TranslatesFormData;

    protected static string $resource = PhotoResource::class;

    /**
     * Land on the edit page rather than the list.
     *
     * A large original cannot be uploaded until the photo exists, so creating one
     * is always the first half of a two-step job. Filament would fall through to
     * the edit page here anyway, but only because this resource happens to have no
     * view page — adding one later would quietly drop people on a screen with no
     * uploader on it.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
