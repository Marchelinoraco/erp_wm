<?php

namespace App\Providers;

use App\Contracts\BrevoGateway;
use App\Models\BillPayment;
use App\Models\InvoicePayment;
use App\Observers\BillPaymentObserver;
use App\Observers\InvoicePaymentObserver;
use App\Services\Brevo\BrevoClient;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BrevoGateway::class, BrevoClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // AR/AP → transaksi kas otomatis (sumber tunggal arus kas/jurnal/buku besar)
        InvoicePayment::observe(InvoicePaymentObserver::class);
        BillPayment::observe(BillPaymentObserver::class);
    }
}
