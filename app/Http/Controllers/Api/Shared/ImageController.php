<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ImageController extends BaseApiController
{
    protected $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Upload a file and return its URL
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200', // 50MB max
        ]);

        $file = $request->file('file');
        $result = $this->fileService->uploadFile($file);

        if ($result['success']) {
            return $this->createdResponse([
                'url' => $result['url'],
                'path' => $result['path'],
                'filename' => $result['filename'],
                'original_name' => $result['original_name'],
                'size' => $result['size'],
                'mime_type' => $result['mime_type']
            ], 'File uploaded successfully');
        }

        return $this->errorResponse($result['message'], 400);
    }

    /**
     * Upload multiple files
     */
    public function uploadMultiple(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'required|file|max:51200', // 50MB max per file
        ]);

        $files = $request->file('files');
        $result = $this->fileService->uploadMultipleFiles($files);

        if ($result['success']) {
            return $this->createdResponse($result, 'Files uploaded successfully');
        }

        return $this->errorResponse($result['message'], 400);
    }

    /**
     * Upload file to specific path (e.g., for career applications)
     */
    public function uploadToPath(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200', // 50MB max
            'path' => 'nullable|string|max:255', // Custom path
        ]);

        $file = $request->file('file');
        $customPath = $request->input('path', 'files');
        
        $result = $this->fileService->uploadFile($file, $customPath);

        if ($result['success']) {
            return $this->createdResponse([
                'url' => $result['url'],
                'path' => $result['path'],
                'filename' => $result['filename'],
                'original_name' => $result['original_name'],
                'size' => $result['size'],
                'mime_type' => $result['mime_type']
            ], 'File uploaded successfully');
        }

        return $this->errorResponse($result['message'], 400);
    }
}
