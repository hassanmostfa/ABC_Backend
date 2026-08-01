<?php

namespace App\Console\Commands;

use App\Services\ErpOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncErpOrderStatuses extends Command
{
    protected $signature = 'orders:sync-erp-status';

    protected $description = 'Sync pending/processing order statuses from ERP GetOrderStatus';

    public function handle(ErpOrderService $erpOrderService): int
    {
        $this->info('Syncing pending/processing order statuses from ERP...');

        $summary = $erpOrderService->syncPendingAndProcessingOrderStatuses();

        $this->line("Checked: {$summary['checked']}");
        $this->line("Updated: {$summary['updated']}");
        $this->line("Unchanged: {$summary['unchanged']}");
        $this->line("Failed: {$summary['failed']}");

        Log::channel('erp')->info('ERP order status sync completed', [
            'checked' => $summary['checked'],
            'updated' => $summary['updated'],
            'unchanged' => $summary['unchanged'],
            'failed' => $summary['failed'],
        ]);

        foreach ($summary['results'] as $result) {
            if (!($result['success'] ?? false)) {
                $this->warn(sprintf(
                    '  #%s %s: %s',
                    $result['order_id'] ?? '?',
                    $result['order_number'] ?? '?',
                    $result['message'] ?? 'Failed'
                ));
            } elseif ($result['updated'] ?? false) {
                $this->info(sprintf(
                    '  #%s %s: %s → %s (ERP: %s)',
                    $result['order_id'] ?? '?',
                    $result['order_number'] ?? '?',
                    $result['previous_status'] ?? '?',
                    $result['local_status'] ?? '?',
                    $result['erp_status'] ?? '?'
                ));
            }
        }

        return self::SUCCESS;
    }
}
