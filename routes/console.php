<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Digest follow-up harian ke Sales — 07:00 WITA (Manado).
Schedule::command('reminders:digest')
    ->dailyAt('07:00')
    ->timezone('Asia/Makassar');
