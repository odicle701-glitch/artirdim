<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportController extends Controller
{
    public function index()
    {
        $tickets = SupportTicket::where('user_id', Auth::id())
            ->with('lastMessage')
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->paginate(10);

        return view('general.support.index', compact('tickets'));
    }

    public function create()
    {
        return view('general.support.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:150',
            'category' => 'required|in:general,billing,auction,technical,other',
            'priority' => 'required|in:low,medium,high',
            'body' => 'required|string|min:10|max:3000',
        ]);

        $ticket = SupportTicket::create([
            'user_id' => Auth::id(),
            'subject' => $data['subject'],
            'category' => $data['category'],
            'priority' => $data['priority'],
            'status' => 'open',
            'last_reply_at' => now(),
            'last_reply_by' => 'user',
        ]);

        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'body' => $data['body'],
            'is_admin' => false,
        ]);

        return redirect()->route('support.show', $ticket)
            ->with('success', 'Destek talebiniz oluşturuldu.');
    }

    public function show(SupportTicket $ticket)
    {
        abort_if($ticket->user_id !== Auth::id(), 403);

        $ticket->load('messages.user');

        return view('general.support.show', compact('ticket'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        abort_unless(auth()->id() === $ticket->user_id, 403);
        abort_if($ticket->isClosed(), 403);

        $request->validate(['body' => 'required|max:3000']);

        $message = $ticket->messages()->create([
            'user_id' => auth()->id(),
            'body' => $request->body,
            'is_admin' => false,
        ]);

        $ticket->update(['last_reply_by' => 'user']);

        $message->load('user');

        return response()->json([
            'message' => [
                'id' => $message->id,
                'body' => $message->body,
                'is_admin' => false,
                'user' => $message->user->name,
                'time' => $message->created_at->format('d.m.Y H:i'),
                'avatar'   => $message->user->avatar
                        ? asset('storage/' . $message->user->avatar)
                        : null,
            ],
        ]);
    }

    public function close(SupportTicket $ticket)
    {
        abort_if($ticket->user_id !== Auth::id(), 403);

        $ticket->update(['status' => 'closed']);

        return back()->with('success', 'Talep kapatıldı.');
    }
}
