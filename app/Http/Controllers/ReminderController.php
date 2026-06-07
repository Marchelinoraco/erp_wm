<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\Tour;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReminderController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $reminders = Reminder::where('user_id', $user->id)
            ->with('tour:id,code,title,status')
            ->orderBy('is_done')
            ->orderBy('remind_at')
            ->get()
            ->append(['is_overdue', 'is_today']);

        $tours = Tour::whereNotIn('status', ['confirmed', 'cancelled'])
            ->orderByDesc('created_at')
            ->get(['id', 'code', 'title', 'status']);

        $stats = [
            'overdue'  => $reminders->where('is_done', false)->filter(fn($r) => $r->remind_at->isPast() && !$r->remind_at->isToday())->count(),
            'today'    => $reminders->where('is_done', false)->filter(fn($r) => $r->remind_at->isToday())->count(),
            'upcoming' => $reminders->where('is_done', false)->filter(fn($r) => $r->remind_at->isFuture())->count(),
            'done'     => $reminders->where('is_done', true)->count(),
        ];

        return Inertia::render('Reminders/Index', [
            'reminders' => $reminders,
            'tours'     => $tours,
            'stats'     => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tour_id'   => 'nullable|exists:tours,id',
            'title'     => 'required|string|max:255',
            'notes'     => 'nullable|string',
            'remind_at' => 'required|date',
        ]);

        auth()->user()->reminders()->create($data);

        return redirect()->back()->with('success', 'Reminder berhasil dibuat.');
    }

    public function update(Request $request, Reminder $reminder)
    {
        abort_unless($reminder->user_id === auth()->id(), 403);

        $data = $request->validate([
            'tour_id'   => 'nullable|exists:tours,id',
            'title'     => 'required|string|max:255',
            'notes'     => 'nullable|string',
            'remind_at' => 'required|date',
            'is_done'   => 'boolean',
        ]);

        $reminder->update($data);

        return redirect()->back()->with('success', 'Reminder diperbarui.');
    }

    public function done(Reminder $reminder)
    {
        abort_unless($reminder->user_id === auth()->id(), 403);
        $reminder->update(['is_done' => true]);
        return redirect()->back()->with('success', 'Reminder ditandai selesai.');
    }

    public function destroy(Reminder $reminder)
    {
        abort_unless($reminder->user_id === auth()->id(), 403);
        $reminder->delete();
        return redirect()->back()->with('success', 'Reminder dihapus.');
    }
}
