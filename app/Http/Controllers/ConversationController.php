<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDmRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Services\MessengerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(public MessengerService $messenger) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->messenger->syncGroupChatMembership($user);
        $conversations = $this->messenger->getConversationsForUser($user);

        return response()->json($conversations);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless(
            $conversation->participants()->where('user_id', $request->user()->id)->exists(),
            403,
        );

        $this->messenger->markAsRead($conversation, $request->user());

        $messages = $this->messenger->getMessages($conversation, $request->integer('before') ?: null);

        return response()->json($messages);
    }

    public function storeDm(StoreDmRequest $request): JsonResponse
    {
        $user = $request->user();
        $otherUser = User::findOrFail($request->validated('user_id'));

        abort_if($user->id === $otherUser->id, 422, 'Cannot start a conversation with yourself.');

        $conversation = $this->messenger->getOrCreateDm($user, $otherUser);
        $conversation->load('participants:id,name,role', 'latestMessage.user:id,name', 'province:id,name');

        return response()->json($conversation);
    }

    public function users(Request $request): JsonResponse
    {
        $users = $this->messenger->getContactableUsers(
            $request->user(),
            $request->input('search'),
        );

        return response()->json($users);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->messenger->getUnreadCount($request->user()),
        ]);
    }
}
