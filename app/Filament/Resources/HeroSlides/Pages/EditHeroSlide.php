<?php

namespace App\Filament\Resources\HeroSlides\Pages;

use App\Filament\Concerns\TranslatesFormData;
use App\Filament\Resources\HeroSlides\HeroSlideResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHeroSlide extends EditRecord
{
    use TranslatesFormData;

    protected static string $resource = HeroSlideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
