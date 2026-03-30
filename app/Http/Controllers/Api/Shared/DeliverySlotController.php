<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Shared\DeliverySlotResource;
use App\Services\DeliverySlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DeliverySlotController extends BaseApiController
{
    public function __construct(
        protected DeliverySlotService $deliverySlotService
    ) {}

    /**
     * Available delivery time windows for a date (settings-driven; full slots excluded).
     *
     * - GET: pass date as a query string only — do not send a JSON body on GET (many servers return 403).
     *   Example: GET /api/mobile/delivery-slots?date=2026-03-31
     * - POST: pass JSON body { "date": "2026-03-31" } if your client prefers a body.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'required|date_format:Y-m-d',
            ]);

            $payload = $this->deliverySlotService->getAvailableSlotsForDate((string) $request->input('date'));

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
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('DeliverySlotController::index failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'date' => $request->input('date'),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to load delivery slots.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
