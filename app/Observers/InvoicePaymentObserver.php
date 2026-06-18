<?php

namespace App\Observers;

use App\Models\InvoicePayment;
use App\Support\LedgerSync;

class InvoicePaymentObserver
{
    public function saved(InvoicePayment $payment): void
    {
        LedgerSync::syncInvoicePayment($payment);
    }

    public function deleted(InvoicePayment $payment): void
    {
        LedgerSync::remove('invoice', $payment->id);
    }
}
