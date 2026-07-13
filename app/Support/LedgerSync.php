<?php

namespace App\Support;

use App\Models\CashAccount;
use App\Models\FinCategory;
use App\Models\FinTransaction;

/**
 * Menyinkronkan pembayaran AR/AP menjadi transaksi kas (fin_transactions),
 * sehingga arus kas, jurnal, dan buku besar punya satu sumber data.
 */
class LedgerSync
{
    public static function syncInvoicePayment($payment): void
    {
        self::upsert($payment, 'invoice', 'in', 'Penjualan Tour', 'Pembayaran invoice');
    }

    public static function syncBillPayment($payment): void
    {
        self::upsert($payment, 'bill', 'out', 'Biaya Supplier', 'Pembayaran ke supplier');
    }

    public static function remove(string $source, $id): void
    {
        FinTransaction::where('source', $source)->where('source_id', $id)->delete();
    }

    private static function upsert($payment, string $source, string $direction, string $categoryName, string $label): void
    {
        $category = FinCategory::where('name', $categoryName)->where('is_system', true)->first();
        // Akun kas dipilih eksplisit oleh akuntan sejak fitur ini ada; baris
        // lama yang belum punya cash_account_id tetap pakai tebakan lama.
        $account  = $payment->cash_account_id
            ? CashAccount::find($payment->cash_account_id)
            : self::accountForMethod($payment->method ?? null);

        if (! $category || ! $account) {
            return; // fondasi keuangan belum di-seed
        }

        FinTransaction::updateOrCreate(
            ['source' => $source, 'source_id' => $payment->id],
            [
                'date'            => $payment->date,
                'direction'       => $direction,
                'fin_category_id' => $category->id,
                'cash_account_id' => $account->id,
                // Invoice bisa multi-currency → pakai ekuivalen IDR (amount_idr).
                // Buku besar selalu IDR.
                'amount'          => ($source === 'invoice' && (float) ($payment->amount_idr ?? 0) > 0)
                    ? (float) $payment->amount_idr
                    : (float) $payment->amount,
                'description'     => $label . ($payment->notes ? ' — ' . $payment->notes : ''),
                'created_by'      => 'Sistem (auto)',
            ]
        );
    }

    private static function accountForMethod($method): ?CashAccount
    {
        $m = strtolower((string) $method);
        $isCash = str_contains($m, 'cash') || str_contains($m, 'tunai');

        return CashAccount::where('type', $isCash ? 'cash' : 'bank')->orderBy('id')->first()
            ?? CashAccount::orderBy('id')->first();
    }
}
