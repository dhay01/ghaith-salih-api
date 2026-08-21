<?php

namespace Database\Seeders;

use App\Models\Workshop;
use Illuminate\Database\Seeder;

/**
 * The archive strip on the courses page. These were a hardcoded array in the
 * frontend; as real workshop rows they simply sort behind today's date, so the
 * archive fills itself as upcoming workshops age out.
 */
class PastWorkshopSeeder extends Seeder
{
    protected string $uploads = '/Users/dhaysalih/Desktop/photographer-portfolio/public/uploads';

    protected array $files = [
        'ranges' => '589903741_18554882992055421_5603779562178377351_n.jpg',
        'stars' => '608189925_18557068639055421_3317370782112853283_n.jpg',
        'valley' => 'images.jpeg',
        'portraitStudy' => 'images (3).jpeg',
        'arch' => '651508649_18577811014055421_4024825212185473190_n.jpg',
        'assignment' => 'images (1).jpeg',
    ];

    public function run(): void
    {
        $rows = [
            ['winter-light-expedition', 'Winter Light Expedition', 'Zagros', '2025-02-10', '14 photographers', 'ranges'],
            ['night-sky-bootcamp', 'Night Sky Bootcamp', 'Halgurd', '2024-09-12', '10 photographers', 'stars'],
            ['first-annual-camp', 'The First Annual Camp', 'Baghdad', '2024-08-05', '15 photographers', 'valley'],
            ['studio-portrait-lab', 'Studio Portrait Lab', 'Baghdad', '2024-04-18', '12 photographers', 'portraitStudy'],
            ['old-city-architecture-walk', 'Old City Architecture Walk', 'Samarra', '2024-03-22', '16 photographers', 'arch'],
            ['sony-apu-video-workshop', 'Sony × APU Video Workshop', 'Baghdad', '2023-11-14', '40 photographers', 'assignment'],
        ];

        foreach ($rows as [$slug, $title, $location, $date, $attendees, $file]) {
            $workshop = Workshop::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => ['en' => $title],
                    'location' => ['en' => $location],
                    'mode' => ['en' => 'Field workshop'],
                    'level' => ['en' => 'All levels'],
                    'attendees' => ['en' => $attendees],
                    'starts_on' => $date,
                    'seats_total' => 0,
                    'is_published' => true,
                    // Closed: the archive is a record, not a sales surface.
                    'accepts_reservations' => false,
                ],
            );

            if (! $workshop->getFirstMedia('image')) {
                $path = $this->uploads.'/'.($this->files[$file] ?? '');

                if (is_file($path)) {
                    $workshop->addMedia($path)->preservingOriginal()->toMediaCollection('image');
                }
            }
        }
    }
}
