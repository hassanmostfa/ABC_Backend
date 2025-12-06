<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ActivityLogService
{
    protected string $logPath;

    public function __construct()
    {
        $this->logPath = storage_path('logs/activity.log');
    }

    /**
     * Get all activity logs with pagination and filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedLogs(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $logs = $this->readLogs();
        $logs = $this->applyFilters($logs, $filters);
        
        // Sort by timestamp descending (newest first)
        $logs = $logs->sortByDesc(function ($log) {
            return $log['timestamp'] ?? '';
        })->values();

        $currentPage = request()->get('page', 1);
        $items = $logs->forPage($currentPage, $perPage)->values();
        
        return new LengthAwarePaginator(
            $items,
            $logs->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Read logs from single activity log file
     *
     * @return Collection
     */
    protected function readLogs(): Collection
    {
        $logs = collect();
        
        if (!File::exists($this->logPath)) {
            return $logs;
        }

        $content = File::get($this->logPath);
        $lines = explode("\n", $content);
        
        $currentLog = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            // Check if line starts a new log entry (contains timestamp)
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                // Save previous log if exists
                if ($currentLog !== null) {
                    $parsedLog = $this->parseLogEntry($currentLog);
                    if ($parsedLog) {
                        $logs->push($parsedLog);
                    }
                }
                $currentLog = $line;
            } elseif ($currentLog !== null) {
                // Append to current log entry
                $currentLog .= "\n" . $line;
            }
        }
        
        // Don't forget the last log in the file
        if ($currentLog !== null) {
            $parsedLog = $this->parseLogEntry($currentLog);
            if ($parsedLog) {
                $logs->push($parsedLog);
            }
        }
        
        return $logs;
    }

    /**
     * Parse a log entry
     *
     * @param string $logEntry
     * @return array|null
     */
    protected function parseLogEntry(string $logEntry): ?array
    {
        // Clean the log entry
        $logEntry = trim($logEntry);
        
        // Try to extract JSON data from the log entry (Laravel logs JSON in context)
        // Laravel log format: [2024-12-06 17:30:45] local.INFO: Admin Activity {"timestamp":"...","admin_id":1,...}
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*local\.(\w+):\s*Admin Activity\s*(\{.*\})/s', $logEntry, $matches)) {
            $jsonString = $matches[3] ?? '';
            
            $jsonData = json_decode($jsonString, true);
            if ($jsonData && is_array($jsonData) && isset($jsonData['action']) && isset($jsonData['model'])) {
                return $jsonData;
            }
        }
        
        // Alternative: Try to find any JSON object in the log entry (more flexible)
        // This pattern matches JSON objects that span multiple lines
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $logEntry, $matches)) {
            $jsonString = $matches[0];
            $jsonData = json_decode($jsonString, true);
            if ($jsonData && is_array($jsonData) && isset($jsonData['action']) && isset($jsonData['model'])) {
                return $jsonData;
            }
        }
        
        // Another alternative: Try to find JSON starting from the first {
        $jsonStart = strpos($logEntry, '{');
        if ($jsonStart !== false) {
            $jsonString = substr($logEntry, $jsonStart);
            // Try to find the end of JSON (last })
            $jsonEnd = strrpos($jsonString, '}');
            if ($jsonEnd !== false) {
                $jsonString = substr($jsonString, 0, $jsonEnd + 1);
                $jsonData = json_decode($jsonString, true);
                if ($jsonData && is_array($jsonData) && isset($jsonData['action']) && isset($jsonData['model'])) {
                    return $jsonData;
                }
            }
        }
        
        // Fallback: try to parse basic Laravel log format
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*local\.(\w+):\s*(.*)/s', $logEntry, $matches)) {
            $timestamp = $matches[1];
            $level = $matches[2];
            $message = $matches[3] ?? '';
            
            // Try to extract JSON from message
            if (preg_match('/\{.*\}/s', $message, $jsonMatches)) {
                $jsonData = json_decode($jsonMatches[0], true);
                if ($jsonData && is_array($jsonData)) {
                    return $jsonData;
                }
            }
            
            return [
                'timestamp' => Carbon::parse($timestamp)->toIso8601String(),
                'level' => $level,
                'message' => trim($message),
            ];
        }
        
        return null;
    }

    /**
     * Apply filters to logs
     *
     * @param Collection $logs
     * @param array $filters
     * @return Collection
     */
    protected function applyFilters(Collection $logs, array $filters): Collection
    {
        // Filter by admin_id
        if (isset($filters['admin_id']) && $filters['admin_id'] !== '') {
            $logs = $logs->filter(function ($log) use ($filters) {
                return isset($log['admin_id']) && $log['admin_id'] == $filters['admin_id'];
            });
        }

        // Filter by action
        if (isset($filters['action']) && $filters['action'] !== '') {
            $logs = $logs->filter(function ($log) use ($filters) {
                return isset($log['action']) && 
                       stripos($log['action'], $filters['action']) !== false;
            });
        }

        // Filter by model
        if (isset($filters['model']) && $filters['model'] !== '') {
            $logs = $logs->filter(function ($log) use ($filters) {
                return isset($log['model']) && 
                       stripos($log['model'], $filters['model']) !== false;
            });
        }

        // Filter by date range
        if (isset($filters['date_from']) && $filters['date_from'] !== '') {
            $logs = $logs->filter(function ($log) use ($filters) {
                if (!isset($log['timestamp'])) {
                    return false;
                }
                $logDate = Carbon::parse($log['timestamp']);
                return $logDate->greaterThanOrEqualTo(Carbon::parse($filters['date_from']));
            });
        }

        if (isset($filters['date_to']) && $filters['date_to'] !== '') {
            $logs = $logs->filter(function ($log) use ($filters) {
                if (!isset($log['timestamp'])) {
                    return false;
                }
                $logDate = Carbon::parse($log['timestamp']);
                return $logDate->lessThanOrEqualTo(Carbon::parse($filters['date_to'])->endOfDay());
            });
        }

        // Search in admin name or email
        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = strtolower($filters['search']);
            $logs = $logs->filter(function ($log) use ($search) {
                $adminName = isset($log['admin_name']) ? strtolower($log['admin_name']) : '';
                $adminEmail = isset($log['admin_email']) ? strtolower($log['admin_email']) : '';
                $action = isset($log['action']) ? strtolower($log['action']) : '';
                $model = isset($log['model']) ? strtolower($log['model']) : '';
                
                return stripos($adminName, $search) !== false ||
                       stripos($adminEmail, $search) !== false ||
                       stripos($action, $search) !== false ||
                       stripos($model, $search) !== false;
            });
        }

        return $logs;
    }
}

