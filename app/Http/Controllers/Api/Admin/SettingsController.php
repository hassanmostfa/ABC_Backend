<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SettingsController extends BaseApiController
{
    /**
     * Display a listing of all settings.
     */
    public function index(): JsonResponse
    {
        try {
            $settings = Setting::orderBy('key', 'asc')->get()->map(function ($setting) {
                return [
                    'key' => $setting->key,
                    'value' => $setting->value,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Settings retrieved successfully',
                'data' => $settings,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update settings values.
     */
    public function update(Request $request): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'settings' => 'required|array',
                'settings.*.key' => 'required|string',
                'settings.*.value' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $updatedSettings = [];
            
            foreach ($request->input('settings', []) as $settingData) {
                $key = $settingData['key'] ?? null;
                $value = $settingData['value'] ?? null;
                
                if ($key) {
                    // Only update if the setting key exists in the database
                    $setting = Setting::where('key', $key)->first();
                    
                    if ($setting) {
                        $setting->update(['value' => $value ?? '']);
                        $updatedSettings[] = [
                            'key' => $setting->key,
                            'value' => $setting->value,
                        ];
                    }
                }
            }

            DB::commit();

            // Log activity
            logAdminActivity('updated', 'Settings', null, ['updated_keys' => array_column($updatedSettings, 'key')]);

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => $updatedSettings,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}

