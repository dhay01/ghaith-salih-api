<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

/**
 * Uploads a file the browser slices into pieces, for originals far too large to
 * survive a single POST.
 *
 * Only usable once the record exists, because each chunk is addressed to a photo
 * id — which is why the ordinary upload field stays alongside it for everyday
 * images.
 */
class LargeFileUpload extends Field
{
    protected string $view = 'filament.forms.components.large-file-upload';

    /**
     * Bytes per chunk, before the server's own limits are taken into account.
     *
     * Every chunk is a separate request, so this size decides how many round trips
     * an upload costs: a 500 MB original is 125 requests at 4 MB and 16 at 32 MB.
     * On a link with half a second of latency that difference is a minute of dead
     * waiting, which is why this is no longer pinned to the smallest value a stock
     * PHP will accept — getChunkSize() clamps it to what the server actually takes.
     */
    protected int $chunkSize = 32 * 1024 * 1024;

    public function chunkSize(int $bytes): static
    {
        $this->chunkSize = $bytes;

        return $this;
    }

    /**
     * The configured size, clamped to what this server will actually accept.
     *
     * A chunk larger than upload_max_filesize or post_max_size is rejected by PHP
     * before any application code runs, so on a host that caps uploads at 2 MB an
     * ambitious default would break every upload rather than slow it down. The
     * margin leaves room for the multipart envelope around the chunk itself.
     */
    public function getChunkSize(): int
    {
        $limits = array_filter([
            $this->bytesFromIni('upload_max_filesize'),
            $this->bytesFromIni('post_max_size'),
        ]);

        if ($limits === []) {
            return $this->chunkSize;
        }

        $ceiling = (int) (min($limits) * 0.9);

        return max(256 * 1024, min($this->chunkSize, $ceiling));
    }

    /** Turns "512M" / "8M" / "2G" into bytes; 0 or -1 mean "no limit". */
    protected function bytesFromIni(string $directive): ?int
    {
        $raw = trim((string) ini_get($directive));

        if ($raw === '' || $raw === '0' || $raw === '-1') {
            return null;
        }

        $value = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /** Nothing is written through the form state; the controller attaches the media. */
    public function isDehydrated(): bool
    {
        return false;
    }
}
