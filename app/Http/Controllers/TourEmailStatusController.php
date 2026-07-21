<?php

namespace App\Http\Controllers;

use App\Contracts\BrevoGateway;
use App\Models\Tour;
use App\Services\Brevo\BrevoException;
use App\Services\Brevo\BrevoNotConfiguredException;
use Illuminate\Http\JsonResponse;

/**
 * Status email terkirim (delivered/opened/bounced) untuk customer sebuah tour.
 *
 * Endpoint terpisah & dipanggil saat dibutuhkan — sengaja TIDAK ditempel di
 * TourController@edit supaya halaman Tours/Edit tidak melambat menunggu Brevo.
 *
 * Batasan Brevo: aktivitas hanya tersimpan ±30 hari terakhir, dan korelasinya
 * per alamat email (bukan per email yang dikirim).
 */
class TourEmailStatusController extends Controller
{
    public function __construct(private BrevoGateway $brevo) {}

    public function __invoke(Tour $tour): JsonResponse
    {
        abort_unless($tour->isAccessibleBy(auth()->user()), 403);

        $email = $tour->customer?->email;

        $payload = [
            'configured' => true,
            'email'      => $email,
            'summary'    => [],
            'lastEventAt' => null,
            'error'      => null,
        ];

        if (blank($email)) {
            return response()->json($payload);
        }

        try {
            $events = $this->brevo->emailEvents($email);
        } catch (BrevoNotConfiguredException $e) {
            return response()->json([...$payload, 'configured' => false, 'error' => $e->getMessage()]);
        } catch (BrevoException $e) {
            return response()->json([...$payload, 'error' => $e->getMessage()]);
        }

        $summary = [];
        $dates   = [];

        foreach ($events as $event) {
            $name = $event['event'] ?? null;

            if ($name === null) {
                continue;
            }

            $summary[$name] = ($summary[$name] ?? 0) + 1;

            if (isset($event['date'])) {
                $dates[] = $event['date'];
            }
        }

        return response()->json([
            ...$payload,
            'summary'     => $summary,
            'lastEventAt' => $dates === [] ? null : max($dates),
        ]);
    }
}
