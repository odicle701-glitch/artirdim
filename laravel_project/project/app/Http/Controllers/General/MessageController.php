<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(?Conversation $conversation = null)
    {
        $user = auth()->user();

        $conversations = Conversation::forUser($user)
            ->with(['userOne', 'userTwo', 'lastMessage'])
            ->orderByDesc('last_message_at')
            ->get();

        $active = null;
        $messages = collect();

        if ($conversation && $conversation->exists) {
            abort_unless($conversation->hasParticipant($user), 403);
            $active = $conversation->load(['userOne', 'userTwo']);
            $active->messages()->where('sender_id', '!=', $user->id)->whereNull('read_at')->update(['read_at' => now()]);
            $messages = $conversation->messages()->with('sender')->get();
        }

        return view('messages.index', compact('conversations', 'active', 'messages', 'user'));
    }

    public function show(Conversation $conversation)
    {
        return $this->index($conversation);
    }

    public function store(Request $request, Conversation $conversation)
    {
        $user = auth()->user();
        abort_unless($conversation->hasParticipant($user), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'body'            => $data['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        if ($request->wantsJson()) {
            return response()->json([
                'id'      => $message->id,
                'body'    => $message->body,
                'mine'    => true,
                'time'    => $message->created_at->format('H:i'),
            ]);
        }

        return redirect()->route('messages.show', $conversation);
    }

    public function poll(Request $request, Conversation $conversation)
    {
        $user = auth()->user();
        abort_unless($conversation->hasParticipant($user), 403);

        $afterId = (int) $request->query('after', 0);

        $new = $conversation->messages()
            ->with('sender')
            ->where('id', '>', $afterId)
            ->get();

        // Karşıdan gelen yeni mesajları okundu işaretle (yalnızca gerekiyorsa)
        $unread = $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at');

        if ($unread->exists()) {
            $unread->update(['read_at' => now()]);
        }

        return response()->json([
            'messages' => $new->map(fn ($m) => [
                'id'   => $m->id,
                'body' => $m->body,
                'mine' => $m->sender_id === $user->id,
                'time' => $m->created_at->format('H:i'),
                'name' => $m->sender->name,
            ]),
        ]);
    }

    public function start(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ((int) $data['user_id'] === $user->id) {
            return redirect()->route('messages.index');
        }

        $target = User::findOrFail($data['user_id']);
        $conversation = Conversation::between($user, $target);

        if ($request->filled('body')) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $user->id,
                'body'            => substr($request->input('body'), 0, 2000),
            ]);
            $conversation->update(['last_message_at' => now()]);
        }

        return redirect()->route('messages.show', $conversation);
    }
}
