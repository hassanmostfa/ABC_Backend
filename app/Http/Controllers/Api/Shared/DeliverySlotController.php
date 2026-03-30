<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Shared\DeliverySlotResource;
use App\Services\DeliverySlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliverySlotController extends BaseApiController
{
    public function __construct(
        protected DeliverySlotService $deliverySlotService
    ) {}

    /**
     * GET ?date=Y-m-d — Available delivery time slots for that date (settings-driven; full slots excluded).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $payload = $this->deliverySlotService->getAvailableSlotsForDate($request->input('date'));

        if ($payload['out_of_range'] && !empty($payload['message'])) {
            return response()->json([
                'success' => false,
                'message' => $payload['message'],
                'data' => $payload,
            ], 422);
        }

        $data = [
            'date' => $payload['date'],
            'is_day_off' => $payload['is_day_off'],
            'out_of_range' => $payload['out_of_range'],
            'message' => $payload['message'],
            'slots' => DeliverySlotResource::collection($payload['slots']),
            'meta' => $payload['meta'],
        ];

        return $this->successResponse($data, 'Delivery slots retrieved successfully');
    }
}
