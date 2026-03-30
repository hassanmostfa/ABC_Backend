<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Mobile\StoreOrderRequest as MobileStoreOrderRequest;

/**
 * Web checkout: same validation as mobile (delivery date and time required).
 */
class StoreOrderRequest extends MobileStoreOrderRequest
{
}
