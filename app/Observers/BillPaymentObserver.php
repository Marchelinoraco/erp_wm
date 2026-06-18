<?php

namespace App\Observers;

use App\Models\BillPayment;
use App\Support\LedgerSync;

class BillPaymentObserver
{
    public function saved(BillPayment $payment): void
    {
        LedgerSync::syncBillPayment($payment);
    }

    public function deleted(BillPayment $payment): void
    {
        LedgerSync::remove('bill', $payment->id);
    }
}
