<?php

return [

    /*
    | Path to the vips binary. Homebrew puts it in /opt/homebrew/bin on Apple
    | silicon; most Linux servers put it in /usr/bin after `apt install
    | libvips-tools`. Override with VIPS_BINARY if it lives somewhere else.
    */
    'binary' => env('VIPS_BINARY', 'vips'),

    /*
    | 512 rather than the 256 the Deep Zoom spec suggests: it produces roughly a
    | quarter as many files for the same image, which matters a great deal when
    | deploying or backing up a folder of thousands of tiles.
    */
    'tile_size' => (int) env('GIGAPIXEL_TILE_SIZE', 512),

    'quality' => (int) env('GIGAPIXEL_QUALITY', 85),

    /*
    | Where tile pyramids are written. The `public` disk keeps them on the
    | server's own filesystem; pointing this at an S3-compatible disk later is a
    | configuration change rather than a code change.
    */
    'disk' => env('GIGAPIXEL_DISK', 'public'),

    'directory' => 'tiles',

    /*
    | Slicing a multi-gigabyte original is minutes of work, not seconds, so the
    | job is given room. Applies per photo.
    */
    'timeout' => (int) env('GIGAPIXEL_TIMEOUT', 1800),

    /*
    | Above this size an upload is treated as "large": the normal GD-based
    | thumbnail pipeline is skipped entirely, because GD decodes the whole image
    | into memory and a gigapixel original needs many gigabytes to do that. vips
    | generates the web-sized versions instead, streaming as it goes.
    */
    'large_file_bytes' => (int) env('GIGAPIXEL_LARGE_BYTES', 25 * 1024 * 1024),

    /*
    | Longest edge, in pixels, of each web-sized version vips produces for large
    | originals. Mirrors the GD conversions used for ordinary uploads.
    */
    'derivatives' => [
        'thumb' => 600,
        'preview' => 1400,
        'full' => 2600,
    ],
];
