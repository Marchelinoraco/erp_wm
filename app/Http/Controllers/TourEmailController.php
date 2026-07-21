<?php

namespace App\Http\Controllers;

use App\Mail\TourEmail;
use App\Models\Reminder;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class TourEmailController extends Controller
{
    public function send(Request $request, Tour $tour)
    {
        $data = $request->validate([
            'to'      => 'required|email',
            'subject' => 'required|string|max:255',
            'body'    => 'required|string',
        ]);

        Mail::to($data['to'])->queue(new TourEmail($data['subject'], $data['body']));

        // Log sebagai reminder "terkirim"
        Reminder::create([
            'user_id'   => auth()->id(),
            'tour_id'   => $tour->id,
            'title'     => 'Email terkirim: ' . $data['subject'],
            'notes'     => 'To: ' . $data['to'],
            'remind_at' => today(),
            'is_done'   => true,
        ]);

        return redirect()->back()->with('success', 'Email berhasil dikirim ke ' . $data['to']);
    }

    public function templates(Tour $tour): array
    {
        $tour->loadMissing('customer');

        $customer  = $tour->customer;
        $name      = $customer?->name ?? 'Bapak/Ibu';
        $code      = $tour->code;
        $title     = $tour->title ?? 'Tour';
        $pax       = $tour->pax . ' pax';
        $dates     = $tour->start_date
            ? $tour->start_date->format('d M Y') . ($tour->end_date ? ' – ' . $tour->end_date->format('d M Y') : '')
            : '-';
        $sales     = $tour->sales_person ?? 'Tim Welcome Manado';
        $sign      = "Salam hangat,\n{$sales}\nWelcome Manado Tour & Travel\nwelcomemanado.com";

        return [
            'inquiry' => [
                'subject' => "Konfirmasi Penerimaan Inquiry – {$code}",
                'body'    => "Yth. {$name},\n\nTerima kasih telah menghubungi Welcome Manado Tour & Travel.\n\nKami telah menerima inquiry Anda untuk paket \"{$title}\" ({$pax}) dan akan segera menghubungi Anda dengan informasi lebih lanjut.\n\nNomor referensi inquiry Anda: {$code}\n\nApabila ada pertanyaan mendesak, Anda dapat menghubungi kami langsung melalui WhatsApp atau telepon.\n\n{$sign}",
            ],
            'quotation_sent' => [
                'subject' => "Penawaran Tour – {$code} | {$title}",
                'body'    => "Yth. {$name},\n\nBerikut kami sampaikan penawaran tour dari Welcome Manado Tour & Travel:\n\n  Kode Tour   : {$code}\n  Paket       : {$title}\n  Pax         : {$pax}\n  Tanggal     : {$dates}\n\nSilakan periksa detail penawaran yang terlampir. Jika ada pertanyaan atau ingin melakukan penyesuaian, kami siap berdiskusi.\n\nPenawaran ini berlaku selama 7 hari sejak tanggal pengiriman.\n\n{$sign}",
            ],
            'follow_up' => [
                'subject' => "Follow Up Penawaran Tour – {$code}",
                'body'    => "Yth. {$name},\n\nKami ingin menindaklanjuti penawaran tour yang kami kirimkan sebelumnya:\n\n  Kode Tour   : {$code}\n  Paket       : {$title}\n  Tanggal     : {$dates}\n\nApakah Anda sudah sempat melihat penawaran kami? Kami dengan senang hati siap membantu menjawab pertanyaan atau mendiskusikan penyesuaian yang diperlukan.\n\n{$sign}",
            ],
            'negotiation' => [
                'subject' => "Update Negosiasi Tour – {$code}",
                'body'    => "Yth. {$name},\n\nTerima kasih atas waktu dan diskusi yang telah kita lakukan mengenai paket tour Anda.\n\nKami sedang memproses permintaan penyesuaian yang telah disepakati dan akan segera mengirimkan penawaran terbaru untuk konfirmasi Anda.\n\n  Kode Tour   : {$code}\n  Paket       : {$title}\n  Tanggal     : {$dates}\n\nMohon tidak ragu menghubungi kami apabila ada hal yang ingin didiskusikan lebih lanjut.\n\n{$sign}",
            ],
            'confirmed' => [
                'subject' => "Konfirmasi Pemesanan Tour – {$code} ✓",
                'body'    => "Yth. {$name},\n\nKami dengan senang hati mengkonfirmasi pemesanan tour Anda!\n\n  Kode Tour   : {$code}\n  Paket       : {$title}\n  Pax         : {$pax}\n  Tanggal     : {$dates}\n\nPemesanan Anda telah kami catat dan kami akan menghubungi Anda kembali mengenai detail teknis perjalanan (meeting point, itinerary, perlengkapan yang perlu disiapkan, dll.).\n\nTerima kasih telah memilih Welcome Manado Tour & Travel. Kami tidak sabar menyambut Anda!\n\n{$sign}",
            ],
            'cancelled' => [
                'subject' => "Informasi Pembatalan Tour – {$code}",
                'body'    => "Yth. {$name},\n\nDengan hormat, kami ingin menginformasikan bahwa tour berikut telah dibatalkan:\n\n  Kode Tour   : {$code}\n  Paket       : {$title}\n  Tanggal     : {$dates}\n\nApabila ada pertanyaan mengenai proses refund atau penjadwalan ulang, silakan hubungi kami dan kami akan dengan senang hati membantu.\n\nMohon maaf atas ketidaknyamanan yang mungkin ditimbulkan.\n\n{$sign}",
            ],
        ];
    }
}
