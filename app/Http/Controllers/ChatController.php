<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $conversations = Conversation::whereHas('participants', fn($q) => $q->where('user_id', $user->id))
            ->with(['participants', 'lastMessage.user'])
            ->withCount(['messages as unread_count' => fn($q) => $q->whereRaw('(SELECT last_read_at FROM conversation_participants WHERE conversation_id = messages.conversation_id AND user_id = ?) IS NULL OR created_at > (SELECT last_read_at FROM conversation_participants WHERE conversation_id = messages.conversation_id AND user_id = ?)', [$user->id, $user->id])])
            ->latest('updated_at')
            ->get();

        $users = User::where('id', '!=', $user->id)
            ->whereIn('role', ['developer', 'admin', 'lawyer', 'staff'])
            ->get();

        return view('chat.index', compact('conversations', 'users'));
    }

    public function show(Conversation $conversation): View
    {
        $user = auth()->user();

        if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
            abort(403);
        }

        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        $conversations = Conversation::whereHas('participants', fn($q) => $q->where('user_id', $user->id))
            ->with(['participants', 'lastMessage.user'])
            ->latest('updated_at')
            ->get();

        $users = User::where('id', '!=', $user->id)
            ->whereIn('role', ['developer', 'admin', 'lawyer', 'staff'])
            ->get();

        $messages = $conversation->messages()->with('user')->oldest()->get();

        return view('chat.index', compact('conversations', 'conversation', 'messages', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'message' => 'required|string',
        ]);

        $user = auth()->user();
        $targetUser = $request->user_id;

        $existing = Conversation::whereHas('participants', fn($q) => $q->where('user_id', $user->id))
            ->whereHas('participants', fn($q) => $q->where('user_id', $targetUser))
            ->where('type', 'private')
            ->first();

        if ($existing) {
            $conversation = $existing;
        } else {
            $conversation = Conversation::create(['type' => 'private']);
            $conversation->participants()->attach([$user->id, $targetUser]);
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'message' => $request->message,
        ]);

        $conversation->touch();

        $this->notifyParticipants($conversation, $user, $request->message);

        return redirect()->route('chat.show', $conversation);
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:5000']);

        $user = auth()->user();

        if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
            abort(403);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'message' => $request->message,
        ]);

        $conversation->touch();

        $this->notifyParticipants($conversation, $user, $request->message);

        return response()->json([
            'id' => $message->id,
            'message' => $message->message,
            'user_id' => $message->user_id,
            'user_name' => $user->name,
            'created_at' => $message->created_at->diffForHumans(),
        ]);
    }

    public function fetchMessages(Conversation $conversation, Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
            abort(403);
        }

        $lastId = $request->get('after', 0);

        $messages = $conversation->messages()
            ->with('user')
            ->where('id', '>', $lastId)
            ->oldest()
            ->get();

        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        return response()->json($messages->map(fn($m) => [
            'id' => $m->id,
            'message' => $m->message,
            'user_id' => $m->user_id,
            'user_name' => $m->user->name,
            'created_at' => $m->created_at->diffForHumans(),
        ]));
    }

    public function unreadCount(): JsonResponse
    {
        $user = auth()->user();

        $count = Conversation::whereHas('participants', fn($q) => $q->where('user_id', $user->id))
            ->withCount(['messages as unread_count' => fn($q) => $q->whereRaw('(SELECT last_read_at FROM conversation_participants WHERE conversation_id = messages.conversation_id AND user_id = ?) IS NULL OR created_at > (SELECT last_read_at FROM conversation_participants WHERE conversation_id = messages.conversation_id AND user_id = ?)', [$user->id, $user->id])])
            ->get()
            ->sum('unread_count');

        return response()->json(['count' => $count]);
    }

    private function notifyParticipants(Conversation $conversation, User $sender, string $message): void
    {
        $participants = $conversation->participants()->where('user_id', '!=', $sender->id)->get();

        foreach ($participants as $participant) {
            \App\Models\Notification::create([
                'user_id' => $participant->id,
                'title' => 'رسالة جديدة من ' . $sender->name,
                'message' => substr($message, 0, 100),
                'is_read' => false,
            ]);
        }
    }
}
