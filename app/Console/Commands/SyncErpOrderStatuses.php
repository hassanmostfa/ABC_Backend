<?php

namespace App\Console\Commands;

use App\Services\ErpOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncErpOrderStatuses extends Command
{
    protected $signature = 'orders:sync-erp-status {--limit= : Max orders to sync this run (default from ERP_STATUS_SYNC_LIMIT)}';

    protected $description = 'Sync pending/processing/rejected ERP-sent order statuses (limited batch per run)';

    public function handle(ErpOrderService $erpOrderService): int
    {
        $limit = $this->option('limit');
        $limit = $limit !== null && $limit !== '' ? (int) $limit : null;

        $this->info('Syncing pending/processing/rejected ERP-sent order statuses...');

        $summary = $erpOrderService->syncPendingAndProcessingOrderStatuses($limit);

        $this->line("Eligible (sent to ERP): {$summary['eligible_total']}");
        $this->line("Limit this run: {$summary['limit']}");
        $this->line("Checked: {$summary['checked']}");
        $this->line("Updated: {$summary['updated']}");
        $this->line("Unchanged: {$summary['unchanged']}");
        $this->line("Failed: {$summary['failed']}");

        Log::channel('erp')->info('ERP order status sync completed', [
            'checked' => $summary['checked'],
            'updated' => $summary['updated'],
            'unchanged' => $summary['unchanged'],
            'failed' => $summary['failed'],
            'limit' => $summary['limit'],
            'eligible_total' => $summary['eligible_total'],
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
