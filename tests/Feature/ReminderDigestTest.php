<?php

namespace Tests\Feature;

use App\Mail\ReminderDigestMail;
use App\Models\Reminder;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderDigestTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name, string $role = 'sales'): User
    {
        return User::create([
            'name'     => $name,
            'email'    => strtolower(str_replace(' ', '.', $name)) . '@test.local',
            'password' => bcrypt('password'),
            'role'     => $role,
        ]);
    }

    public function test_digest_sends_one_email_per_sales_with_only_their_due_reminders(): void
    {
        Mail::fake();

        $salesA = $this->makeUser('Sales A');
        $salesB = $this->makeUser('Sales B');
        $tour   = Tour::create(['pax' => 1, 'type' => 'tour', 'created_by' => $salesA->id]);

        // A: satu jatuh tempo hari ini (masuk), satu sudah selesai (dikecualikan),
        // satu masih di masa depan (dikecualikan).
        Reminder::create(['user_id' => $salesA->id, 'tour_id' => $tour->id, 'title' => 'Follow up due', 'remind_at' => today(), 'is_done' => false]);
        Reminder::create(['user_id' => $salesA->id, 'title' => 'Sudah selesai', 'remind_at' => today(), 'is_done' => true]);
        Reminder::create(['user_id' => $salesA->id, 'title' => 'Masa depan', 'remind_at' => today()->addDay(), 'is_done' => false]);

        // B: satu terlewat (masuk).
        Reminder::create(['user_id' => $salesB->id, 'title' => 'Follow up overdue', 'remind_at' => today()->subDays(3), 'is_done' => false]);

        $this->artisan('reminders:digest')->assertSuccessful();

        Mail::assertQueued(ReminderDigestMail::class, 2);
        Mail::assertQueued(ReminderDigestMail::class, fn (ReminderDigestMail $mail) =>
            $mail->hasTo($salesA->email) && $mail->reminders->count() === 1);
        Mail::assertQueued(ReminderDigestMail::class, fn (ReminderDigestMail $mail) =>
            $mail->hasTo($salesB->email) && $mail->reminders->count() === 1);
    }

    public function test_digest_skips_sales_with_no_due_reminders(): void
    {
        Mail::fake();

        $salesA = $this->makeUser('Sales A');
        Reminder::create(['user_id' => $salesA->id, 'title' => 'Selesai', 'remind_at' => today(), 'is_done' => true]);

        $this->artisan('reminders:digest')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_digest_marks_notified_and_does_not_resend_same_day(): void
    {
        Mail::fake();

        $salesA = $this->makeUser('Sales A');
        $reminder = Reminder::create(['user_id' => $salesA->id, 'title' => 'Follow up', 'remind_at' => today(), 'is_done' => false]);

        $this->artisan('reminders:digest')->assertSuccessful();
        Mail::assertQueued(ReminderDigestMail::class, 1);
        $this->assertNotNull($reminder->fresh()->notified_at, 'Reminder yang masuk digest harus ditandai notified_at.');

        // Jalankan lagi di hari yang sama → tidak boleh dobel kirim.
        $this->artisan('reminders:digest')->assertSuccessful();
        Mail::assertQueued(ReminderDigestMail::class, 1);
    }
}
