<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DeliverySlotService
{
    /**
     * Build available delivery slots for a calendar date using settings (opening/closing, interval, max per slot).
     * Full slots are omitted from the list.
     *
     * @return array{date: string, is_day_off: bool, out_of_range: bool, message: string|null, slots: array<int, array{start: string, end: string, delivery_time: string, remaining: int, capacity: int, booked: int}>, meta: array<string, mixed>}
     */
    public function getAvailableSlotsForDate(string $dateYmd): array
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $now = Carbon::now($tz);

        try {
            $date = Carbon::createFromFormat('Y-m-d', $dateYmd, $tz)->startOfDay();
        } catch (\Throwable $e) {
            return $this->emptyPayload($dateYmd, false, true, 'Invalid date format. Use Y-m-d.');
        }

        $deliveryDays = max(1, (int) Setting::getValue('delivery_days', 7));
        $today = $now->copy()->startOfDay();
        $maxBookable = $today->copy()->addDays($deliveryDays - 1);

        if ($date->lt($today)) {
            return $this->emptyPayload($dateYmd, false, true, 'Date cannot be in the past.');
        }
        if ($date->gt($maxBookable)) {
            return $this->emptyPayload($dateYmd, false, true, "Date must be within the next {$deliveryDays} day(s).");
        }

        $dayOffs = $this->parseDayOffs();
        $weekdayKey = strtolower($date->copy()->locale('en')->dayName);
        if (in_array($weekdayKey, $dayOffs, true)) {
            return $this->emptyPayload($dateYmd, true, false, null);
        }

        $openingRaw = (string) Setting::getValue('opening_time', '10:00 am');
        $closingRaw = (string) Setting::getValue('closing_time', '10:00 pm');
        $intervalMinutes = max(1, (int) Setting::getValue('slot_interval', 60));
        $maxPerSlot = max(0, (int) Setting::getValue('max_delivery_per_slot', 999));

        $open = $this->parseTimeOnDate($dateYmd, $openingRaw, $tz);
        $close = $this->parseTimeOnDate($dateYmd, $closingRaw, $tz);

        if ($close->lte($open)) {
            Log::warning('DeliverySlotService: closing time is not after opening time', [
                'date' => $dateYmd,
                'opening' => $openingRaw,
                'closing' => $closingRaw,
            ]);

            return $this->emptyPayload($dateYmd, false, false, 'Invalid opening or closing time configuration.');
        }

        $slots = [];
        $current = $open->copy();

        while ($current->lt($close)) {
            $slotEnd = $current->copy()->addMinutes($intervalMinutes);
            if ($slotEnd->gt($close)) {
                break;
            }

            if ($date->isSameDay($now) && $current->lt($now)) {
                $current->addMinutes($intervalMinutes);
                continue;
            }

            $timeHms = $current->format('H:i:s');
            $booked = $this->countDeliveriesForSlot($dateYmd, $timeHms);

            if ($booked < $maxPerSlot) {
                $slots[] = [
                    'start' => $current->format('H:i'),
                    'end' => $slotEnd->format('H:i'),
                    // Same as start — use for orders.delivery_time
                    'delivery_time' => $current->format('H:i'),
                    'remaining' => $maxPerSlot - $booked,
                    'capacity' => $maxPerSlot,
                    'booked' => $booked,
                ];
            }

            $current->addMinutes($intervalMinutes);
        }

        return [
            'date' => $dateYmd,
            'is_day_off' => false,
            'out_of_range' => false,
            'message' => null,
            'slots' => $slots,
            'meta' => [
                'opening_time' => $openingRaw,
                'closing_time' => $closingRaw,
                'slot_interval_minutes' => $intervalMinutes,
                'max_delivery_per_slot' => $maxPerSlot,
                'timezone' => $tz,
            ],
        ];
    }

    /**
     * @return array<int, string> lowercase English weekday names
     */
    private function parseDayOffs(): array
    {
        $raw = Setting::getValue('day_offs', '[]');
        if (!is_string($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($d) {
            return is_string($d) ? strtolower(trim($d)) : null;
        }, $decoded)));
    }

    private function parseTimeOnDate(string $dateYmd, string $timeStr, string $tz): Carbon
    {
        $timeStr = trim($timeStr);
        $combined = $dateYmd . ' ' . $timeStr;

        return Carbon::parse($combined, $tz);
    }

    private function countDeliveriesForSlot(string $dateYmd, string $timeHms): int
    {
        return Order::query()
            ->where('delivery_type', 'delivery')
            ->whereDate('delivery_date', $dateYmd)
            ->whereNotIn('status', ['cancelled'])
            ->whereNotNull('delivery_time')
            ->whereTime('delivery_time', '=', $timeHms)
            ->count();
    }

    private function emptyPayload(string $dateYmd, bool $isDayOff, bool $outOfRange, ?string $message): array
    {
        return [
            'date' => $dateYmd,
            'is_day_off' => $isDayOff,
            'out_of_range' => $outOfRange,
            'message' => $message,
            'slots' => [],
            'meta' => [
                'opening_time' => Setting::getValue('opening_time'),
                'closing_time' => Setting::getValue('closing_time'),
                'slot_interval_minutes' => (int) Setting::getValue('slot_interval', 60),
                'max_delivery_per_slot' => (int) Setting::getValue('max_delivery_per_slot', 999),
                'timezone' => config('app.timezone', 'Asia/Kuwait'),
            ],
        ];
    }
}
