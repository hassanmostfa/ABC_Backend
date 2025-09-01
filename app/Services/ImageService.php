<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    protected $disk;
    protected $path;
    protected $allowedMimes;
    protected $maxSize;

    public function __construct()
    {
        $this->disk = 'public'; // Explicitly use public disk
        $this->path = 'images';
        $this->allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $this->maxSize = 5 * 1024 * 1024; // 5MB
    }

    /**
     * Upload an image and return its URL
     *
     * @param UploadedFile $file
     * @return array
     */
    public function uploadImage(UploadedFile $file): array
    {
        try {
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['success']) {
                return $validation;
            }

            // Generate unique filename
            $filename = $this->generateFilename($file);
            
            // Store the file
            $storedPath = Storage::disk($this->disk)->putFileAs($this->path, $file, $filename);
            
            if (!$storedPath) {
                return [
                    'success' => false,
                    'message' => 'Failed to upload image'
                ];
            }

            // Get the full URL with domain
            $url = Storage::disk($this->disk)->url($storedPath);

            return [
                'success' => true,
                'url' => $url
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error uploading image: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate uploaded file
     *
     * @param UploadedFile $file
     * @return array
     */
    protected function validateFile(UploadedFile $file): array
    {
        // Check if file is valid
        if (!$file->isValid()) {
            return [
                'success' => false,
                'message' => 'Invalid file upload'
            ];
        }

        // Check file size
        if ($file->getSize() > $this->maxSize) {
            return [
                'success' => false,
                'message' => 'File size exceeds maximum limit of ' . ($this->maxSize / 1024 / 1024) . 'MB'
            ];
        }

        // Check mime type
        if (!in_array($file->getMimeType(), $this->allowedMimes)) {
            return [
                'success' => false,
                'message' => 'File type not allowed. Allowed types: jpg, jpeg, png, gif, webp'
            ];
        }

        return [
            'success' => true
        ];
    }

    /**
     * Generate unique filename
     *
     * @param UploadedFile $file
     * @return string
     */
    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $timestamp = now()->format('Y-m-d_H-i-s');
        $random = Str::random(8);
        
        return "{$name}_{$timestamp}_{$random}.{$extension}";
    }
}
