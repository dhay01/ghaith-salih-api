<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The logo shares a model with the author portrait but wants none of its sizes.
 *
 * Left to the inherited conversions it would be re-encoded at the photograph
 * widths — 600, 1400 and 2600px — none of which it is ever displayed at, while
 * the size it is actually shown at would not exist.
 */
class SiteLogoTest extends TestCase
{
    use RefreshDatabase;

    /** A real file on disk: a faked upload's temp file is cleaned up too early. */
    protected function png(int $w = 1200, int $h = 400): string
    {
        $path = tempnam(sys_get_temp_dir(), 'logo').'.png';
        $image = imagecreatetruecolor($w, $h);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    protected function withLogo(): SiteSetting
    {
        $site = SiteSetting::current();
        $site->addMedia($this->png())->preservingOriginal()->toMediaCollection('logo');

        return $site->fresh();
    }

    public function test_a_logo_is_resized_for_display(): void
    {
        $media = $this->withLogo()->getFirstMedia('logo');

        $this->assertNotNull($media);
        $this->assertTrue(
            $media->hasGeneratedConversion('logo'),
            'the logo conversion should be generated on upload',
        );

        [$width, $height] = getimagesize($media->getPath('logo'));

        $this->assertLessThanOrEqual(900, $width);
        $this->assertLessThanOrEqual(180, $height);
        // 1200x400 bounded by height, so the width comes down in proportion.
        $this->assertSame(round(1200 / 400, 2), round($width / $height, 2));
    }

    public function test_a_logo_does_not_get_the_photograph_sizes(): void
    {
        $media = $this->withLogo()->getFirstMedia('logo');

        foreach (['thumb', 'preview', 'full'] as $conversion) {
            $this->assertFalse(
                $media->hasGeneratedConversion($conversion),
                "a logo should not be re-encoded at the {$conversion} photograph size",
            );
        }
    }

    public function test_the_author_portrait_still_gets_its_own_sizes(): void
    {
        $site = SiteSetting::current();
        $site->addMedia($this->png(800, 1000))
            ->preservingOriginal()
            ->toMediaCollection('author_photo');

        $media = $site->fresh()->getFirstMedia('author_photo');

        $this->assertTrue(
            $media->hasGeneratedConversion('thumb'),
            'scoping the logo conversion must not disturb the portrait',
        );
    }

    public function test_the_api_serves_the_resized_logo_not_the_upload(): void
    {
        $site = $this->withLogo();
        $media = $site->getFirstMedia('logo');

        $this->assertSame($media->getFullUrl('logo'), $site->logoUrl());
        $this->assertNotSame($media->getFullUrl(), $site->logoUrl());

        $this->getJson('/api/site')
            ->assertSuccessful()
            ->assertJsonPath('data.logo', $media->getFullUrl('logo'));
    }

    public function test_uploading_a_second_logo_replaces_the_first(): void
    {
        $site = $this->withLogo();
        $first = $site->getFirstMedia('logo')->id;

        $site->addMedia($this->png(600, 200))
            ->preservingOriginal()
            ->toMediaCollection('logo');

        $site = $site->fresh();

        // Without singleFile() the second upload is added alongside the first and
        // getFirstMedia keeps returning the original, so replacing a logo looks
        // like it did nothing — and a broken one can never be replaced.
        $this->assertCount(1, $site->getMedia('logo'));
        $this->assertNotSame($first, $site->getFirstMedia('logo')->id);
    }

    public function test_the_api_reports_no_logo_when_none_is_uploaded(): void
    {
        SiteSetting::current();

        $this->getJson('/api/site')->assertSuccessful()->assertJsonPath('data.logo', null);
    }
}
