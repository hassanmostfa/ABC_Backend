<?php

namespace App\Support;

use Illuminate\Http\Request;

class DeviceId
{
    public const HEADER = 'X-Device-Id';

    /**
     * Stable id sent by the app (UUID or Android/iOS device id).
     * Accepts the header, then a device_id body field.
     */
    public static function fromRequest(Request $request): ?string
    {
        $raw = trim((string) $request->header(self::HEADER, ''));
        if ($raw === '') {
            $raw = trim((string) $request->input('device_id', ''));
        }

        return self::normalize($raw);
    }

    public static function normalize(mixed $value): ?string
    {
        $id = strtolower(trim((string) $value));
        if ($id === '' || strlen($id) > 128) {
            return null;
        }

        if (!preg_match('/^[a-z0-9][a-z0-9._:-]{7,127}$/', $id)) {
            return null;
        }

        return $id;
    }
}
