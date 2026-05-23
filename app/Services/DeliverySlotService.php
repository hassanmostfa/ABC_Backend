<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DeliverySlotService
{
    /**
     * Delivery scheduling settings for clients (date picker + slot config).
     *
     * @return array<string, mixed>
     */
    public function getScheduleSettings(): array
    {
        $window = $this->getBookableWindow();
        $todayPayload = $this->getAvailableSlotsForDate($window['min_bookable_date']);

        return [
            'same_day_delivery_enabled' => $this->isSameDayDeliveryEnabled(),
            'today_available' => !$todayPayload['out_of_range']
                && !$todayPayload['is_day_off']
                && count($todayPayload['slots']) > 0,
            'min_bookable_date' => $window['min_bookable_date'],
            'max_bookable_date' => $window['max_bookable_date'],
            'delivery_days' => $window['delivery_days'],
            'day_offs' => $this->parseDayOffs(),
            'opening_time' => (string) Setting::getValue('opening_time', '10:00 am'),
            'closing_time' => (string) Setting::getValue('closing_time', '10:00 pm'),
            'slot_interval_minutes' => max(1, (int) Setting::getValue('slot_interval', 60)),
            'max_delivery_per_slot' => max(0, (int) Setting::getValue('max_delivery_per_slot', 999)),
            'timezone' => config('app.timezone', 'Asia/Kuwait'),
        ];
    }

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

        $window = $this->getBookableWindow();
        $minBookable = Carbon::createFromFormat('Y-m-d', $window['min_bookable_date'], $tz)->startOfDay();
        $maxBookable = Carbon::createFromFormat('Y-m-d', $window['max_bookable_date'], $tz)->startOfDay();

        if ($date->lt($minBookable)) {
            return $this->emptyPayload(
                $dateYmd,
                false,
                true,
                $this->isSameDayDeliveryEnabled()
                    ? 'Date cannot be in the past.'
                    : 'Same-day delivery is disabled. Choose a date from tomorrow.'
            );
        }
        if ($date->gt($maxBookable)) {
            return $this->emptyPayload(
                $dateYmd,
                false,
                true,
                "Date must be within the next {$window['delivery_days']} day(s)."
            );
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

            if ($date->isSameDay($now) && $slotEnd->lte($now)) {
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
            'meta' => array_merge([
                'opening_time' => $openingRaw,
                'closing_time' => $closingRaw,
                'slot_interval_minutes' => $intervalMinutes,
                'max_delivery_per_slot' => $maxPerSlot,
                'timezone' => $tz,
                'same_day_delivery_enabled' => $this->isSameDayDeliveryEnabled(),
            ], $this->getBookableWindow()),
        ];
    }

    /**
     * @return array{min_bookable_date: string, max_bookable_date: string, delivery_days: int}
     */
    private function getBookableWindow(): array
    {
        $tz = config('app.timezone', 'Asia/Kuwait');
        $today = Carbon::now($tz)->startOfDay();
        $deliveryDays = max(1, (int) Setting::getValue('delivery_days', 7));
        $minBookable = $this->isSameDayDeliveryEnabled()
            ? $today
            : $today->copy()->addDay();
        $maxBookable = $minBookable->copy()->addDays($deliveryDays - 1);

        return [
            'min_bookable_date' => $minBookable->toDateString(),
            'max_bookable_date' => $maxBookable->toDateString(),
            'delivery_days' => $deliveryDays,
        ];
    }

    private function isSameDayDeliveryEnabled(): bool
    {
        $value = Setting::getValue('same_day_delivery_enabled', '1');

        return $value === '1' || $value === 1 || $value === true || $value === 'true';
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
