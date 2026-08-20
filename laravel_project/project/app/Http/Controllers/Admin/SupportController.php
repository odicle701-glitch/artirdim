<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportTicket::with(['user', 'lastMessage'])->withCount('messages');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('q')) {
            $query->where('subject', 'like', '%'.$request->q.'%');
        }

        $tickets = $query->orderByDesc('updated_at')->paginate(15)->withQueryString();

        $counts = [
            'all' => SupportTicket::count(),
            'open' => SupportTicket::where('status', 'open')->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'closed' => SupportTicket::where('status', 'closed')->count(),
        ];

        return view('admin.support.index', compact('tickets', 'counts'));
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load('messages.user', 'user');

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        return view('admin.support.show', compact('ticket'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $request->validate(['body' => 'required|max:3000']);

        $message = $ticket->messages()->create([
            'user_id' => auth()->id(),
            'body' => $request->body,
            'is_admin' => true,
        ]);

        $ticket->update([
            'status' => 'in_progress',
            'last_reply_by' => 'admin',
        ]);

        $ticket->user->notify(new TicketRepliedNotification($ticket));

        $message->load('user');

        return response()->json([
            'message' => [
                'id' => $message->id,
                'body' => $message->body,
                'is_admin' => true,
                'user' => $message->user->name,
                'time' => $message->created_at->format('d.m.Y H:i'),
                'avatar'   => $message->user->avatar
                        ? asset('storage/' . $message->user->avatar)
                        : null,
            ],
        ]);
    }

    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $request->validate(['status' => 'required|in:open,in_progress,closed']);
        $ticket->update(['status' => $request->status]);

        return back()->with('success', 'Durum güncellendi.');
    }
}
