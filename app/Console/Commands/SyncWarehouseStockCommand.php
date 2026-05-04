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
        $warehouseCode = $this->option('wh_code')
            ?: config('services.warehouse_stock.default_code', 'FGW1');

        $this->line('');
        $this->info("[*] Warehouse Stock Sync Started");
        $this->line("[>] Warehouse Code: {$warehouseCode}");
        $this->line("[~] Fetching stock data from API...");

        $result = $warehouseStockService->getStock($warehouseCode);

        if (!$result['success']) {
            $this->error("[X] Failed to fetch warehouse stock: " . ($result['error'] ?? 'Unknown error'));
            Log::channel('erp')->error('Warehouse stock sync failed', [
                'warehouse_code' => $warehouseCode,
                'error' => $result['error'],
            ]);
            return self::FAILURE;
        }

        $this->info("[+] Stock data received successfully");

        $stockData = $result['body']['data'] ?? [];

        if (empty($stockData)) {
            $this->warn("[!] No stock data returned from warehouse API.");
            return self::SUCCESS;
        }

        $this->line("[i] Raw stock entries: " . count($stockData));

        $quantityByItemCode = [];
        foreach ($stockData as $item) {
            $itemCode = $item['itemCode'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($itemCode === null) {
                continue;
            }

            if (!isset($quantityByItemCode[$itemCode])) {
                $quantityByItemCode[$itemCode] = 0;
            }
            $quantityByItemCode[$itemCode] += $quantity;
        }

        $this->line("[i] Unique SKUs aggregated: " . count($quantityByItemCode));
        $this->line("[~] Matching with product variants in database...");

        $skus = array_keys($quantityByItemCode);
        $variants = ProductVariant::whereIn('sku', $skus)->get();

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
            'warehouse_code' => $warehouseCode,
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
