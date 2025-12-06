<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\SliderRepositoryInterface;
use App\Http\Resources\Admin\SliderResource;
use App\Http\Requests\Admin\SliderRequest;
use App\Traits\ManagesFileUploads;
use App\Models\Slider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SliderController extends BaseApiController
{
    use ManagesFileUploads;
    
    protected $sliderRepository;

    public function __construct(SliderRepositoryInterface $sliderRepository)
    {
        $this->sliderRepository = $sliderRepository;
    }

    /**
     * Display a listing of the sliders with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'is_published' => 'nullable|string|in:true,false',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'is_published' => $request->input('is_published'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $sliders = $this->sliderRepository->getAllPaginated($filters, $perPage);

        // Transform data using SliderResource
        $transformedSliders = SliderResource::collection($sliders->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Sliders retrieved successfully',
            'data' => $transformedSliders,
            'pagination' => [
                'current_page' => $sliders->currentPage(),
                'last_page' => $sliders->lastPage(),
                'per_page' => $sliders->perPage(),
                'total' => $sliders->total(),
                'from' => $sliders->firstItem(),
                'to' => $sliders->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Store a newly created slider in storage.
     */
    public function store(SliderRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        
        // Handle image upload
        if ($request->hasFile('image')) {
            $imagePath = $this->uploadFile($request->file('image'), Slider::$STORAGE_DIR, 'public');
            $validatedData['image'] = $imagePath;
        }
        
        $slider = $this->sliderRepository->create($validatedData);
        $transformedSlider = new SliderResource($slider);

        // Log activity
        logAdminActivity('created', 'Slider', $slider->id);

        return $this->createdResponse($transformedSlider, 'Slider created successfully');
    }

    /**
     * Display the specified slider.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $slider = $this->sliderRepository->findById($id);

        if (!$slider) {
            return $this->notFoundResponse('Slider not found');
        }

        // Transform data using SliderResource
        $transformedSlider = new SliderResource($slider);

        return $this->resourceResponse($transformedSlider, 'Slider retrieved successfully');
    }

    /**
     * Update the specified slider in storage.
     */
    public function update(SliderRequest $request, int $id): JsonResponse
    {
        $validatedData = $request->validated();
        
        // Get the existing slider to check for old image
        $existingSlider = $this->sliderRepository->findById($id);
        
        if (!$existingSlider) {
            return $this->notFoundResponse('Slider not found');
        }
        
        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if it exists
            if ($existingSlider->image) {
                $this->deleteFile($existingSlider->image, 'public');
            }
            
            $imagePath = $this->uploadFile($request->file('image'), Slider::$STORAGE_DIR, 'public');
            $validatedData['image'] = $imagePath;
        }
        
        $slider = $this->sliderRepository->update($id, $validatedData);

        if (!$slider) {
            return $this->notFoundResponse('Slider not found');
        }

        $transformedSlider = new SliderResource($slider);
        
        // Log activity
        logAdminActivity('updated', 'Slider', $slider->id);
        
        return $this->updatedResponse($transformedSlider, 'Slider updated successfully');
    }

    /**
     * Toggle the published status of the specified slider.
     */
    public function togglePublished(int $id): JsonResponse
    {
        $slider = $this->sliderRepository->findById($id);

        if (!$slider) {
            return $this->notFoundResponse('Slider not found');
        }

        // Toggle the is_published status
        $slider->is_published = !$slider->is_published;
        $slider->save();

        $transformedSlider = new SliderResource($slider);
        $message = $slider->is_published 
            ? 'Slider published successfully' 
            : 'Slider unpublished successfully';

        // Log activity
        logAdminActivity('toggled_published', 'Slider', $slider->id, ['is_published' => $slider->is_published]);

        return $this->updatedResponse($transformedSlider, $message);
    }

    /**
     * Remove the specified slider from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        // Get the slider to check for image before deletion
        $slider = $this->sliderRepository->findById($id);
        
        if (!$slider) {
            return $this->notFoundResponse('Slider not found');
        }
        
        // Check if this is the last slider - must keep at least one
        $totalSliders = $this->sliderRepository->getAll()->count();
        
        if ($totalSliders <= 1) {
            return $this->errorResponse('Cannot delete the last slider. At least one slider must remain.', 422);
        }
        
        // Delete the image if it exists
        if ($slider->image) {
            $this->deleteFile($slider->image, 'public');
        }
        
        $deleted = $this->sliderRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Slider not found');
        }

        // Log activity
        logAdminActivity('deleted', 'Slider', $id);

        return $this->deletedResponse('Slider deleted successfully');
    }
}

