<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Http\Resources\Admin\CouponResource;
use App\Repositories\CouponRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends BaseApiController
{
    public function __construct(protected CouponRepositoryInterface $couponRepository)
    {
    }

    /**
     * Display a listing of coupons with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:1000',
            'is_active' => 'nullable|in:true,false,1,0',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $filters = [
            'search' => $request->input('search'),
            'is_active' => $request->input('is_active'),
        ];

        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== '';
        });

        $perPage = (int) $request->input('per_page', 15);
        $coupons = $this->couponRepository->getAllPaginated($filters, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Coupons retrieved successfully',
            'data' => CouponResource::collection($coupons->items()),
            'pagination' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
                'from' => $coupons->firstItem(),
                'to' => $coupons->lastItem(),
            ],
            'filters' => $filters,
        ]);
    }

    /**
     * Store a newly created coupon in storage.
     */
    public function store(StoreCouponRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;
        $data['minimum_order_amount'] = $data['minimum_order_amount'] ?? 0;

        $coupon = $this->couponRepository->create($data);
        logAdminActivity('created', 'Coupon', $coupon->id);

        return $this->createdResponse(new CouponResource($coupon), 'Coupon created successfully');
    }

    /**
     * Display the specified coupon.
     */
    public function show(int $id): JsonResponse
    {
        $coupon = $this->couponRepository->findById($id);
        if (!$coupon) {
            return $this->notFoundResponse('Coupon not found');
        }

        return $this->resourceResponse(new CouponResource($coupon), 'Coupon retrieved successfully');
    }

    /**
     * Update the specified coupon in storage.
     */
    public function update(UpdateCouponRequest $request, int $id): JsonResponse
    {
        $coupon = $this->couponRepository->update($id, $request->validated());
        if (!$coupon) {
            return $this->notFoundResponse('Coupon not found');
        }

        logAdminActivity('updated', 'Coupon', $coupon->id);
        return $this->updatedResponse(new CouponResource($coupon), 'Coupon updated successfully');
    }

    /**
     * Remove the specified coupon from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $deleted = $this->couponRepository->delete($id);
        if (!$deleted) {
            return $this->notFoundResponse('Coupon not found');
        }

        logAdminActivity('deleted', 'Coupon', $id);
        return $this->deletedResponse('Coupon deleted successfully');
    }

    /**
     * Toggle coupon active status.
     */
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'is_active' => 'nullable|boolean',
        ]);

        $coupon = $this->couponRepository->findById($id);
        if (!$coupon) {
            return $this->notFoundResponse('Coupon not found');
        }

        $newStatus = $request->has('is_active')
            ? (bool) $request->input('is_active')
            : !$coupon->is_active;

        $updated = $this->couponRepository->update($id, [
            'is_active' => $newStatus,
        ]);

        if (!$updated) {
            return $this->errorResponse('Failed to update coupon status', 500);
        }

        logAdminActivity($newStatus ? 'activated' : 'deactivated', 'Coupon', $id);

        return $this->updatedResponse(
            new CouponResource($updated),
            $newStatus ? 'Coupon activated successfully' : 'Coupon deactivated successfully'
        );
    }
}
