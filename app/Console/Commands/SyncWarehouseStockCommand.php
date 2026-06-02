<?php

namespace App\Console\Commands;

use App\Models\ProductVariant;
use App\Services\WarehouseStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncWarehouseStockCommand extends Command
{
    protected $signature = 'warehouse:sync-stock {--wh_code= : Warehouse code (defaults to env config)}';

    protected $description = 'Sync product variant quantities from warehouse stock API';

    public function handle(WarehouseStockService $warehouseStockService): int
    {
        if (getSetting('erp_stock_sync_enabled', '1') !== '1') {
            $this->line('');
            $this->warn('[!] ERP stock sync is disabled in settings (erp_stock_sync_enabled = 0). Skipping sync.');
            $this->line('');

            Log::channel('erp')->info('Warehouse stock sync skipped (disabled in settings)');

            return self::SUCCESS;
        }

        $singleWarehouseCode = $this->option('wh_code');
        $warehouseCodes = $singleWarehouseCode
            ? [(string) $singleWarehouseCode]
            : $warehouseStockService->getWarehouseCodes();

        $this->line('');
        $this->info("[*] Warehouse Stock Sync Started");
        $this->line("[>] Warehouse Codes: " . implode(', ', $warehouseCodes));
        $this->line("[~] Fetching stock data from API...");

        $result = count($warehouseCodes) === 1
            ? $warehouseStockService->getStock($warehouseCodes[0])
            : $warehouseStockService->getAggregatedStock($warehouseCodes);

        if (!$result['success']) {
            $this->error("[X] Failed to fetch warehouse stock: " . ($result['error'] ?? 'Unknown error'));
            Log::channel('erp')->error('Warehouse stock sync failed', [
                'warehouse_codes' => $warehouseCodes,
                'warehouse_errors' => $result['warehouse_errors'] ?? null,
                'error' => $result['error'],
            ]);
            return self::FAILURE;
        }

        if (!empty($result['warehouse_errors'])) {
            foreach ($result['warehouse_errors'] as $code => $error) {
                $this->warn("[!] Warehouse {$code} failed: " . ($error ?? 'Unknown error'));
            }
        }

        $this->info("[+] Stock data received successfully");

        $stockData = $result['body']['data'] ?? [];

        if (empty($stockData)) {
            $this->warn("[!] No stock data returned from warehouse API.");
            return self::SUCCESS;
        }

        $this->line("[i] Raw stock entries: " . count($stockData));

        $quantityByItemCode = $warehouseStockService->aggregateQuantitiesByItemCode($stockData);

        $this->line("[i] Unique SKUs aggregated: " . count($quantityByItemCode));
        $this->line("[~] Matching with product variants in database...");

        $apiSkus = array_keys($quantityByItemCode);
        $variants = ProductVariant::whereIn('sku', $apiSkus)->get();

        $this->line("[i] Variants matched in DB: " . $variants->count());

        $updated = 0;
        $skipped = 0;

        foreach ($variants as $variant) {
            $newQuantity = $quantityByItemCode[$variant->sku] ?? null;

            if ($newQuantity === null) {
                $skipped++;
                continue;
            }

            if ((int) $variant->quantity !== $newQuantity) {
                $oldQty = $variant->quantity;
                $variant->update(['quantity' => $newQuantity]);
                $this->line("  [^] {$variant->sku}: {$oldQty} -> {$newQuantity}");
                $updated++;
            } else {
                $skipped++;
            }
        }

        $notFound = count($quantityByItemCode) - $variants->count();

        $this->line('');
        $this->info("[=] Sync Summary:");
        $this->line("  [+] Updated:    {$updated}");
        $this->line("  [-] Unchanged:  {$skipped}");
        $this->line("  [?] Not in DB:  {$notFound}");
        $this->info("[*] Warehouse Stock Sync Completed");
        $this->line('');

        Log::channel('erp')->info('Warehouse stock sync completed', [
            'warehouse_codes' => $warehouseCodes,
            'total_stock_items' => count($stockData),
            'aggregated_skus' => count($quantityByItemCode),
            'variants_found' => $variants->count(),
            'updated' => $updated,
            'skipped' => $skipped,
            'not_in_db' => $notFound,
        ]);

        return self::SUCCESS;
    }
}
