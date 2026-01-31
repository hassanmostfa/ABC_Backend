<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Setting;
use App\Models\SettingTranslation;
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
            $settings = Setting::with('translations')->orderBy('key', 'asc')->get()->map(function ($setting) {
                $data = [
                    'key' => $setting->key,
                    'value' => $setting->value,
                ];

                if (in_array($setting->key, Setting::TRANSLATABLE_KEYS)) {
                    $data['translations'] = [
                        'en' => $setting->translations->firstWhere('locale', 'en')?->value ?? '',
                        'ar' => $setting->translations->firstWhere('locale', 'ar')?->value ?? '',
                    ];
                }

                return $data;
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
                'settings.*.translations' => 'nullable|array',
                'settings.*.translations.en' => 'nullable|string',
                'settings.*.translations.ar' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $updatedSettings = [];

            foreach ($request->input('settings', []) as $settingData) {
                $key = $settingData['key'] ?? null;
                $value = $settingData['value'] ?? null;
                $translations = $settingData['translations'] ?? null;

                if ($key) {
                    $setting = Setting::with('translations')->where('key', $key)->first();

                    if ($setting) {
                        if (in_array($key, Setting::TRANSLATABLE_KEYS) && $translations !== null) {
                            foreach (['en', 'ar'] as $locale) {
                                $translationValue = $translations[$locale] ?? '';
                                SettingTranslation::updateOrCreate(
                                    ['setting_id' => $setting->id, 'locale' => $locale],
                                    ['value' => $translationValue]
                                );
                            }
                        } else {
                            $setting->update(['value' => $value ?? '']);
                        }

                        $setting->load('translations');
                        $updatedData = [
                            'key' => $setting->key,
                            'value' => $setting->value,
                        ];
                        if (in_array($setting->key, Setting::TRANSLATABLE_KEYS)) {
                            $updatedData['translations'] = [
                                'en' => $setting->translations->firstWhere('locale', 'en')?->value ?? '',
                                'ar' => $setting->translations->firstWhere('locale', 'ar')?->value ?? '',
                            ];
                        }
                        $updatedSettings[] = $updatedData;
                    }
                }
            }

            DB::commit();

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

