<?php

namespace App\Traits;

use App\Models\Offer;
use Carbon\Carbon;

trait ChecksOfferActive
{
    /**
     * Check if an offer is currently active
     *
     * @param Offer|null $offer
     * @return bool
     */
    protected function isOfferActive(?Offer $offer): bool
    {
        if (!$offer) {
            return false;
        }

        $now = Carbon::now();
        return $offer->is_active === true 
            && $offer->offer_start_date <= $now 
            && $offer->offer_end_date >= $now;
    }

    /**
     * Validate and get active offer
     *
     * @param int|null $offerId
     * @return Offer|null
     * @throws \Exception
     */
    protected function getActiveOffer(?int $offerId): ?Offer
    {
        if (!$offerId) {
            return null;
        }

        $offer = Offer::with(['rewards.product', 'rewards.productVariant'])->find($offerId);
        
        if (!$offer) {
            throw new \Exception('Offer not found');
        }

        if (!$this->isOfferActive($offer)) {
            throw new \Exception('Offer is not active. Please check the offer dates and status.');
        }

        return $offer;
    }
}

