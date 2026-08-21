<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AboutResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\HeroSlideResource;
use App\Http\Resources\PageResource;
use App\Http\Resources\PhotoResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\SiteResource;
use App\Models\AboutPage;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Page;
use App\Models\Photo;
use App\Models\Post;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Everything the SPA used to hold in src/data/*.js. Each endpoint is deliberately
 * narrow so a page fetches only what it renders, in parallel.
 */
class ContentController extends Controller
{
    public function site(): SiteResource
    {
        return new SiteResource(SiteSetting::current());
    }

    public function page(Page $page): PageResource
    {
        return new PageResource($page);
    }

    public function about(): AboutResource
    {
        return new AboutResource(AboutPage::current());
    }

    public function heroSlides(): AnonymousResourceCollection
    {
        return HeroSlideResource::collection(
            HeroSlide::published()->orderBy('position')->get()
        );
    }

    public function categories(Request $request): AnonymousResourceCollection
    {
        $type = $request->string('type')->toString() ?: Category::TYPE_WORK;

        abort_unless(
            in_array($type, [Category::TYPE_WORK, Category::TYPE_POST], true),
            422,
        );

        $categories = Category::ofType($type)
            ->withCount(['photos' => fn ($q) => $q->where('is_published', true)])
            ->get();

        return CategoryResource::collection($categories);
    }

    public function photos(Request $request): AnonymousResourceCollection
    {
        $photos = Photo::published()
            ->with('category')
            ->when(
                $request->filled('category'),
                fn ($q) => $q->whereRelation('category', 'slug', $request->string('category')),
            )
            ->orderBy('position')
            ->get();

        return PhotoResource::collection($photos);
    }

    public function posts(): AnonymousResourceCollection
    {
        $posts = Post::published()
            ->with('category')
            ->orderByDesc('is_featured')
            ->orderByDesc('published_on')
            ->get();

        return PostResource::collection($posts);
    }

    public function post(Post $post): PostResource
    {
        abort_unless($post->is_published, 404);

        return new PostResource($post->load('category'));
    }
}
