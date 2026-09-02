<?php

namespace App\Console\Commands;

use App\Services\ERP\ErpOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncErpOrderStatuses extends Command
{
    protected $signature = 'orders:sync-erp-status {--limit= : Max orders to sync this run (default from ERP_STATUS_SYNC_LIMIT)}';

    protected $description = 'Sync ERP-sent regular and subscription orders from ERP';

    public function handle(ErpOrderService $erpOrderService): int
    {
        $limit = $this->option('limit');
        $limit = $limit !== null && $limit !== '' ? (int) $limit : null;

        $this->info('Syncing ERP-sent order statuses (regular + subscription)...');

        $summary = $erpOrderService->syncPendingAndProcessingOrderStatuses($limit);

        $this->line("Limit this run (per type): {$summary['limit']}");
        $this->line('--- Regular orders ---');
        $this->line("Eligible: {$summary['orders']['eligible_total']}");
        $this->line("Checked: {$summary['orders']['checked']}");
        $this->line("Updated: {$summary['orders']['updated']}");
        $this->line("Unchanged: {$summary['orders']['unchanged']}");
        $this->line("Failed: {$summary['orders']['failed']}");
        $this->line('--- Subscription orders ---');
        $this->line("Eligible: {$summary['subscription_orders']['eligible_total']}");
        $this->line("Checked: {$summary['subscription_orders']['checked']}");
        $this->line("Updated: {$summary['subscription_orders']['updated']}");
        $this->line("Unchanged: {$summary['subscription_orders']['unchanged']}");
        $this->line("Failed: {$summary['subscription_orders']['failed']}");
        $this->line('--- Total ---');
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
            'orders' => $summary['orders'],
            'subscription_orders' => $summary['subscription_orders'],
        ]);

        foreach ($summary['results'] as $result) {
            $label = ($result['order_type'] ?? 'order') === 'subscription_order' ? 'SUB' : 'ORD';

            if (!($result['success'] ?? false)) {
                $this->warn(sprintf(
                    '  [%s] #%s %s: %s',
                    $label,
                    $result['order_id'] ?? '?',
                    $result['order_number'] ?? '?',
                    $result['message'] ?? 'Failed'
                ));
            } elseif ($result['updated'] ?? false) {
                $this->info(sprintf(
                    '  [%s] #%s %s: %s → %s (ERP: %s)',
                    $label,
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
