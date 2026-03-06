<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{id: int, conversation_id: int, user_id: int, user_name: string, user_role: string, body: string, created_at: string}  $messageData
     * @param  list<int>  $recipientUserIds
     */
    public function __construct(
        public array $messageData,
        public array $recipientUserIds,
    ) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            fn (int $userId) => new PrivateChannel("App.Models.User.{$userId}"),
            $this->recipientUserIds,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['message' => $this->messageData];
    }
}
