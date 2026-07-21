<?php

namespace App\Console\Commands;

use App\Mail\ReminderDigestMail;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendReminderDigest extends Command
{
    protected $signature = 'reminders:digest';

    protected $description = 'Kirim email digest harian ke tiap pemilik reminder yang follow-up-nya jatuh tempo/terlewat.';

    public function handle(): int
    {
        // Reminder belum selesai, sudah jatuh tempo (hari ini atau terlewat),
        // dan belum masuk digest hari ini (anti-dobel bila command jalan >1x).
        $due = Reminder::query()
            ->where('is_done', false)
            ->whereDate('remind_at', '<=', today())
            ->where(fn ($q) => $q->whereNull('notified_at')->orWhereDate('notified_at', '<', today()))
            ->get()
            ->groupBy('user_id');

        $sent = 0;

        foreach ($due as $userId => $reminders) {
            $user = User::find($userId);

            if (! $user) {
                continue;
            }

            Mail::to($user->email)->queue(new ReminderDigestMail($user, $reminders));

            Reminder::whereIn('id', $reminders->pluck('id'))->update(['notified_at' => now()]);

            $sent++;
        }

        $this->info("Digest terkirim ke {$sent} pengguna.");

        return self::SUCCESS;
    }
}
