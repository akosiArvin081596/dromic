<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Requests\StoreMessageRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $conversation->participants()->where('user_id', $user->id)->exists(),
            403,
        );

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => $request->validated('body'),
        ]);

        $message->load('user:id,name,role,city_municipality_id,province_id', 'user.cityMunicipality:id,name', 'user.province:id,name');

        $recipientIds = $conversation->participants()
            ->where('user_id', '!=', $user->id)
            ->pluck('users.id')
            ->all();

        if ($recipientIds) {
            broadcast(new MessageSent(
                [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'user_id' => $message->user_id,
                    'user_name' => $message->user->name,
                    'user_role' => $message->user->role->value,
                    'body' => $message->body,
                    'created_at' => $message->created_at->toISOString(),
                    'user' => [
                        'id' => $message->user->id,
                        'name' => $message->user->name,
                        'role' => $message->user->role->value,
                        'city_municipality' => $message->user->cityMunicipality ? ['id' => $message->user->cityMunicipality->id, 'name' => $message->user->cityMunicipality->name] : null,
                        'province' => $message->user->province ? ['id' => $message->user->province->id, 'name' => $message->user->province->name] : null,
                    ],
                ],
                $recipientIds,
            ));
        }

        return response()->json($message, 201);
    }
}
