@php
    use App\Models\Photo;

    $record = $getRecord();
    $status = $record?->dzi_status;
    $percent = $record?->dzi_progress ?? 0;
    $working = in_array($status, [Photo::TILING_QUEUED, Photo::TILING_PROCESSING], true);

    $headline = $record?->dzi_stage ?: match ($status) {
        Photo::TILING_QUEUED => 'Waiting for the queue worker',
        Photo::TILING_PROCESSING => 'Working',
        Photo::TILING_READY => 'Deep zoom is ready',
        Photo::TILING_FAILED => 'Deep zoom failed',
        default => 'No deep zoom tiles yet',
    };

    $modifier = match (true) {
        $status === Photo::TILING_READY => 'tiling--ready',
        $status === Photo::TILING_FAILED => 'tiling--failed',
        $status === Photo::TILING_QUEUED => 'tiling--working tiling--queued',
        $working => 'tiling--working',
        default => '',
    };

    $meta = $record?->dzi_meta;
@endphp

{{-- Polls only while there is something to watch: an idle edit page should not
     be talking to the server every three seconds. --}}
<div @if ($working) wire:poll.3s @endif class="tiling tiling--wide {{ $modifier }}">
    <div class="tiling__head">
        <span class="tiling__label">{{ $headline }}</span>

        @if ($status === Photo::TILING_PROCESSING)
            <span class="tiling__percent">{{ $percent }}%</span>
        @endif
    </div>

    @if ($working || $status === Photo::TILING_READY)
        <div class="tiling__track">
            <div
                class="tiling__fill"
                style="width: {{ $status === Photo::TILING_READY ? 100 : max($percent, $status === Photo::TILING_QUEUED ? 8 : 2) }}%"
            ></div>
        </div>
    @endif

    @if ($working)
        {{-- The complaint about this job was not knowing whether it was working
             or stuck. The heartbeat answers that; the reassurance answers the
             next question, which is whether the tab has to stay open. --}}
        <p class="tiling__note">
            Slicing a gigapixel original takes several minutes, and uploading the tiles takes a few more.
            This runs in the background &mdash; you can leave this page and come back.
            Last update {{ $record->updated_at?->diffForHumans() }}.
        </p>
    @elseif ($status === Photo::TILING_FAILED && $record->dzi_error)
        <p class="tiling__note tiling__note--error">{{ $record->dzi_error }}</p>
    @elseif ($status === Photo::TILING_READY && is_array($meta))
        <p class="tiling__note">
            {{ number_format((int) ($meta['width'] ?? 0)) }} &times; {{ number_format((int) ($meta['height'] ?? 0)) }} pixels,
            in {{ (int) ($meta['tileSize'] ?? 0) }}px tiles.
            @if ($record->dzi_generated_at)
                Built {{ $record->dzi_generated_at->diffForHumans() }}.
            @endif
        </p>
    @endif
</div>
