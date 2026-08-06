<?php

namespace Database\Seeders;

use App\Models\Workshop;
use Illuminate\Database\Seeder;

/**
 * Mirrors the schedule the frontend currently ships in
 * `src/data/courses.js`, so the SPA can switch to the API with no visible
 * change. Arabic is intentionally left unset — content is authored in English
 * for now, and the fallback locale fills the gap.
 */
class WorkshopSeeder extends Seeder
{
    public function run(): void
    {
        $workshops = [
            [
                'slug' => 'language-of-light',
                'title' => 'The Language of Light',
                'mode' => '2-day studio intensive',
                'level' => 'Intermediate',
                'location' => 'Studio, Baghdad',
                'price_minor' => 48000,
                'seats_total' => 10,
                'starts_on' => '2026-03-14',
                'ends_on' => '2026-03-15',
                'overview' => 'Two days on the one thing that separates a snapshot from a photograph: light. We work entirely with continuous and window light in the studio — shaping it, reading it, and learning to wait for it.',
                'outcomes' => [
                    'Reading direction, quality and colour of light',
                    'Shaping window light with flags and scrims',
                    'Metering for mood, not just exposure',
                    'Building a frame around a single light source',
                    'Directing a subject without over-directing',
                    'A calm, repeatable edit that protects the light',
                ],
                'syllabus' => [
                    ['day' => 'Day 01', 'title' => 'Seeing light', 'slots' => [
                        ['time' => '09:00', 'what' => 'Intro · the vocabulary of light'],
                        ['time' => '11:00', 'what' => 'Window-light shaping demo'],
                        ['time' => '14:00', 'what' => 'Hands-on: one light, one subject'],
                        ['time' => '16:30', 'what' => 'Group review & feedback'],
                    ]],
                    ['day' => 'Day 02', 'title' => 'Shaping & finishing', 'slots' => [
                        ['time' => '09:00', 'what' => 'Metering for mood workshop'],
                        ['time' => '11:30', 'what' => 'Personal shoot · your concept'],
                        ['time' => '14:30', 'what' => 'Editing the light in post'],
                        ['time' => '16:30', 'what' => 'Final critique · certificates'],
                    ]],
                ],
                'included' => [
                    'Studio time & all lighting gear',
                    'A professional model for both days',
                    'Lunch & coffee',
                    'Edited selects from your shoot',
                    'Certificate of completion',
                ],
                'prerequisites' => [
                    'Your own camera with manual mode',
                    'One fast prime lens (35 / 50 / 85)',
                    'A laptop with your editing software',
                    'Comfort with basic exposure',
                ],
                'faqs' => [
                    ['q' => 'Do I need my own gear?', 'a' => 'Yes — bring a camera with manual mode and one fast prime. All studio lighting, modifiers and the model are provided.'],
                    ['q' => 'Is it beginner friendly?', 'a' => 'It’s pitched at intermediate level — you should be comfortable with exposure and manual mode.'],
                    ['q' => 'What’s the refund policy?', 'a' => 'Full refund up to 14 days before. Within 14 days, your seat can be transferred.'],
                ],
            ],
            [
                'slug' => 'architecture-interiors',
                'title' => 'Architecture & Interiors',
                'mode' => '1-day city walk',
                'level' => 'Intermediate',
                'location' => 'Samarra',
                'price_minor' => 15000,
                'seats_total' => 16,
                'starts_on' => '2026-03-28',
            ],
            [
                'slug' => 'astro-night-panorama',
                'title' => 'Astro & Night Panorama',
                'mode' => 'Weekend field workshop',
                'level' => 'All levels',
                'location' => 'Halgurd',
                'price_minor' => 36000,
                'seats_total' => 12,
                'starts_on' => '2026-04-05',
                'ends_on' => '2026-04-06',
            ],
            [
                'slug' => 'portraits-natural-light',
                'title' => 'Portraits in Natural Light',
                'mode' => 'Weekend workshop',
                'level' => 'Beginner',
                'location' => 'Erbil',
                'price_minor' => 32000,
                'seats_total' => 14,
                'starts_on' => '2026-05-09',
                'ends_on' => '2026-05-10',
            ],
            [
                'slug' => 'gigapixel-stitching',
                'title' => 'Gigapixel & Stitching',
                'mode' => 'Live online masterclass',
                'level' => 'Advanced',
                'location' => 'Online',
                'price_minor' => 18000,
                'seats_total' => 40,
                'starts_on' => '2026-06-20',
            ],
            [
                'slug' => 'annual-training-camp',
                'title' => 'The Annual Training Camp',
                'mode' => '1-month intensive program',
                'level' => 'All levels',
                'location' => 'Baghdad',
                'price_minor' => 140000,
                'seats_total' => 15,
                'starts_on' => '2026-08-01',
                'ends_on' => '2026-08-30',
            ],
        ];

        foreach ($workshops as $data) {
            Workshop::updateOrCreate(
                ['slug' => $data['slug']],
                [...$data, 'is_published' => true, 'accepts_reservations' => true],
            );
        }
    }
}
