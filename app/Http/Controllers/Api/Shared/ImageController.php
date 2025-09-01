<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ImageController extends BaseApiController
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Upload an image and return its URL
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120', // 5MB max
        ]);

        $file = $request->file('image');
        $result = $this->imageService->uploadImage($file);

        if ($result['success']) {
            return $this->createdResponse(
                ['url' => $result['url']], 
                'Image uploaded successfully'
            );
        }

        return $this->errorResponse($result['message'], 400);
    }
}
