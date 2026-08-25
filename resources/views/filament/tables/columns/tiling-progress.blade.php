@php
    use App\Models\Photo;

    $record = $getRecord();
    $status = $record->dzi_status;
    $percent = $record->dzi_progress;

    $label = match ($status) {
        Photo::TILING_QUEUED => 'Waiting',
        Photo::TILING_PROCESSING => $percent !== null ? "Building {$percent}%" : 'Building…',
        Photo::TILING_READY => 'Ready',
        Photo::TILING_FAILED => 'Failed',
        default => null,
    };
@endphp

@if ($label === null)
    <span class="text-sm text-gray-400">—</span>
@else
    <div class="w-32 space-y-1" @if ($record->dzi_error) title="{{ $record->dzi_error }}" @endif>
        <span @class([
            'text-xs font-medium',
            'text-success-600 dark:text-success-400' => $status === Photo::TILING_READY,
            'text-danger-600 dark:text-danger-400' => $status === Photo::TILING_FAILED,
            'text-warning-600 dark:text-warning-400' => in_array($status, [Photo::TILING_QUEUED, Photo::TILING_PROCESSING], true),
        ])>{{ $label }}</span>

        {{-- The bar is only meaningful while work is in flight or has just finished. --}}
        @if (in_array($status, [Photo::TILING_QUEUED, Photo::TILING_PROCESSING, Photo::TILING_READY], true))
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                    @class([
                        'h-full rounded-full transition-all duration-500',
                        'bg-success-500' => $status === Photo::TILING_READY,
                        'bg-primary-500' => $status !== Photo::TILING_READY,
                        {{-- Queued has no percentage yet, so it pulses instead of sitting at zero. --}}
                        'animate-pulse' => $status === Photo::TILING_QUEUED,
                    ])
                    style="width: {{ $status === Photo::TILING_READY ? 100 : max($percent ?? 0, $status === Photo::TILING_QUEUED ? 8 : 2) }}%"
                ></div>
            </div>
        @endif
    </div>
@endif
