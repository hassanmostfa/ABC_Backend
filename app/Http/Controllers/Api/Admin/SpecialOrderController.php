<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Middleware\RestrictSpecialOrderPortalToken;
use App\Http\Requests\Admin\RejectSpecialOrderRequest;
use App\Http\Requests\Admin\StoreSpecialOrderRequest;
use App\Http\Resources\Admin\SpecialOrderResource;
use App\Models\Admin;
use App\Models\SpecialOrder;
use App\Services\Orders\SpecialOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SpecialOrderController extends BaseApiController
{
    public function __construct(
        protected SpecialOrderService $specialOrderService
    ) {}

    /**
     * Dedicated login for the special-order approval page. Only admins who can view and
     * approve/reject special orders are allowed in.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $admin = Admin::query()->with('role')->where($loginField, $request->login)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return $this->errorResponse('هذه البيانات غير صحيحة', 401);
        }

        if (!$admin->is_active) {
            return $this->errorResponse('حسابك غير مفعل الرجاء التواصل مع الادارة', 401);
        }

        if (
            !$admin->hasPermission(SpecialOrderService::APPROVAL_PERMISSION, 'view')
            || !$admin->hasPermission(SpecialOrderService::APPROVAL_PERMISSION, 'edit')
        ) {
            return $this->errorResponse('ليس لديك صلاحية الدخول إلى صفحة اعتماد الطلبات الخاصة', 403);
        }

        $tokenExpiresAt = now()->addMinutes((int) config('sanctum.expiration', 60 * 24 * 30));
        $token = $admin->createToken(
            'special-order-approver-token',
            [RestrictSpecialOrderPortalToken::ABILITY],
            $tokenExpiresAt
        )->plainTextToken;

        return $this->successResponse([
            'admin' => $this->portalAdminPayload($admin),
            'permissions' => $this->portalPermissions($admin),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'تم تسجيل الدخول بنجاح');
    }

    /**
     * Current approver profile for the special-order portal.
     */
    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();
        if (!$admin instanceof Admin) {
            return $this->unauthorizedResponse('Admin authentication required.');
        }

        $admin->loadMissing('role');

        return $this->successResponse([
            'admin' => $this->portalAdminPayload($admin),
            'permissions' => $this->portalPermissions($admin),
        ], 'Profile retrieved successfully');
    }

    /**
     * Logout of the special-order approval portal.
     */
    public function logout(Request $request): JsonResponse
    {
        $admin = $request->user();
        if ($admin) {
            $admin->currentAccessToken()?->delete();
        }

        return $this->successResponse(null, 'تم تسجيل الخروج بنجاح');
    }

    /**
     * List special orders with pagination, search and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:1000',
            'status' => 'nullable|in:pending,approved,rejected,cancelled',
            'payment_method' => 'nullable|in:cash,wallet,online_link',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = SpecialOrder::with(['customer', 'requestedBy', 'reviewedBy'])
            ->orderByDesc('created_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($customer) use ($search) {
                        $customer->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('phone', 'LIKE', "%{$search}%");
                    });
            });
        }

        foreach (['status', 'payment_method'] as $filter) {
            if ($value = $request->input($filter)) {
                $query->where($filter, $value);
            }
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $specialOrders = $query->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(
            $specialOrders->through(fn ($specialOrder) => new SpecialOrderResource($specialOrder)),
            'Special orders retrieved successfully'
        );
    }

    /**
     * Price a special order at the agreed final price and queue it for approval.
     */
    public function store(StoreSpecialOrderRequest $request): JsonResponse
    {
        try {
            $admin = $request->user();
            $specialOrder = $this->specialOrderService->create(
                $request->validated(),
                $admin instanceof Admin ? $admin : null
            );

            logAdminActivity('created', 'SpecialOrder', $specialOrder->id, [
                'order_number' => $specialOrder->order_number,
                'original_amount_due' => (float) $specialOrder->original_amount_due,
                'final_price' => (float) $specialOrder->final_price,
                'discount_percentage' => (float) $specialOrder->discount_percentage,
            ]);

            $specialOrder->load(['customer', 'requestedBy']);

            return $this->createdResponse(
                new SpecialOrderResource($specialOrder),
                'Special order submitted for approval'
            );
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) && $e->getCode() > 0 ? (int) $e->getCode() : 400;

            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * Display the specified special order.
     */
    public function show(int $id): JsonResponse
    {
        $specialOrder = SpecialOrder::with([
            'customer',
            'requestedBy',
            'reviewedBy',
            'orderCheckout',
            'order.customer',
            'order.items.product',
            'order.items.variant',
            'order.invoice.payments',
        ])->find($id);

        if (!$specialOrder) {
            return $this->notFoundResponse('Special order not found');
        }

        return $this->resourceResponse(new SpecialOrderResource($specialOrder), 'Special order retrieved successfully');
    }

    /**
     * Approve the special order: create the order (or its payment link) at the discounted price.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $specialOrder = SpecialOrder::find($id);

        if (!$specialOrder) {
            return $this->notFoundResponse('Special order not found');
        }

        try {
            $admin = $request->user();
            $result = $this->specialOrderService->approve(
                $specialOrder,
                $admin instanceof Admin ? $admin : null,
                $validated['notes'] ?? null
            );

            if (!$result['success']) {
                return $this->errorResponse($result['message'], 400);
            }

            logAdminActivity('approved', 'SpecialOrder', $id, [
                'order_number' => $specialOrder->order_number,
                'order_id' => $result['special_order']->order_id,
            ]);

            $result['special_order']->load(['customer', 'requestedBy', 'reviewedBy', 'orderCheckout', 'order']);

            return $this->successResponse([
                'special_order' => new SpecialOrderResource($result['special_order']),
                'payment_link' => $result['payment_link'] ?? null,
            ], $result['message']);
        } catch (\Exception $e) {
            $code = is_numeric($e->getCode()) && $e->getCode() > 0 ? (int) $e->getCode() : 500;

            return $this->errorResponse($e->getMessage(), $code);
        }
    }

    /**
     * Reject the special order with a reason.
     */
    public function reject(RejectSpecialOrderRequest $request, int $id): JsonResponse
    {
        $specialOrder = SpecialOrder::find($id);

        if (!$specialOrder) {
            return $this->notFoundResponse('Special order not found');
        }

        $admin = $request->user();
        $result = $this->specialOrderService->reject(
            $specialOrder,
            $request->validated('reason'),
            $admin instanceof Admin ? $admin : null
        );

        if (!$result['success']) {
            return $this->errorResponse($result['message'], 400);
        }

        logAdminActivity('rejected', 'SpecialOrder', $id, [
            'order_number' => $specialOrder->order_number,
            'reason' => $request->validated('reason'),
        ]);

        $result['special_order']->load(['customer', 'requestedBy', 'reviewedBy']);

        return $this->successResponse(
            new SpecialOrderResource($result['special_order']),
            $result['message']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function portalAdminPayload(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'admin_id' => $admin->admin_id,
            'name' => $admin->name,
            'employee_code' => $admin->employee_code,
            'email' => $admin->email,
            'phone' => $admin->phone,
            'role' => $admin->role ? [
                'id' => $admin->role->id,
                'name' => $admin->role->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function portalPermissions(Admin $admin): array
    {
        return [
            'can_view' => $admin->hasPermission(SpecialOrderService::APPROVAL_PERMISSION, 'view'),
            'can_approve' => $admin->hasPermission(SpecialOrderService::APPROVAL_PERMISSION, 'edit'),
            'can_reject' => $admin->hasPermission(SpecialOrderService::APPROVAL_PERMISSION, 'edit'),
        ];
    }
}
