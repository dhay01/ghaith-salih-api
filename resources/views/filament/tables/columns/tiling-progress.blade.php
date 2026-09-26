@php
    use App\Models\Photo;

    $record = $getRecord();
    $status = $record->dzi_status;
    $percent = $record->dzi_progress;
    $working = in_array($status, [Photo::TILING_QUEUED, Photo::TILING_PROCESSING], true);

    // dzi_stage is written by the tiling job and says what it is doing right
    // now. It is the useful line whenever there is one; the words below are the
    // fallback for the states the job itself never reaches.
    $headline = $record->dzi_stage ?: match ($status) {
        Photo::TILING_QUEUED => 'Waiting for the queue worker',
        Photo::TILING_PROCESSING => 'Working',
        Photo::TILING_READY => 'Ready',
        Photo::TILING_FAILED => 'Failed',
        default => null,
    };

    $modifier = match (true) {
        $status === Photo::TILING_READY => 'tiling--ready',
        $status === Photo::TILING_FAILED => 'tiling--failed',
        $status === Photo::TILING_QUEUED => 'tiling--working tiling--queued',
        $working => 'tiling--working',
        default => '',
    };
@endphp

@if ($headline === null)
    <span class="tiling__label">&mdash;</span>
@else

    <div class="tiling {{ $modifier }}" @if ($record->dzi_error) title="{{ $record->dzi_error }}" @endif>
        <div class="tiling__head">
            <span class="tiling__label">{{ $headline }}</span>

            {{-- The number only while it is moving: on a finished row it is noise. --}}
            @if ($status === Photo::TILING_PROCESSING && $percent !== null)
                <span class="tiling__percent">{{ $percent }}%</span>
            @endif
        </div>

        @if (in_array($status, [Photo::TILING_QUEUED, Photo::TILING_PROCESSING, Photo::TILING_READY], true))
            <div class="tiling__track">
                <div
                    class="tiling__fill"
                    style="width: {{ $status === Photo::TILING_READY ? 100 : max($percent ?? 0, $status === Photo::TILING_QUEUED ? 8 : 2) }}%"
                ></div>
            </div>
        @endif

        {{-- Tiling runs for minutes, so the reason it failed has to be readable
             without hovering for a tooltip. --}}
        @if ($status === Photo::TILING_FAILED && $record->dzi_error)
            <p class="tiling__note tiling__note--error">{{ $record->dzi_error }}</p>
        @endif
    </div>
@endif
