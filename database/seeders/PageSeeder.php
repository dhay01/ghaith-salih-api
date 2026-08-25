<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/** Ports the page headings and section copy that lived in the Vue templates. */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'home' => [
                'eyebrow' => null,
                'title' => null,
                'intro' => '**Landscape, panorama & gigapixel** photography — wide, patient frames of a world most people pass without looking.',
                'sections' => [
                    [
                        'key' => 'meta',
                        'body' => "Based in Baghdad, Iraq\nAvailable worldwide\nEst. 2013",
                        'note' => 'Trusted by 100+ clients',
                    ],
                    [
                        'key' => 'clients',
                        'note' => 'Trusted by 100+ clients',
                        'items' => [
                            ['label' => 'VOGUE'],
                            ['label' => '◆ atelier'],
                            ['label' => 'MONO®'],
                            ['label' => 'hasselblad'],
                            ['label' => 'NORD/'],
                            ['label' => '◎ lumen'],
                            ['label' => 'KINFOLK'],
                        ],
                    ],
                    [
                        'key' => 'stats',
                        'items' => [
                            ['value' => '12', 'label' => "Years behind\nthe lens"],
                            ['value' => '52K', 'label' => "Instagram\ncommunity"],
                            ['value' => '5', 'label' => "Solo\nexhibitions"],
                        ],
                    ],
                    [
                        'key' => 'about',
                        'eyebrow' => '(01) — About',
                        'heading' => 'I don’t just take photos — I capture the <em>vast, quiet stillness</em> of a landscape in the one moment its light will never <em>repeat</em>.',
                        'body' => 'For more than twelve years I’ve worked across open landscapes and wide horizons — building panoramas and gigapixel frames stitched from hundreds of exposures. Based in Baghdad, shooting worldwide, and teaching the craft along the way.',
                    ],
                    [
                        'key' => 'work',
                        'eyebrow' => '(02) — Selected work',
                        'heading' => 'Featured galleries',
                    ],
                    [
                        'key' => 'learn',
                        'eyebrow' => '(03) — Learn',
                        'heading' => 'Courses & workshops',
                        'body' => 'Seven years of teaching — hundreds trained on the ground, thousands online. Once a year we run a month-long camp on shooting, teamwork and winning clients.',
                    ],
                    [
                        'key' => 'shop',
                        'eyebrow' => '(04) — Shop',
                        'heading' => 'wear the work',
                        'note' => 'Coming soon',
                        'body' => 'A small first drop — clean, heavyweight sweatshirts with the studio logo and a few minimal prints. Landing this winter.',
                        'items' => [
                            ['label' => 'Studio Logo Crew', 'value' => 'Heavyweight cotton', 'note' => 'sweatshirt · logo'],
                            ['label' => 'Everyday Hoodie', 'value' => 'Brushed fleece', 'note' => 'hoodie · logo'],
                            ['label' => '“Through My Lens” Crew', 'value' => 'Minimal text print', 'note' => 'sweatshirt · simple print'],
                        ],
                    ],
                ],
            ],

            'work' => [
                'eyebrow' => 'Portfolio',
                'title' => 'the work',
                'intro' => null,
                'sections' => [],
            ],

            'blog' => [
                'eyebrow' => 'Journal',
                'title' => "notes from\nthe field",
                'intro' => 'Behind-the-scenes stories, technique breakdowns, and gear notes from the road — written between shoots.',
                'sections' => [],
            ],

            'courses' => [
                'eyebrow' => 'Learn',
                'title' => "courses &\nworkshops",
                'intro' => 'Seven years of teaching — hundreds on the ground, thousands online. Pick a date below to see details and reserve your seat.',
                'sections' => [],
            ],

            'about' => [
                'eyebrow' => '(00) — About',
                'title' => null,
                'intro' => null,
                'sections' => [],
            ],
        ];

        foreach ($pages as $key => $row) {
            $page = Page::firstOrNew(['key' => $key]);
            $page->sections = $row['sections'];

            foreach (['eyebrow', 'title', 'intro'] as $column) {
                if ($row[$column] !== null) {
                    $page->setTranslation($column, 'en', $row[$column]);
                }
            }

            $page->save();
        }
    }
}
