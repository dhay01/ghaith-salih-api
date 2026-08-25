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
     * Bytes per chunk. This is the only thing the server's upload_max_filesize and
     * post_max_size have to exceed, however large the original file is — so it is
     * kept small enough that a stock PHP configuration works without editing.
     */
    protected int $chunkSize = 4 * 1024 * 1024;

    public function chunkSize(int $bytes): static
    {
        $this->chunkSize = $bytes;

        return $this;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /** Nothing is written through the form state; the controller attaches the media. */
    public function isDehydrated(): bool
    {
        return false;
    }
}
