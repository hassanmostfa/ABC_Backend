<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\ServiceRequest;
use App\Http\Resources\Admin\ServiceResource;
use App\Models\Service;
use App\Repositories\Services\ServiceRepositoryInterface;
use App\Traits\ManagesFileUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends BaseApiController
{
    use ManagesFileUploads;

    public function __construct(protected ServiceRepositoryInterface $serviceRepository)
    {
    }

    /**
     * Display a listing of services.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'is_active' => 'nullable|in:true,false,1,0',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = array_filter([
            'search' => $request->input('search'),
            'is_active' => $request->input('is_active'),
        ], fn ($value) => $value !== null && $value !== '');

        $perPage = (int) $request->input('per_page', 15);
        $services = $this->serviceRepository->getAllPaginated($filters, $perPage);

        $response = [
            'success' => true,
            'message' => 'Services retrieved successfully',
            'data' => ServiceResource::collection($services->items()),
            'pagination' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
                'from' => $services->firstItem(),
                'to' => $services->lastItem(),
            ],
        ];

        if (!empty($filters)) {
            $response['filters'] = $filters;
        }

        return response()->json($response);
    }

    /**
     * Store a newly created service.
     */
    public function store(ServiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        if ($request->hasFile('image')) {
            $data['image'] = $this->uploadFile($request->file('image'), Service::$STORAGE_DIR, 'public');
        }

        $service = $this->serviceRepository->create($data);

        logAdminActivity('created', 'Service', $service->id);

        return $this->createdResponse(
            new ServiceResource($service),
            'Service created successfully'
        );
    }

    /**
     * Display the specified service.
     */
    public function show(int $id): JsonResponse
    {
        $service = $this->serviceRepository->findById($id);

        if (!$service) {
            return $this->notFoundResponse('Service not found');
        }

        return $this->resourceResponse(
            new ServiceResource($service),
            'Service retrieved successfully'
        );
    }

    /**
     * Update the specified service.
     */
    public function update(ServiceRequest $request, int $id): JsonResponse
    {
        $service = $this->serviceRepository->findById($id);

        if (!$service) {
            return $this->notFoundResponse('Service not found');
        }

        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($service->image) {
                $this->deleteFile($service->image, 'public');
            }

            $data['image'] = $this->uploadFile($request->file('image'), Service::$STORAGE_DIR, 'public');
        }

        $updated = $this->serviceRepository->update($id, $data);

        if (!$updated) {
            return $this->notFoundResponse('Service not found');
        }

        logAdminActivity('updated', 'Service', $updated->id);

        return $this->updatedResponse(
            new ServiceResource($updated),
            'Service updated successfully'
        );
    }

    /**
     * Toggle service active status.
     */
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'is_active' => 'nullable|boolean',
        ]);

        $service = $this->serviceRepository->findById($id);
        if (!$service) {
            return $this->notFoundResponse('Service not found');
        }

        $newStatus = $request->has('is_active')
            ? (bool) $request->input('is_active')
            : !$service->is_active;

        $updated = $this->serviceRepository->update($id, [
            'is_active' => $newStatus,
        ]);

        if (!$updated) {
            return $this->errorResponse('Failed to update service status', 500);
        }

        logAdminActivity($newStatus ? 'activated' : 'deactivated', 'Service', $id);

        return $this->updatedResponse(
            new ServiceResource($updated),
            $newStatus ? 'Service activated successfully' : 'Service deactivated successfully'
        );
    }

    /**
     * Remove the specified service.
     */
    public function destroy(int $id): JsonResponse
    {
        $service = $this->serviceRepository->findById($id);

        if (!$service) {
            return $this->notFoundResponse('Service not found');
        }

        if ($service->image) {
            $this->deleteFile($service->image, 'public');
        }

        $deleted = $this->serviceRepository->delete($id);

        if (!$deleted) {
            return $this->notFoundResponse('Service not found');
        }

        logAdminActivity('deleted', 'Service', $id);

        return $this->deletedResponse('Service deleted successfully');
    }
}
