<?php

namespace Database\Seeders;

use App\Models\AboutPage;
use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Photo;
use App\Models\Post;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Ports the frontend's former src/data/*.js verbatim, so switching the SPA over to
 * the API is not supposed to change a single pixel.
 *
 * Images are pulled from the portfolio's public/uploads. Five of those files were
 * never delivered (they exceeded the design API's transfer cap), so any photo whose
 * file is absent seeds without media and renders as a labelled placeholder — the
 * same behaviour the static site already had.
 */
class ContentSeeder extends Seeder
{
    /** Where the original upload bundle still lives. */
    protected string $uploads = '/Users/dhaysalih/Desktop/photographer-portfolio/public/uploads';

    /** photos.js handle => original filename. */
    protected array $files = [
        'ranges' => '589903741_18554882992055421_5603779562178377351_n.jpg',
        'stars' => '608189925_18557068639055421_3317370782112853283_n.jpg',
        'valley' => 'images.jpeg',
        'assignment' => 'images (1).jpeg',
        'muqarnas' => 'images (2).jpeg',
        'portraitStudy' => 'images (3).jpeg',
        'devotion' => '651054829_18577811026055421_4883273883445382392_n.jpg',
        'arch' => '651508649_18577811014055421_4024825212185473190_n.jpg',
        'herder' => 'Screenshot 2026-08-02 at 11.07.18 AM.png',
        'frameA' => 'Screenshot 2026-07-24 at 4.09.26 PM.png',
        'frameB' => 'Screenshot 2026-07-24 at 4.22.16 PM.png',
        'textureA' => '8420b80f332b4448f6fe0880018ce2a1.webp',
        'textureB' => 'dc845662fa38f0aaf1a6293c9053a935.webp',
    ];

    public function run(): void
    {
        $this->seedSite();
        $work = $this->seedWorkCategories();
        $this->seedPhotos($work);
        $this->seedHero();
        $this->seedPosts();
        $this->seedAbout();
    }

    protected function path(?string $handle): ?string
    {
        if (! $handle || ! isset($this->files[$handle])) {
            return null;
        }

        $full = $this->uploads.'/'.$this->files[$handle];

        return is_file($full) ? $full : null;
    }

    protected function attach(object $model, ?string $handle, string $collection = 'image'): void
    {
        $path = $this->path($handle);

        if (! $path) {
            return;
        }

        $model->addMedia($path)->preservingOriginal()->toMediaCollection($collection);
    }

    protected function seedSite(): void
    {
        $site = SiteSetting::current();
        $site->fill([
            'name' => 'ghaith salih',
            'email' => 'creator@ghaithsalih.com',
            'phone' => '+964 770 531 0152',
            'phone_href' => '+9647705310152',
            'socials' => [
                ['label' => 'Instagram', 'href' => 'https://www.instagram.com/ghaith_salih/'],
                ['label' => 'Behance', 'href' => '#'],
                ['label' => 'Vimeo', 'href' => '#'],
            ],
        ]);
        $site->setTranslation('tagline', 'en', 'through my lens');
        $site->setTranslation('studio', 'en', 'Baghdad, Iraq');
        $site->save();
    }

    /** @return array<string, Category> */
    protected function seedWorkCategories(): array
    {
        // grid_span / grid_ratio drive the home page showcase; only the four
        // categories it features carry them.
        $rows = [
            ['slug' => 'architecture', 'name' => 'Architecture', 'span' => 7, 'ratio' => '16/11', 'cover' => 'arch'],
            ['slug' => 'landscape', 'name' => 'Landscape', 'span' => 5, 'ratio' => null, 'cover' => 'ranges'],
            ['slug' => 'portrait', 'name' => 'Portrait', 'span' => 5, 'ratio' => '4/5', 'cover' => 'herder'],
            ['slug' => 'commercial', 'name' => 'Commercial', 'span' => 7, 'ratio' => null, 'cover' => 'valley'],
            ['slug' => 'panorama', 'name' => 'Panorama', 'span' => null, 'ratio' => null, 'cover' => 'stars'],
            ['slug' => 'gigapixel', 'name' => 'Gigapixel', 'span' => null, 'ratio' => null, 'cover' => null],
        ];

        $made = [];

        foreach ($rows as $i => $row) {
            $category = Category::updateOrCreate(
                ['type' => Category::TYPE_WORK, 'slug' => $row['slug']],
                [
                    'name' => ['en' => $row['name']],
                    'position' => $i + 1,
                    'grid_span' => $row['span'],
                    'grid_ratio' => $row['ratio'],
                ],
            );

            if (! $category->getFirstMedia('image')) {
                $this->attach($category, $row['cover']);
            }

            $made[$row['slug']] = $category;
        }

        return $made;
    }

    /** @param array<string, Category> $categories */
    protected function seedPhotos(array $categories): void
    {
        $rows = [
            ['w1', 'landscape', 'Misty Ranges', 'Zagros, Iraq', 'Sigma 14mm f/1.8', '16/10', 'ranges', false],
            ['w2', 'panorama', 'A Thousand Stars', 'Halgurd, Iraq', 'Sigma 14mm f/1.8', '4/5', 'stars', true],
            ['w3', 'panorama', 'The White Valley', 'Sakran, Iraq', 'Sigma 14mm f/1.8', '1/1', 'valley', true],
            ['w4', 'commercial', 'On Assignment', 'Kurdistan', 'Sigma 70-200mm f/2.8', '3/2', 'assignment', false],
            ['w5', 'architecture', 'Muqarnas', 'Kadhimiya', 'Sigma 14mm f/1.8', '3/2', 'muqarnas', true],
            ['w6', 'portrait', 'Study in Grey', 'Studio, Baghdad', 'Sigma 105mm Macro', '1/1', 'portraitStudy', false],
            ['w7', 'portrait', 'Devotion', 'Karbala', 'Sigma 105mm Macro', '3/2', 'devotion', false],
            ['w8', 'architecture', 'Through the Arch', 'Samarra', 'Sigma 14mm f/1.8', '2/3', 'arch', true],
            ['w9', 'portrait', 'The Herder', 'Al-Jazira', 'Sigma 70-200mm f/2.8', '3/2', 'herder', false],
            ['w10', 'landscape', 'Dunes at Dusk', 'Western Desert', 'Sigma 70-200mm f/2.8', '4/5', null, false],
            ['w11', 'gigapixel', 'City Panorama', 'Baghdad', 'Sigma 105mm Macro', '21/9', null, true],
            ['w12', 'commercial', 'Product Study', 'Studio', 'Sigma 105mm Macro', '1/1', null, false],
            ['w13', 'landscape', 'River Bend', 'Tigris', 'Sigma 14mm f/1.8', '3/2', null, false],
            ['w14', 'architecture', 'Spiral Minaret', 'Samarra', 'Sigma 70-200mm f/2.8', '2/3', null, false],
            ['w15', 'gigapixel', 'Mountain Stitch', 'Zagros', 'Sigma 14mm f/1.8', '21/9', null, true],
            ['w16', 'portrait', 'Elder', 'Baghdad', 'Sigma 105mm Macro', '4/5', null, false],
            ['w17', 'commercial', 'Editorial Set', 'Studio', 'Sigma 70-200mm f/2.8', '3/2', null, false],
            ['w18', 'panorama', 'Coastline', 'Basra', 'Sigma 14mm f/1.8', '21/9', null, true],
        ];

        foreach ($rows as $i => [$slug, $cat, $title, $loc, $gear, $ratio, $file, $zoom]) {
            $photo = Photo::updateOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $categories[$cat]->id,
                    'title' => ['en' => $title],
                    'location' => ['en' => $loc],
                    'gear' => ['en' => $gear],
                    'ratio' => $ratio,
                    'is_zoomable' => $zoom,
                    'position' => $i + 1,
                    'is_published' => true,
                ],
            );

            if (! $photo->getFirstMedia('image')) {
                $this->attach($photo, $file);
            }
        }
    }

    protected function seedHero(): void
    {
        $slides = [
            ['ranges', 'Misty mountain ranges at dawn'],
            ['stars', 'Night sky over Halgurd'],
            ['valley', 'The white valley at Sakran'],
            ['assignment', 'On assignment in Kurdistan'],
            ['devotion', 'Documentary frame from Karbala'],
        ];

        foreach ($slides as $i => [$file, $alt]) {
            $slide = HeroSlide::updateOrCreate(
                ['position' => $i + 1],
                ['alt' => ['en' => $alt], 'is_published' => true],
            );

            if (! $slide->getFirstMedia('image')) {
                $this->attach($slide, $file);
            }
        }
    }

    protected function seedPosts(): void
    {
        $categories = [];

        foreach (['Behind the scenes', 'Tips', 'Gear', 'Tutorial', 'Craft', 'Journal'] as $i => $name) {
            $slug = str($name)->slug()->toString();
            $categories[$name] = Category::updateOrCreate(
                ['type' => Category::TYPE_POST, 'slug' => $slug],
                ['name' => ['en' => $name], 'position' => $i + 1],
            );
        }

        $featuredBody = [
            ['type' => 'text', 'paragraphs' => [
                'I’d seen the frame in my head for two years before I made it: a ridge of dark pines dissolving into valley fog, the first sun just catching the top of the range. The problem with a picture that lives in your head is that the world rarely agrees to build it for you.',
                'So I went back. Three mornings, the same trailhead, the same forty-minute climb in the dark to be set up before the light. The first two mornings gave me nothing — clear skies, no fog, flat light. Photography outdoors is mostly the patience to be wrong on schedule.',
            ]],
            ['type' => 'heading', 'text' => 'Reading the night before'],
            ['type' => 'text', 'paragraphs' => [
                'Fog isn’t luck — it’s a forecast you learn to read. I watch dew point and overnight temperature: when they converge and the wind drops below a whisper, the valley fills. The third night, the numbers finally lined up, and I barely slept.',
            ]],
            ['type' => 'figure', 'path' => $this->copyToPublic('valley'), 'ratio' => '3 / 2', 'caption' => 'Blue hour on the approach — 04:52, before the fog lifted.'],
            ['type' => 'quote', 'text' => 'The best frames arrive when you stop chasing them — you just have to be there, ready, when they do.'],
            ['type' => 'text', 'paragraphs' => [
                'When it came, it lasted maybe eight minutes. The fog sat exactly where I’d imagined, the sun broke the ridge, and for a moment the whole valley glowed the colour of cold steel. I made eleven frames. One of them was the picture I’d carried for two years.',
                'The lesson isn’t about gear or settings — a 14mm at f/8 did the whole job. It’s that the photograph was made on the two mornings I came home empty-handed, not the one that worked. Showing up is the technique.',
            ]],
            ['type' => 'figure', 'path' => $this->copyToPublic('stars'), 'ratio' => '16 / 9', 'caption' => 'The frame I came for — 05:38, eight minutes of light.'],
        ];

        $rows = [
            [
                'slug' => 'chasing-fog-zagros',
                'cat' => 'Behind the scenes',
                'date' => '2026-02-01',
                'read' => 8,
                'file' => 'ranges',
                'featured' => true,
                'title' => 'Chasing fog in the Zagros — three cold mornings for one frame',
                'excerpt' => 'The light I wanted only exists for about eight minutes after sunrise, when the valley fog hasn’t burned off yet. Here’s what it took to be standing in the right place when it happened.',
                'standfirst' => 'The light I wanted only exists for about eight minutes after sunrise. Here’s what it took to be standing in the right place when it happened.',
                'tags' => ['Landscape', 'Fieldcraft', 'Zagros'],
                'body' => $featuredBody,
            ],
            ['slug' => 'milky-way-halgurd', 'cat' => 'Tips', 'date' => '2026-01-15', 'read' => 6, 'file' => 'stars', 'featured' => false, 'title' => 'Shooting the Milky Way over Halgurd', 'excerpt' => 'Planning, focus, and stacking for a clean astro panorama in sub-zero cold.'],
            ['slug' => 'sigma-bf', 'cat' => 'Gear', 'date' => '2026-01-05', 'read' => 5, 'file' => null, 'featured' => false, 'title' => 'Why I switched to the Sigma BF', 'excerpt' => 'A stripped-back body changed how I shoot landscapes. What I gained, what I gave up.'],
            ['slug' => 'two-gigapixel-panorama', 'cat' => 'Tutorial', 'date' => '2025-12-15', 'read' => 12, 'file' => 'valley', 'featured' => false, 'title' => 'Stitching a two-gigapixel panorama', 'excerpt' => 'From capture grid to final export — my full workflow for massive stitched frames.'],
            ['slug' => 'portraits-marshlands', 'cat' => 'Behind the scenes', 'date' => '2025-12-05', 'read' => 7, 'file' => 'herder', 'featured' => false, 'title' => 'Portraits of the marshlands', 'excerpt' => 'Three days with the buffalo herders of the south, and the trust a portrait needs.'],
            ['slug' => 'light-in-old-mosques', 'cat' => 'Craft', 'date' => '2025-11-20', 'read' => 6, 'file' => 'arch', 'featured' => false, 'title' => 'Reading light in old mosques', 'excerpt' => 'How I meter and wait for the one shaft of light that makes an interior sing.'],
            ['slug' => 'week-teaching-erbil', 'cat' => 'Journal', 'date' => '2025-11-08', 'read' => 4, 'file' => 'assignment', 'featured' => false, 'title' => 'A week teaching in Erbil', 'excerpt' => 'Notes on running a field workshop, and what students taught me back.'],
        ];

        foreach ($rows as $row) {
            $post = Post::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'category_id' => $categories[$row['cat']]->id,
                    'title' => ['en' => $row['title']],
                    'excerpt' => ['en' => $row['excerpt']],
                    'standfirst' => isset($row['standfirst']) ? ['en' => $row['standfirst']] : null,
                    'body' => $row['body'] ?? [],
                    'tags' => $row['tags'] ?? [],
                    'read_minutes' => $row['read'],
                    'is_featured' => $row['featured'],
                    'is_published' => true,
                    'published_on' => $row['date'],
                ],
            );

            if (! $post->getFirstMedia('image')) {
                $this->attach($post, $row['file']);
            }
        }
    }

    /** Copies a bundled upload onto the public disk for use inside a post body. */
    protected function copyToPublic(string $handle): ?string
    {
        $source = $this->path($handle);

        if (! $source) {
            return null;
        }

        $target = 'posts/'.basename($source);

        if (! Storage::disk('public')->exists($target)) {
            Storage::disk('public')->put($target, file_get_contents($source));
        }

        return $target;
    }

    protected function seedAbout(): void
    {
        $about = AboutPage::current();

        $about->fill([
            'disciplines' => ['Landscape', 'Panorama', 'Gigapixel'],
            'journey_paragraphs' => [
                'It started with a borrowed camera and a country full of horizon. I studied Film Directing at the College of Fine Arts — but it was the still frame, not the moving one, that held me.',
                'Over twelve years I turned toward the land: wide panoramas and gigapixel frames stitched from hundreds of exposures, each one a patient argument for slowing down. I’ve held 5 solo exhibitions, shown in many group shows, and collected awards along the way.',
                'Teaching came next. For seven years I’ve trained hundreds of photographers on the ground and thousands online — including a Sony workshop on video, held with the Arab Photographers Union. Today more than 52,000 people follow the work on Instagram.',
            ],
            'timeline' => [
                ['year' => '2013', 'what' => 'First steps into professional landscape photography.'],
                ['year' => '2018', 'what' => 'Began teaching workshops on the ground.'],
                ['year' => '2020', 'what' => 'Trained thousands of photographers online.'],
                ['year' => '2026', 'what' => 'Annual month-long training camp in Baghdad.'],
            ],
            'approach' => [
                ['n' => '01', 'title' => 'Observe', 'body' => 'Before the camera comes up, I watch. Every subject has a rhythm — the way they move, pause, and forget I’m there. I wait for that.'],
                ['n' => '02', 'title' => 'Wait', 'body' => 'Light is the one collaborator I can’t rush. I’d rather lose the shot than fake the moment. The best frames arrive when you stop chasing them.'],
                ['n' => '03', 'title' => 'Honor', 'body' => 'In edit, I protect what was real. Grain over gloss, texture over perfection. The goal is never to improve the moment — only to keep it.'],
            ],
            'gear' => [
                ['label' => 'Cameras', 'value' => 'Sigma BF (Sony E-mount)'],
                ['label' => 'Lenses', 'value' => 'Sigma 14mm f/1.8 · 70-200mm f/2.8 · 105mm Macro'],
                ['label' => 'Lighting', 'value' => 'Natural light · golden hour, mostly'],
                ['label' => 'Formats', 'value' => 'Panorama · Gigapixel · Landscape'],
                ['label' => 'Studio', 'value' => 'Baghdad, Iraq — shooting worldwide'],
            ],
        ]);

        $about->setTranslation('hero_title', 'en', 'the person behind the lens');
        $about->setTranslation('hero_intro', 'en', 'I’m **Ghaith Salih** — a landscape, panorama & gigapixel photographer based in Baghdad, with 12+ years chasing wide, honest light.');
        $about->setTranslation('journey_title', 'en', 'From a borrowed camera to a life in light.');
        $about->setTranslation('philosophy_quote', 'en', 'I don’t photograph how things look. I photograph what light forgets to say.');
        $about->setTranslation('philosophy_note', 'en', 'A photograph should feel like a memory you didn’t know you had. I work slow and quiet, keeping the frame honest — no forced smiles, no over-direction. Just presence, patience, and the right light.');
        $about->setTranslation('gear_title', 'en', 'Simple tools, used well. The camera is never the point — but people always ask.');
        $about->save();

        if (! $about->getFirstMedia('hero_image')) {
            $this->attach($about, 'assignment', 'hero_image');
        }

        if (! $about->getFirstMedia('gear_image')) {
            $this->attach($about, 'stars', 'gear_image');
        }
    }
}
