<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

if (!function_exists('logAdminActivity')) {
    /**
     * Helper function to log admin activities
     * 
     * @param string $action The action performed (e.g., 'created', 'updated', 'deleted')
     * @param string $model The model name (e.g., 'Product', 'Category')
     * @param int|null $modelId The ID of the model
     * @param array|null $data Additional data to log
     * @return void
     */
    function logAdminActivity(string $action, string $model, ?int $modelId = null, ?array $data = null): void
    {
        $admin = Auth::user();
        
        $logData = [
            'timestamp' => Carbon::now()->toIso8601String(),
            'admin_id' => $admin?->id,
            'admin_name' => $admin?->name,
            'admin_email' => $admin?->email,
            'action' => $action,
            'model' => $model,
            'model_id' => $modelId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ];

        if ($data !== null) {
            $logData['data'] = $data;
        }

        // Log to activity log file
        Log::channel('activity')->info('Admin Activity', $logData);
    }
}

