<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Collection;

/**
 * Thrown when a customer tries to create an online_link order while another unpaid invoice exists.
 */
class PendingOnlineInvoiceException extends Exception
{
    /**
     * @param Collection<int, \App\Models\Order> $pendingOrders
     */
    public function __construct(
        string $message,
        public readonly Collection $pendingOrders
    ) {
        parent::__construct($message, 409);
    }
}
