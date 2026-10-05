<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves product photos from the media disk.
 * Works without the public/storage symlink and on any host or APP_URL.
 */
class MediaController extends Controller
{
    private const ALLOWED = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];

    public function __invoke(string $path): Response
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        abort_unless(
            Str::startsWith($path, 'products/')
            && ! Str::contains($path, '..')
            && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::ALLOWED, true),
            404
        );

        $disk = Storage::disk(config('filesystems.media_disk'));
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
