<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ManagesFileUploads
{
    public function uploadFile(UploadedFile $file, string $directory, string $disk = 'local', ?int $userId = null): string
    {
        $extension = $file->getClientOriginalExtension();
        $encryptedName = Str::random(40).'.'.$extension;

        // If userId is provided, create user-specific directory
        if ($userId !== null) {
            $directory = $directory.'/'.$userId;
        }

        return Storage::disk($disk)->putFileAs($directory, $file, $encryptedName);
    }

    public function getFilePath(string $relativePath, string $disk = 'local'): string
    {
        return Storage::disk($disk)->path($relativePath);
    }

    public function getFileUrl(?string $relativePath, string $disk = 'public', string $fallbackImage = 'no-image.png', ?int $userId = null): string
    {
        if ($relativePath) {
            // If userId is provided, check in user-specific directory first
            if ($userId !== null) {
                $userSpecificPath = dirname($relativePath).'/'.$userId.'/'.basename($relativePath);
                if (Storage::disk($disk)->exists($userSpecificPath)) {
                    return url(Storage::url($userSpecificPath));
                }
            }

            // Check original path
            if (Storage::disk($disk)->exists($relativePath)) {
                return url(Storage::url($relativePath));
            }
        }

        // Return fallback image URL from public/images
        return url("defaults/files/{$fallbackImage}");
    }

    public function downloadFile(string $relativePath, ?string $originalName = null, string $disk = 'local'): StreamedResponse
    {
        if (! Storage::disk($disk)->exists($relativePath)) {
            abort(404, 'File not found.');
        }

        return Storage::disk($disk)->download($relativePath, $originalName);
    }

    public function deleteFile(string $relativePath, string $disk = 'local', ?int $userId = null): bool
    {
        // If userId is provided, try to delete from user-specific directory first
        if ($userId !== null) {
            $userSpecificPath = dirname($relativePath).'/'.$userId.'/'.basename($relativePath);
            if (Storage::disk($disk)->exists($userSpecificPath)) {
                return Storage::disk($disk)->delete($userSpecificPath);
            }
        }

        // Try to delete from original path
        return Storage::disk($disk)->delete($relativePath);
    }

    public function getFileMimeType(string $relativePath, string $disk = 'local'): ?string
    {
        return Storage::disk($disk)->mimeType($relativePath);
    }
}
