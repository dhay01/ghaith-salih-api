<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkshopResource;
use App\Models\Workshop;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkshopController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $workshops = Workshop::published()
            ->orderBy('starts_on')
            ->get();

        return WorkshopResource::collection($workshops);
    }

    public function show(Workshop $workshop): WorkshopResource
    {
        abort_unless($workshop->is_published, 404);

        return new WorkshopResource($workshop);
    }
}
