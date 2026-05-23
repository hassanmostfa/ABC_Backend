<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Collection;

/**
 * Thrown when a customer tries to create an online_link order while another unpaid checkout exists.
 */
class PendingOnlineInvoiceException extends Exception
{
    /**
     * @param Collection<int, \App\Models\Order> $pendingOrders
     * @param Collection<int, \App\Models\OrderCheckout> $pendingCheckouts
     */
    public function __construct(
        string $message,
        public readonly Collection $pendingOrders,
        public readonly Collection $pendingCheckouts = new Collection(),
    ) {
        parent::__construct($message, 409);
    }
}
