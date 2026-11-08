<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicImageController extends Controller
{
    public function __invoke(string $path): StreamedResponse
    {
        abort_unless(
            (str_starts_with($path, 'knowledge-base/') || str_starts_with($path, 'profile-photos/'))
                && ! str_contains($path, '\\')
                && ! in_array('..', explode('/', $path), true),
            404,
        );

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        $mimeType = $disk->mimeType($path);
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return $disk->response($path, headers: [
            'Cache-Control' => 'public, max-age=86400',
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
