<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Concerns\TranslatesFormData;
use App\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    use TranslatesFormData;

    protected static string $resource = PostResource::class;
}
