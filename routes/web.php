<?php

use App\Http\Controllers\AgentProductController;
use App\Http\Controllers\AgentProductPriceController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\FinanceLedgerController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChannelManagerController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\TourEmailController;
use App\Http\Controllers\TourHistoryController;
use App\Http\Controllers\TourItineraryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BillPaymentController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\MyJobsController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductPriceController;
use App\Http\Controllers\QuotationItemController;
use App\Http\Controllers\FiscalController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MiceTemplateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\TourItemController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'       => Route::has('login'),
        'canRegister'    => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'     => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {

    // Profile (semua role)
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Dashboard & Pipeline — admin + sales
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('role:admin,sales')
        ->name('dashboard');

    // Kelola Akun — admin only
    Route::resource('users', UserController::class)
        ->except(['show', 'create', 'edit'])
        ->middleware('role:admin');

    // Master Data — Suppliers: admin only
    Route::resource('suppliers', SupplierController::class)
        ->except(['show'])
        ->middleware('role:admin');

    // Channel Manager — internal (admin + sales): review harga travel agent
    Route::middleware('role:admin,sales')->group(function () {
        Route::get('/channel-manager',                       [ChannelManagerController::class, 'index'])->name('channel-manager.index');
        Route::patch('/channel-manager/{product}/approve',   [ChannelManagerController::class, 'approve'])->name('channel-manager.approve');
        Route::patch('/channel-manager/{product}/reject',    [ChannelManagerController::class, 'reject'])->name('channel-manager.reject');
        Route::patch('/channel-manager/{product}/price',     [ChannelManagerController::class, 'updatePrice'])->name('channel-manager.price');
        // Period price approval
        Route::patch('/channel-manager/prices/{productPrice}/approve', [ChannelManagerController::class, 'approvePrice'])->name('channel-manager.price.approve');
        Route::patch('/channel-manager/prices/{productPrice}/reject',  [ChannelManagerController::class, 'rejectPrice'])->name('channel-manager.price.reject');
        Route::patch('/channel-manager/prices/{productPrice}',         [ChannelManagerController::class, 'updatePeriodPrice'])->name('channel-manager.period.price');
    });

    // Produk Saya — travel agent (eksternal): kelola produk supplier sendiri
    Route::middleware('role:travel_agent')->group(function () {
        Route::get('/agent/products',              [AgentProductController::class, 'index'])->name('agent.products.index');
        Route::post('/agent/products',             [AgentProductController::class, 'store'])->name('agent.products.store');
        Route::patch('/agent/products/{product}',  [AgentProductController::class, 'update'])->name('agent.products.update');
        Route::delete('/agent/products/{product}', [AgentProductController::class, 'destroy'])->name('agent.products.destroy');
        // Period prices (agent submit)
        Route::post('/agent/products/{product}/prices',       [AgentProductPriceController::class, 'store'])->name('agent.product-prices.store');
        Route::delete('/agent/product-prices/{productPrice}', [AgentProductPriceController::class, 'destroy'])->name('agent.product-prices.destroy');
    });

    // Template download — harus sebelum resource agar tidak ditangkap {product}
    Route::middleware('role:admin,sales')->group(function () {
        Route::get('/products/template/download',  [ProductController::class, 'downloadTemplate'])->name('products.template.download');
        Route::get('/products/template/suppliers', [ProductController::class, 'exportSuppliers'])->name('products.template.suppliers');
    });

    // Master Data — Products: admin + sales
    Route::resource('products', ProductController::class)
        ->except(['show'])
        ->middleware('role:admin,sales');

    // Product period prices — admin + sales
    Route::middleware('role:admin,sales')->group(function () {
        Route::post('/products/{product}/prices',       [ProductPriceController::class, 'store'])->name('product-prices.store');
        Route::patch('/product-prices/{productPrice}',  [ProductPriceController::class, 'update'])->name('product-prices.update');
        Route::delete('/product-prices/{productPrice}', [ProductPriceController::class, 'destroy'])->name('product-prices.destroy');
    });

    // Master Data — Customers: admin + sales
    Route::resource('customers', CustomerController::class)
        ->middleware('role:admin,sales');

    // Tours — admin + sales
    Route::resource('tours', TourController::class)
        ->except(['show'])
        ->middleware('role:admin,sales');

    Route::middleware('role:admin,sales')->group(function () {
        Route::post('/tours/{tour}/email',      [TourEmailController::class, 'send'])->name('tours.email.send');

        Route::post('/tours/{tour}/histories',              [TourHistoryController::class, 'store'])->name('tours.histories.store');
        Route::delete('/tours/{tour}/histories/{history}',  [TourHistoryController::class, 'destroy'])->name('tours.histories.destroy');

        Route::post('/tours/{tour}/itinerary/days',        [TourItineraryController::class, 'updateDays'])->name('tours.itinerary.days');
        Route::post('/tours/{tour}/itinerary/pdf',         [TourItineraryController::class, 'uploadPdf'])->name('tours.itinerary.pdf.upload');
        Route::delete('/tours/{tour}/itinerary/pdf',       [TourItineraryController::class, 'deletePdf'])->name('tours.itinerary.pdf.delete');
        Route::get('/tours/{tour}/itinerary/pdf/download', [TourItineraryController::class, 'downloadPdf'])->name('tours.itinerary.pdf.download');

        Route::post('/tours/{tour}/itinerary/hours',       [TourItineraryController::class, 'storeHour'])->name('tours.itinerary.hours.store');
        Route::patch('/tours/{tour}/itinerary/hours/{hourId}', [TourItineraryController::class, 'updateHour'])->name('tours.itinerary.hours.update');
        Route::delete('/tours/{tour}/itinerary/hours/{hourId}', [TourItineraryController::class, 'deleteHour'])->name('tours.itinerary.hours.delete');

        Route::post('/tours/{tour}/items',      [TourItemController::class, 'store'])->name('tour-items.store');
        Route::patch('/tour-items/{tourItem}',  [TourItemController::class, 'update'])->name('tour-items.update');
        Route::delete('/tour-items/{tourItem}', [TourItemController::class, 'destroy'])->name('tour-items.destroy');

        Route::get('/tours/{tour}/quotation/download', [QuotationController::class, 'download'])->name('quotation.download');
        Route::get('/tours/{tour}/quotation/preview',  [QuotationController::class, 'preview'])->name('quotation.preview');

        Route::post('/tours/{tour}/quotation-items',          [QuotationItemController::class, 'store'])->name('quotation-items.store');
        Route::patch('/quotation-items/{quotationItem}',      [QuotationItemController::class, 'update'])->name('quotation-items.update');
        Route::delete('/quotation-items/{quotationItem}',     [QuotationItemController::class, 'destroy'])->name('quotation-items.destroy');

        // MICE Templates
        Route::post('/mice-templates',                               [MiceTemplateController::class, 'store'])->name('mice-templates.store');
        Route::patch('/mice-templates/{miceTemplate}',               [MiceTemplateController::class, 'update'])->name('mice-templates.update');
        Route::delete('/mice-templates/{miceTemplate}',              [MiceTemplateController::class, 'destroy'])->name('mice-templates.destroy');
        Route::post('/mice-templates/{miceTemplate}/apply/{tour}',   [MiceTemplateController::class, 'apply'])->name('mice-templates.apply');
        Route::post('/tours/{tour}/save-as-mice-template',           [MiceTemplateController::class, 'saveFromTour'])->name('mice-templates.save-from-tour');

        Route::post('/tours/{tour}/assignments',   [AssignmentController::class, 'store'])->name('assignments.store');
        Route::patch('/assignments/{assignment}',  [AssignmentController::class, 'update'])->name('assignments.update');
        Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');
    });

    // Reminders — admin + sales
    Route::middleware('role:admin,sales')->group(function () {
        Route::get('/reminders',                    [ReminderController::class, 'index'])->name('reminders.index');
        Route::post('/reminders',                   [ReminderController::class, 'store'])->name('reminders.store');
        Route::patch('/reminders/{reminder}',       [ReminderController::class, 'update'])->name('reminders.update');
        Route::patch('/reminders/{reminder}/done',  [ReminderController::class, 'done'])->name('reminders.done');
        Route::delete('/reminders/{reminder}',      [ReminderController::class, 'destroy'])->name('reminders.destroy');
    });

    // Booking operasional — admin + sales + operation
    Route::middleware('role:admin,sales,operation')->group(function () {
        Route::get('/bookings',                  [BookingController::class, 'index'])->name('bookings.index');
        Route::post('/tours/{tour}/bookings',    [BookingController::class, 'store'])->name('bookings.store');
        Route::patch('/bookings/{booking}',      [BookingController::class, 'update'])->name('bookings.update');
        Route::delete('/bookings/{booking}',     [BookingController::class, 'destroy'])->name('bookings.destroy');
    });

    // My Jobs — field (guide, driver, tour_leader)
    Route::middleware('role:guide,driver,tour_leader')->group(function () {
        Route::get('/my-jobs',        [MyJobsController::class, 'index'])->name('my-jobs');
        Route::get('/my-jobs/{tour}', [MyJobsController::class, 'show'])->name('my-jobs.show');
    });

    // Finance — admin + accountant
    Route::middleware('role:admin,accountant')->group(function () {
        Route::get('/finance',             [FinanceController::class, 'index'])->name('finance.index');

        // Buku kas / akuntansi — pencatatan pendapatan & pengeluaran
        Route::get('/finance/cash-flow',     [FinanceLedgerController::class, 'cashFlow'])->name('finance.cashflow');
        Route::get('/finance/journal',       [FinanceLedgerController::class, 'journal'])->name('finance.journal');
        Route::get('/finance/ledger',        [FinanceLedgerController::class, 'ledger'])->name('finance.ledger');
        Route::get('/finance/recap',         [FinanceLedgerController::class, 'recap'])->name('finance.recap');
        Route::get('/finance/balance-sheet', [FinanceLedgerController::class, 'balanceSheet'])->name('finance.balance-sheet');
        Route::get('/finance/income-statement',     [FinanceLedgerController::class, 'incomeStatement'])->name('finance.income-statement');

        // Aset Tetap
        Route::get('/finance/fixed-assets',                    [FixedAssetController::class, 'index'])->name('finance.fixed-assets');
        Route::post('/finance/fixed-assets',                   [FixedAssetController::class, 'store'])->name('fixed-assets.store');
        Route::patch('/finance/fixed-assets/{fixedAsset}',    [FixedAssetController::class, 'update'])->name('fixed-assets.update');
        Route::delete('/finance/fixed-assets/{fixedAsset}',   [FixedAssetController::class, 'destroy'])->name('fixed-assets.destroy');

        // Koreksi Fiskal (PPh Badan)
        Route::get('/finance/fiscal',                          [FiscalController::class, 'index'])->name('finance.fiscal');
        Route::get('/finance/fiscal/pdf',                      [FiscalController::class, 'pdf'])->name('finance.fiscal.pdf');
        Route::post('/finance/fiscal/corrections',             [FiscalController::class, 'store'])->name('fiscal.corrections.store');
        Route::patch('/finance/fiscal/corrections/{fiscalCorrection}', [FiscalController::class, 'update'])->name('fiscal.corrections.update');
        Route::delete('/finance/fiscal/corrections/{fiscalCorrection}',[FiscalController::class, 'destroy'])->name('fiscal.corrections.destroy');

        // Hutang & Pinjaman
        Route::get('/finance/loans',              [LoanController::class, 'index'])->name('finance.loans');
        Route::post('/finance/loans',             [LoanController::class, 'store'])->name('loans.store');
        Route::patch('/finance/loans/{loan}',     [LoanController::class, 'update'])->name('loans.update');
        Route::delete('/finance/loans/{loan}',    [LoanController::class, 'destroy'])->name('loans.destroy');
        Route::patch('/finance/settings',         [LoanController::class, 'updateSetting'])->name('finance.settings.update');
        Route::get('/finance/account-balances', [FinanceLedgerController::class, 'accountBalances'])->name('finance.account-balances');

        // Unduh PDF laporan keuangan
        Route::get('/finance/account-balances/pdf',    [FinanceLedgerController::class, 'accountBalancesPdf'])->name('finance.account-balances.pdf');
        Route::get('/finance/balance-sheet/pdf',       [FinanceLedgerController::class, 'balanceSheetPdf'])->name('finance.balance-sheet.pdf');
        Route::get('/finance/income-statement/pdf',    [FinanceLedgerController::class, 'incomeStatementPdf'])->name('finance.income-statement.pdf');
        Route::get('/finance/journal/pdf',          [FinanceLedgerController::class, 'journalPdf'])->name('finance.journal.pdf');
        Route::get('/finance/ledger/pdf',           [FinanceLedgerController::class, 'ledgerPdf'])->name('finance.ledger.pdf');
        Route::get('/finance/recap/pdf',            [FinanceLedgerController::class, 'recapPdf'])->name('finance.recap.pdf');
        Route::get('/finance/transactions',  [FinanceLedgerController::class, 'transactions'])->name('finance.transactions');
        Route::post('/finance/transactions', [FinanceLedgerController::class, 'storeTransaction'])->name('finance.transactions.store');
        Route::patch('/finance/transactions/{finTransaction}',  [FinanceLedgerController::class, 'updateTransaction'])->name('finance.transactions.update');
        Route::delete('/finance/transactions/{finTransaction}', [FinanceLedgerController::class, 'destroyTransaction'])->name('finance.transactions.destroy');
        Route::post('/finance/categories',   [FinanceLedgerController::class, 'storeCategory'])->name('finance.categories.store');
        Route::patch('/finance/categories/{finCategory}',  [FinanceLedgerController::class, 'updateCategory'])->name('finance.categories.update');
        Route::delete('/finance/categories/{finCategory}', [FinanceLedgerController::class, 'destroyCategory'])->name('finance.categories.destroy');
        Route::post('/finance/cash-accounts', [FinanceLedgerController::class, 'storeCashAccount'])->name('finance.cash-accounts.store');
        Route::patch('/finance/cash-accounts/{cashAccount}',  [FinanceLedgerController::class, 'updateCashAccount'])->name('finance.cash-accounts.update');
        Route::delete('/finance/cash-accounts/{cashAccount}', [FinanceLedgerController::class, 'destroyCashAccount'])->name('finance.cash-accounts.destroy');

        // Rekening pembayaran (tampil di invoice) — dikelola akuntan
        Route::get('/finance/bank-accounts',                   [BankAccountController::class, 'index'])->name('bank-accounts.index');
        Route::post('/finance/bank-accounts',                  [BankAccountController::class, 'store'])->name('bank-accounts.store');
        Route::patch('/finance/bank-accounts/{bankAccount}',   [BankAccountController::class, 'update'])->name('bank-accounts.update');
        Route::delete('/finance/bank-accounts/{bankAccount}',  [BankAccountController::class, 'destroy'])->name('bank-accounts.destroy');

        Route::get('/finance/{tour}',      [FinanceController::class, 'tour'])->name('finance.tour');

        // AR
        Route::post('/finance/{tour}/invoices',           [InvoiceController::class, 'store'])->name('invoices.store');
        Route::patch('/finance/invoices/{invoice}',       [InvoiceController::class, 'update'])->name('invoices.update');
        Route::delete('/finance/invoices/{invoice}',      [InvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::get('/finance/invoices/{invoice}/preview',  [InvoiceController::class, 'preview'])->name('invoices.preview');
        Route::get('/finance/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
        Route::post('/finance/invoices/{invoice}/payments',          [InvoicePaymentController::class, 'store'])->name('invoice-payments.store');
        Route::delete('/finance/invoice-payments/{invoicePayment}',  [InvoicePaymentController::class, 'destroy'])->name('invoice-payments.destroy');

        // AP
        Route::post('/finance/{tour}/bills',        [BillController::class, 'store'])->name('bills.store');
        Route::patch('/finance/bills/{bill}',       [BillController::class, 'update'])->name('bills.update');
        Route::delete('/finance/bills/{bill}',      [BillController::class, 'destroy'])->name('bills.destroy');
        Route::post('/finance/bills/{bill}/payments',      [BillPaymentController::class, 'store'])->name('bill-payments.store');
        Route::delete('/finance/bill-payments/{billPayment}', [BillPaymentController::class, 'destroy'])->name('bill-payments.destroy');
    });
});

// Manifest publik — opsional untuk orang eksternal tanpa akun
Route::get('/manifest/{tour}', [ManifestController::class, 'show'])
    ->name('manifest')
    ->middleware('signed');

require __DIR__.'/auth.php';
