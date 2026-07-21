<?php

namespace Tests\Feature;

use App\Mail\TourEmail;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TourEmailQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_tour_email_queues_it_instead_of_sending_synchronously(): void
    {
        Mail::fake();

        $sales = User::create([
            'name'     => 'Sales A',
            'email'    => 'sales.a@test.local',
            'password' => bcrypt('password'),
            'role'     => 'sales',
        ]);
        $tour = Tour::create(['pax' => 1, 'type' => 'tour', 'status' => 'inquiry', 'created_by' => $sales->id]);

        $this->actingAs($sales)->post(route('tours.email.send', $tour), [
            'to'      => 'customer@example.com',
            'subject' => 'Penawaran Tour',
            'body'    => 'Isi email penawaran.',
        ])->assertRedirect();

        Mail::assertQueued(TourEmail::class, fn (TourEmail $mail) => $mail->hasTo('customer@example.com'));
        Mail::assertNotSent(TourEmail::class);
    }
}
