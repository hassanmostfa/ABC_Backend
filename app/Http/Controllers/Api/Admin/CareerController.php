<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Repositories\CareerRepositoryInterface;
use App\Http\Resources\Admin\CareerResource;
use App\Http\Requests\Admin\CareerRequest;
use App\Traits\ManagesFileUploads;
use App\Models\Career;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CareerController extends BaseApiController
{
    use ManagesFileUploads;
    
    protected $careerRepository;

    public function __construct(CareerRepositoryInterface $careerRepository)
    {
        $this->careerRepository = $careerRepository;
    }

    /**
     * Display a listing of the career applications with pagination, search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Validate filter parameters
        $request->validate([
            'search' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Prepare filters
        $filters = [
            'search' => $request->input('search'),
            'position' => $request->input('position'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = $request->input('per_page', 15);
        $careers = $this->careerRepository->getAllPaginated($filters, $perPage);

        // Transform data using CareerResource
        $transformedCareers = CareerResource::collection($careers->items());

        // Create a custom response with pagination and filters
        $response = [
            'success' => true,
            'message' => 'Career applications retrieved successfully',
            'data' => $transformedCareers,
            'pagination' => [
                'current_page' => $careers->currentPage(),
                'last_page' => $careers->lastPage(),
                'per_page' => $careers->perPage(),
                'total' => $careers->total(),
                'from' => $careers->firstItem(),
                'to' => $careers->lastItem(),
            ]
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Store a newly created career application in storage.
     */
    public function store(CareerRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        
        // Handle file upload
        if ($request->hasFile('file')) {
            $filePath = $this->uploadFile($request->file('file'), Career::$STORAGE_DIR, 'public');
            $validatedData['file'] = $filePath;
        }
        
        $career = $this->careerRepository->create($validatedData);
        $transformedCareer = new CareerResource($career);

        return $this->createdResponse($transformedCareer, 'Career application submitted successfully');
    }

    /**
     * Display the specified career application.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $career = $this->careerRepository->findById($id);

        if (!$career) {
            return $this->notFoundResponse('Career application not found');
        }

        // Transform data using CareerResource
        $transformedCareer = new CareerResource($career);

        return $this->resourceResponse($transformedCareer, 'Career application retrieved successfully');
    }

    /**
     * Update the specified career application in storage.
     */
    public function update(CareerRequest $request, int $id): JsonResponse
    {
        $validatedData = $request->validated();
        
        // Get the existing career to check for old file
        $existingCareer = $this->careerRepository->findById($id);
        
        // Handle file upload
        if ($request->hasFile('file')) {
            // Delete old file if it exists
            if ($existingCareer && $existingCareer->file) {
                $this->deleteFile($existingCareer->file, 'public');
            }
            
            $filePath = $this->uploadFile($request->file('file'), Career::$STORAGE_DIR, 'public');
            $validatedData['file'] = $filePath;
        }
        
        $career = $this->careerRepository->update($id, $validatedData);

        if (!$career) {
            return $this->notFoundResponse('Career application not found');
        }

        $transformedCareer = new CareerResource($career);
        return $this->updatedResponse($transformedCareer, 'Career application updated successfully');
    }

    /**
     * Remove the specified career application from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        // Get the career to check for file before deletion
        $career = $this->careerRepository->findById($id);
        
        if (!$career) {
            return $this->notFoundResponse('Career application not found');
        }
        
        // Delete the file if it exists
        if ($career->file) {
            $this->deleteFile($career->file, 'public');
        }
        
        $deleted = $this->careerRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Career application not found');
        }

        return $this->deletedResponse('Career application deleted successfully');
    }
}
