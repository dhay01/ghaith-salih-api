@php
    $record = $getRecord();
    $existing = $record?->getFirstMedia('image');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if (! $record)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Save the photo first, then upload its original here.
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
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Current original: <span class="font-medium">{{ $existing->file_name }}</span>
                    ({{ number_format($existing->size / 1048576, 1) }} MB)
                </p>
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

                        try {
                            for (let index = 0; index < chunks; index++) {
                                const start = index * config.chunkSize;
                                const blob = file.slice(start, start + config.chunkSize);

                                const body = new FormData();
                                body.append('upload_id', uploadId);
                                body.append('index', index);
                                body.append('chunk', blob);

                                const response = await fetch(config.chunkUrl, {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': config.csrf, 'Accept': 'application/json' },
                                    body,
                                });

                                if (!response.ok) {
                                    throw new Error(`Piece ${index + 1} of ${chunks} was rejected (${response.status}).`);
                                }

                                // Held back from 100% until the server confirms assembly.
                                this.percent = Math.round(((index + 1) / chunks) * 95);
                                this.message = `Uploading — ${index + 1} of ${chunks} pieces`;
                            }

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
