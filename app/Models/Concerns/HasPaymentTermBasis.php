<?php

namespace App\Models\Concerns;

use App\Models\DeliveryProjectPaymentTerm;

/**
 * Basis amount sebuah termin TOP — dipakai bersama oleh Delivery Project
 * (DeliveryProjectPaymentTerm) dan Delivery Support (DeliverySupportPaymentTerm).
 * Rumusnya satu sumber: DeliveryProjectPaymentTerm::amountFor().
 *
 * Model pemakai wajib punya kolom: basis, payment_percentage, amount.
 */
trait HasPaymentTermBasis
{
    public function effectiveAmount($revenue): float
    {
        return DeliveryProjectPaymentTerm::amountFor($this->basis, $this->payment_percentage, $this->amount, $revenue);
    }

    public function isFixed(): bool
    {
        return $this->basis === 'fixed';
    }

    public function isLineItemShare(): bool
    {
        return $this->basis === 'line_item';
    }

    /** Basis % dari revenue (termasuk data lama tanpa basis). */
    public function isRevenueShare(): bool
    {
        return !$this->isFixed() && !$this->isLineItemShare();
    }
}
