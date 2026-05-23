<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\ErpCustomerService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchErpCustomerJob
{
    use Dispatchable, Queueable, SerializesModels;

    public function __construct(
        public int $customerId,
        public string $source
    ) {
    }

    public function handle(ErpCustomerService $erpCustomerService): void
    {
        $customer = Customer::find($this->customerId);
        if (!$customer) {
            return;
        }

        try {
            $erpCustomerService->dispatchAfterCustomerCreated($customer, $this->source);
        } catch (\Throwable $e) {
            Log::warning('DispatchErpCustomerJob failed', [
                'customer_id' => $this->customerId,
                'source'      => $this->source,
                'message'     => $e->getMessage(),
            ]);
        }
    }
}
