<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\DiscordNotifier;
use App\Support\Attachments;
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

        $messages = $conversation->messages()->with(['user', 'replyTo.user'])->oldest()->get();

        return view('chat.index', compact('conversations', 'conversation', 'messages', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'message' => 'nullable|string',
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

        if ($request->filled('message')) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'message' => $request->message,
            ]);
            $conversation->touch();
            if (!$this->forwardToDevelopers($conversation, $user, $message)) {
                $this->notifyParticipants($conversation, $user, $request->message);
            }
        }

        return redirect()->route('chat.show', $conversation);
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate([
            'message' => 'nullable|string|max:5000',
            // svg مرفوض: صورةٌ في ظاهرها تحمل سكربتاً في حقيقتها، وعرضُها
            // من نطاق المكتب يُشغّل سكربتها في جلسة من يفتحها.
            'attachment' => 'nullable|file|max:20480|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,zip,rar,mp3,mp4,mov',
        ]);

        $user = auth()->user();

        if (!$conversation->participants()->where('user_id', $user->id)->exists()) {
            abort(403);
        }

        $data = [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'message' => $request->message ?? '',
            'reply_to_id' => $request->reply_to_id ?? null,
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('chat-attachments', Attachments::DISK);
            $data['attachment_path'] = $path;
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_type'] = $file->getMimeType();
            $data['attachment_size'] = $file->getSize();
        }

        $message = Message::create($data);

        $conversation->touch();

        $notifyText = $request->message ?: ($request->hasFile('attachment') ? $file->getClientOriginalName() : 'مرفق');
        if (!$this->forwardToDevelopers($conversation, $user, $message)) {
            $this->notifyParticipants($conversation, $user, $notifyText);
        }

        $message->load('replyTo');
        return response()->json([
            'id' => $message->id,
            'message' => $message->message,
            'user_id' => $message->user_id,
            'user_name' => $user->name,
            'created_at' => $message->created_at->diffForHumans(),
            'attachment_url' => $message->attachment_url,
            'attachment_download_url' => $message->attachment_download_url,
            'attachment_size_label' => $message->attachment_size_label,
            'attachment_name' => $message->attachment_name,
            'attachment_type' => $message->attachment_type,
            'is_image' => $message->is_image,
            'reply_to_id' => $message->reply_to_id,
            'reply_message' => $message->replyTo?->message,
            'edited_at' => $message->edited_at?->diffForHumans(),
            'discord_pending' => (bool) $message->discord_message_id && !$message->discord_replied_at,
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
            ->with(['user', 'replyTo'])
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
            'attachment_url' => $m->attachment_url,
            'attachment_download_url' => $m->attachment_download_url,
            'attachment_size_label' => $m->attachment_size_label,
            'attachment_name' => $m->attachment_name,
            'attachment_type' => $m->attachment_type,
            'is_image' => $m->is_image,
            'reply_to_id' => $m->reply_to_id,
            'reply_message' => $m->replyTo?->message,
            'edited_at' => $m->edited_at?->diffForHumans(),
        ]));
    }

    public function editMessage(Request $request, Message $message): JsonResponse
    {
        $user = auth()->user();
        if ($message->user_id !== $user->id) {
            abort(403);
        }

        $request->validate(['message' => 'required|string|max:5000']);

        $message->update([
            'message' => $request->message,
            'edited_at' => now(),
        ]);

        return response()->json([
            'id' => $message->id,
            'message' => $message->message,
            'edited_at' => $message->edited_at->diffForHumans(),
        ]);
    }

    public function deleteMessage(Message $message): JsonResponse
    {
        $user = auth()->user();
        if ($message->user_id !== $user->id) {
            abort(403);
        }

        Attachments::delete($message->attachment_path);

        $message->delete();

        return response()->json(['id' => $message->id]);
    }

    /**
     * المرفق لا يُقدَّم إلا لمن هو في المحادثة.
     *
     * كان يُقدَّم من ‎/storage/…‎ بلا أي تحقّق: من ملك الرابط قرأ الملف
     * ولو لم يكن في المكتب أصلاً. ومرفقاتُ محادثات مكتب محاماة صورُ
     * هوياتٍ وعقودٌ ومستنداتُ قضايا.
     */
    public function attachment(Request $request, Message $message): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_if($message->attachment_path === null || $message->attachment_path === '', 404);

        if (!$message->conversation?->participants()->where('user_id', auth()->id())->exists()) {
            abort(403, 'هذا المرفق في محادثة لست طرفاً فيها.');
        }

        return Attachments::respond(
            $request,
            $message->attachment_path,
            $message->attachment_name,
            $message->attachment_type,
        );
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

    private function forwardToDevelopers(Conversation $conversation, User $sender, Message $message): bool
    {
        if ($sender->isDeveloper()) {
            return false;
        }

        $hasDeveloperRecipient = $conversation->participants()
            ->where('role', 'developer')
            ->where('user_id', '!=', $sender->id)
            ->exists();

        if (!$hasDeveloperRecipient) {
            return false;
        }

        $discordId = DiscordNotifier::sendChatMessage($message);
        if ($discordId) {
            $message->update(['discord_message_id' => $discordId]);
            return true;
        }

        return false;
    }

    private function notifyParticipants(Conversation $conversation, User $sender, string $message): void
    {
        $participants = $conversation->participants()->where('user_id', '!=', $sender->id)->get();

        foreach ($participants as $participant) {
            $existing = \App\Models\Notification::where('user_id', $participant->id)
                ->where('type', \App\Models\Notification::TYPE_CHAT)
                ->where('notifiable_id', $conversation->id)
                ->where('notifiable_type', \App\Models\Conversation::class)
                ->where('is_read', false)
                ->first();

            if ($existing) {
                $existing->increment('message_count');
                $existing->touch();
            } else {
                \App\Support\Notify::send(
                    userId: $participant->id,
                    titleKey: 'app.notif_chat_title',
                    messageKey: 'app.notif_passthrough',
                    params: ['sender' => $sender->name, 'text' => substr($message, 0, 100)],
                    type: \App\Models\Notification::TYPE_CHAT,
                    notifiableType: \App\Models\Conversation::class,
                    notifiableId: $conversation->id,
                );
            }
        }
    }
}
