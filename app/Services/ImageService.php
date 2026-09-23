<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    /**
     * Convert an uploaded image to WebP format and save to public storage disk.
     */
    public function convertToWebp(UploadedFile $file, string $directory = 'avatars', int $quality = 85): string
    {
        $imageContent = file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($imageContent);

        $filename = $directory.'/'.Str::uuid().'.webp';

        if ($image === false) {
            // Fallback if imagecreatefromstring cannot decode (e.g. SVG or raw WebP)
            return $file->store($directory, 'public');
        }

        // Preserve alpha transparency for PNGs and transparent images
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, $quality);
        $webpData = (string) ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put($filename, $webpData);

        return $filename;
    }

    /**
     * Delete an existing avatar from public storage.
     */
    public function deleteAvatar(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
