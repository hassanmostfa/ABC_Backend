<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileService
{
    protected $disk;
    protected $path;
    protected $allowedMimes;
    protected $maxSize;

    public function __construct()
    {
        $this->disk = 'public';
        $this->path = 'files';
        // Allow all file types by default, but you can restrict if needed
        $this->allowedMimes = [
            // Images
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            // Documents
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain', 'text/csv',
            // Archives
            'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
            // Audio
            'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4',
            // Video
            'video/mp4', 'video/avi', 'video/mov', 'video/wmv', 'video/flv', 'video/webm'
        ];
        $this->maxSize = 50 * 1024 * 1024; // 50MB
    }

    /**
     * Upload a file and return its URL
     *
     * @param UploadedFile $file
     * @param string|null $customPath
     * @return array
     */
    public function uploadFile(UploadedFile $file, ?string $customPath = null): array
    {
        try {
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['success']) {
                return $validation;
            }

            // Use custom path if provided, otherwise use default
            $uploadPath = $customPath ?: $this->path;
            
            // Generate unique filename
            $filename = $this->generateFilename($file);
            
            // Store the file
            $storedPath = Storage::disk($this->disk)->putFileAs($uploadPath, $file, $filename);
            
            if (!$storedPath) {
                return [
                    'success' => false,
                    'message' => 'Failed to upload file'
                ];
            }

            // Get the full URL with domain
            $url = Storage::disk($this->disk)->url($storedPath);

            return [
                'success' => true,
                'url' => $url,
                'path' => $storedPath,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error uploading file: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Upload multiple files
     *
     * @param array $files
     * @param string|null $customPath
     * @return array
     */
    public function uploadMultipleFiles(array $files, ?string $customPath = null): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($files as $file) {
            $result = $this->uploadFile($file, $customPath);
            $results[] = $result;
            
            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        return [
            'success' => $errorCount === 0,
            'results' => $results,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'message' => "Uploaded {$successCount} files successfully" . ($errorCount > 0 ? ", {$errorCount} failed" : "")
        ];
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

        // Check mime type (allow all if no restrictions)
        if (!empty($this->allowedMimes) && !in_array($file->getMimeType(), $this->allowedMimes)) {
            $allowedExtensions = implode(', ', array_unique(array_map(function($mime) {
                $extensions = [
                    'image/jpeg' => 'jpg, jpeg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                    'application/pdf' => 'pdf',
                    'application/msword' => 'doc',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                    'application/vnd.ms-excel' => 'xls',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                    'text/plain' => 'txt',
                    'application/zip' => 'zip',
                    'audio/mpeg' => 'mp3',
                    'video/mp4' => 'mp4'
                ];
                return $extensions[$mime] ?? 'unknown';
            }, $this->allowedMimes)));
            
            return [
                'success' => false,
                'message' => 'File type not allowed. Allowed types: ' . $allowedExtensions
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

    /**
     * Delete a file
     *
     * @param string $path
     * @return bool
     */
    public function deleteFile(string $path): bool
    {
        try {
            return Storage::disk($this->disk)->delete($path);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Set allowed mime types
     *
     * @param array $mimes
     * @return void
     */
    public function setAllowedMimes(array $mimes): void
    {
        $this->allowedMimes = $mimes;
    }

    /**
     * Set max file size
     *
     * @param int $sizeInBytes
     * @return void
     */
    public function setMaxSize(int $sizeInBytes): void
    {
        $this->maxSize = $sizeInBytes;
    }
}
