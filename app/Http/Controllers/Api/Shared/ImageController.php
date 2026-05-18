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
     * SECURITY: Validates image type and restricts MIME types to prevent XSS
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB max, image types only
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
}
