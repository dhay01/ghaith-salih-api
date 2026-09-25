@php
    $record = $getRecord();
    $existing = $record?->getFirstMedia('image');

    // A preview is only safe once something smaller than the original exists.
    // For a large upload that is the vips derivative; until tiling has run,
    // imageUrls() falls back to the original itself, and putting a 378 MB file
    // in an img tag would download the whole thing into the dashboard.
    $urls = $record?->imageUrls();
    $original = $existing?->getFullUrl();
    $preview = null;

    if ($urls) {
        $candidate = $urls['preview'] ?? $urls['thumb'] ?? null;
        $preview = $candidate && $candidate !== $original ? $candidate : null;
    }

    $awaitingDerivatives = $existing && ! $preview;
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if (! $record)
        {{-- On create there is no record for the pieces to be addressed to yet.
             Saying so is not enough on its own: say what to do and where it
             leads, or this reads as a field that is simply broken. --}}
        <p class="text-sm text-gray-500 dark:text-gray-400">
            <span class="font-medium text-gray-700 dark:text-gray-200">
                Fill in the details below and press Create.
            </span>
            You will land on this photo's edit page, where this becomes a file
            picker that can take an original of any size.
        </p>
    @else
        <div
            x-data="largeFileUpload({
                photo: @js($record->getKey()),
                chunkSize: @js($getChunkSize()),
                chunkUrl: @js(route('large-upload.chunk')),
                finishUrl: @js(route('large-upload.finish')),
                csrf: @js(csrf_token()),
            })"
            class="space-y-3"
        >
            @if ($existing)
                <div class="space-y-2">
                    @if ($preview)
                        <img
                            src="{{ $preview }}"
                            alt="{{ $existing->file_name }}"
                            class="max-h-64 w-auto rounded-lg border border-gray-200 dark:border-gray-700"
                        />
                    @elseif ($awaitingDerivatives)
                        {{-- Deliberately not falling back to the original here: see above. --}}
                        <div class="flex h-32 items-center justify-center rounded-lg border border-dashed
                                    border-gray-300 px-4 text-center text-sm text-gray-500
                                    dark:border-gray-700 dark:text-gray-400">
                            Web-sized versions are still being generated — the preview appears once
                            deep zoom has finished processing this original.
                        </div>
                    @endif

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Current original: <span class="font-medium">{{ $existing->file_name }}</span>
                        ({{ number_format($existing->size / 1048576, 1) }} MB)
                    </p>
                </div>
            @endif

            <input
                type="file"
                accept="image/*,.tif,.tiff"
                x-ref="input"
                @change="start($event)"
                :disabled="busy"
                class="block w-full text-sm text-gray-600 dark:text-gray-300
                       file:mr-3 file:rounded-lg file:border-0 file:bg-primary-600
                       file:px-3 file:py-2 file:text-sm file:font-medium file:text-white
                       hover:file:bg-primary-500 disabled:opacity-50"
            />

            <template x-if="busy || done || error">
                <div class="space-y-2">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div
                            class="h-full rounded-full transition-all duration-200"
                            :class="error ? 'bg-danger-500' : 'bg-primary-600'"
                            :style="`width: ${percent}%`"
                        ></div>
                    </div>
                    <p class="text-sm" :class="error ? 'text-danger-600' : 'text-gray-500 dark:text-gray-400'" x-text="message"></p>
                </div>
            </template>
        </div>

        <script>
            function largeFileUpload(config) {
                return {
                    busy: false,
                    done: false,
                    error: false,
                    percent: 0,
                    message: '',

                    async start(event) {
                        const file = event.target.files[0];
                        if (!file) return;

                        this.busy = true;
                        this.done = false;
                        this.error = false;
                        this.percent = 0;

                        // A UUID per upload keeps concurrent or retried uploads from
                        // writing into each other's pieces.
                        const uploadId = crypto.randomUUID();
                        const chunks = Math.ceil(file.size / config.chunkSize);

                        // Pieces go up several at a time rather than one after another.
                        // Each one costs a full round trip, so on a slow link a
                        // strictly sequential upload spends most of its life waiting
                        // rather than sending, and never lets the connection reach
                        // full speed. The server addresses every piece by its index,
                        // so they may arrive in any order.
                        const CONCURRENCY = Math.min(4, chunks);
                        const ATTEMPTS = 3;

                        let next = 0;
                        let completed = 0;
                        let stopped = false;

                        const sendPiece = async (index) => {
                            const start = index * config.chunkSize;
                            const blob = file.slice(start, start + config.chunkSize);

                            for (let attempt = 1; attempt <= ATTEMPTS; attempt++) {
                                try {
                                    const body = new FormData();
                                    body.append('upload_id', uploadId);
                                    body.append('index', index);
                                    body.append('chunk', blob);

                                    const response = await fetch(config.chunkUrl, {
                                        method: 'POST',
                                        headers: { 'X-CSRF-TOKEN': config.csrf, 'Accept': 'application/json' },
                                        body,
                                    });

                                    if (response.ok) {
                                        return;
                                    }

                                    // Too large, unauthorised or invalid will fail the
                                    // same way however many times it is sent; only a
                                    // dropped or overloaded request is worth repeating.
                                    if ([403, 413, 419, 422].includes(response.status)) {
                                        throw new Error(`Piece ${index + 1} of ${chunks} was rejected (${response.status}).`);
                                    }

                                    if (attempt === ATTEMPTS) {
                                        throw new Error(`Piece ${index + 1} of ${chunks} failed after ${ATTEMPTS} attempts (${response.status}).`);
                                    }
                                } catch (e) {
                                    if (attempt === ATTEMPTS) {
                                        throw e;
                                    }
                                }

                                await new Promise((r) => setTimeout(r, 400 * attempt));
                            }
                        };

                        const worker = async () => {
                            while (!stopped) {
                                const index = next++;

                                if (index >= chunks) {
                                    return;
                                }

                                try {
                                    await sendPiece(index);
                                } catch (e) {
                                    stopped = true;
                                    throw e;
                                }

                                completed++;
                                // Held back from 100% until the server confirms assembly.
                                this.percent = Math.round((completed / chunks) * 95);
                                this.message = `Uploading — ${completed} of ${chunks} pieces`;
                            }
                        };

                        try {
                            await Promise.all(Array.from({ length: CONCURRENCY }, () => worker()));

                            this.message = 'Assembling…';

                            const finish = await fetch(config.finishUrl, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': config.csrf,
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    upload_id: uploadId,
                                    photo: config.photo,
                                    filename: file.name,
                                    chunks,
                                    size: file.size,
                                }),
                            });

                            const payload = await finish.json();

                            if (!finish.ok) {
                                throw new Error(payload.message ?? 'The upload could not be assembled.');
                            }

                            this.percent = 100;
                            this.done = true;
                            this.message = payload.message;

                            // The form was rendered before this file existed, so its
                            // image field still holds the empty state it loaded with.
                            // Both that field and this one write the same media
                            // collection, so the next save would sync the collection
                            // back to empty and delete what was just uploaded. A
                            // reload rehydrates the form against what is now on disk.
                            this.message = payload.message + ' Reloading…';
                            setTimeout(() => window.location.reload(), 1200);
                        } catch (e) {
                            this.error = true;
                            this.message = e.message;
                        } finally {
                            this.busy = false;
                            this.$refs.input.value = '';
                        }
                    },
                };
            }
        </script>
    @endif
</x-dynamic-component>
