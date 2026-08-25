<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Receives a large upload as a sequence of small pieces.
 *
 * A gigapixel original is far too big for a single browser POST: PHP would need
 * `upload_max_filesize` and `post_max_size` raised into the gigabytes, the whole
 * body would be buffered, and one dropped connection would lose everything. The
 * browser slices the file instead and sends it a chunk at a time, so the server's
 * upload limit only has to exceed the chunk size — a few megabytes — no matter how
 * large the original is.
 */
class ChunkedUploadController extends Controller
{
    /** Where partial uploads accumulate before being assembled. */
    protected string $directory = 'chunked-uploads';

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'upload_id' => ['required', 'string', 'size:36'],
            'index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file'],
        ]);

        // The id comes from the browser, so it must not be able to escape the
        // upload directory or collide with another session's work.
        $uploadId = $this->safeId($data['upload_id']);

        Storage::disk('local')->putFileAs(
            $this->directory.'/'.$uploadId,
            $data['chunk'],
            str_pad((string) $data['index'], 9, '0', STR_PAD_LEFT).'.part',
        );

        return response()->json(['received' => (int) $data['index']]);
    }

    public function finish(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'upload_id' => ['required', 'string', 'size:36'],
            'photo' => ['required', 'integer', 'exists:photos,id'],
            'filename' => ['required', 'string', 'max:255'],
            'chunks' => ['required', 'integer', 'min:1'],
            'size' => ['required', 'integer', 'min:1'],
        ]);

        $uploadId = $this->safeId($data['upload_id']);
        $disk = Storage::disk('local');
        $chunkDir = $this->directory.'/'.$uploadId;

        $parts = collect($disk->files($chunkDir))->sort()->values();

        if ($parts->count() !== (int) $data['chunks']) {
            $disk->deleteDirectory($chunkDir);

            return response()->json([
                'message' => 'The upload arrived incomplete — '.$parts->count().' of '.$data['chunks'].' pieces. Please try again.',
            ], 422);
        }

        $assembled = $chunkDir.'/assembled-'.$this->safeFilename($data['filename']);
        $target = $disk->path($assembled);

        // Streamed together rather than read into memory: the whole point is that
        // this file is too large to hold at once.
        $out = fopen($target, 'wb');

        foreach ($parts as $part) {
            $in = fopen($disk->path($part), 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }

        fclose($out);

        if (filesize($target) !== (int) $data['size']) {
            $disk->deleteDirectory($chunkDir);

            return response()->json([
                'message' => 'The assembled file did not match the size the browser reported. Please try again.',
            ], 422);
        }

        $photo = Photo::findOrFail($data['photo']);
        $photo->clearMediaCollection('image');
        $photo->addMedia($target)
            ->usingFileName($this->safeFilename($data['filename']))
            ->toMediaCollection('image');

        $disk->deleteDirectory($chunkDir);

        // Adding the media fires the listener that queues tiling; report back what
        // the dashboard should now expect.
        $photo->refresh();

        return response()->json([
            'status' => $photo->dzi_status,
            'message' => $photo->is_zoomable
                ? 'Upload complete. Deep zoom tiles are being built in the background.'
                : 'Upload complete.',
        ]);
    }

    protected function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_admin === true, 403);
    }

    protected function safeId(string $id): string
    {
        abort_unless(Str::isUuid($id), 422, 'Malformed upload id.');

        return $id;
    }

    protected function safeFilename(string $name): string
    {
        return Str::of($name)->basename()->replaceMatches('/[^A-Za-z0-9._-]/', '-')->limit(120, '');
    }
}
