<?php

use App\Http\Controllers\BillController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\TourEmailController;
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

    // Master Data — Products: admin + sales
    Route::resource('products', ProductController::class)
        ->except(['show'])
        ->middleware('role:admin,sales');

    // Master Data — Customers: admin + sales
    Route::resource('customers', CustomerController::class)
        ->except(['show'])
        ->middleware('role:admin,sales');

    // Tours — admin + sales
    Route::resource('tours', TourController::class)
        ->except(['show'])
        ->middleware('role:admin,sales');

    Route::middleware('role:admin,sales')->group(function () {
        Route::post('/tours/{tour}/email',      [TourEmailController::class, 'send'])->name('tours.email.send');

        Route::post('/tours/{tour}/itinerary/days',        [TourItineraryController::class, 'updateDays'])->name('tours.itinerary.days');
        Route::post('/tours/{tour}/itinerary/pdf',         [TourItineraryController::class, 'uploadPdf'])->name('tours.itinerary.pdf.upload');
        Route::delete('/tours/{tour}/itinerary/pdf',       [TourItineraryController::class, 'deletePdf'])->name('tours.itinerary.pdf.delete');
        Route::get('/tours/{tour}/itinerary/pdf/download', [TourItineraryController::class, 'downloadPdf'])->name('tours.itinerary.pdf.download');

        Route::post('/tours/{tour}/items',      [TourItemController::class, 'store'])->name('tour-items.store');
        Route::patch('/tour-items/{tourItem}',  [TourItemController::class, 'update'])->name('tour-items.update');
        Route::delete('/tour-items/{tourItem}', [TourItemController::class, 'destroy'])->name('tour-items.destroy');

        Route::get('/tours/{tour}/quotation/download', [QuotationController::class, 'download'])->name('quotation.download');
        Route::get('/tours/{tour}/quotation/preview',  [QuotationController::class, 'preview'])->name('quotation.preview');

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

    // My Jobs — field (guide, driver, tour_leader)
    Route::middleware('role:guide,driver,tour_leader')->group(function () {
        Route::get('/my-jobs',        [MyJobsController::class, 'index'])->name('my-jobs');
        Route::get('/my-jobs/{tour}', [MyJobsController::class, 'show'])->name('my-jobs.show');
    });

    // Finance — admin + accountant
    Route::middleware('role:admin,accountant')->group(function () {
        Route::get('/finance',             [FinanceController::class, 'index'])->name('finance.index');
        Route::get('/finance/{tour}',      [FinanceController::class, 'tour'])->name('finance.tour');

        // AR
        Route::post('/finance/{tour}/invoices',           [InvoiceController::class, 'store'])->name('invoices.store');
        Route::patch('/finance/invoices/{invoice}',       [InvoiceController::class, 'update'])->name('invoices.update');
        Route::delete('/finance/invoices/{invoice}',      [InvoiceController::class, 'destroy'])->name('invoices.destroy');
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
