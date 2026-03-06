<?php

namespace App\Events;

use App\Models\RequestLetter;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryRecorded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $requestLetterData
     * @param  list<int>  $recipientUserIds
     */
    public function __construct(
        public array $requestLetterData,
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
        return ['request_letter' => $this->requestLetterData];
    }

    /**
     * @return array{id: int, incident_id: int, incident_name: string, city_municipality_name: string, user_name: string, item_count: int, action: string, message: string}
     */
    public static function serializeRequestLetter(RequestLetter $requestLetter, User $actor): array
    {
        $requestLetter->loadMissing('incident', 'cityMunicipality');
        $actor->loadMissing('cityMunicipality', 'province', 'region');

        return [
            'id' => $requestLetter->id,
            'incident_id' => $requestLetter->incident_id,
            'incident_name' => ($requestLetter->incident->display_name ?? $requestLetter->incident->name),
            'city_municipality_name' => $requestLetter->cityMunicipality->name,
            'user_name' => $actor->name,
            'actor_name' => $actor->getActorDisplayName(),
            'item_count' => count($requestLetter->augmentation_items),
            'action' => 'delivered',
            'message' => "{$actor->getActorDisplayName()} recorded a delivery for {$requestLetter->cityMunicipality->name}'s request letter ({($requestLetter->incident->display_name ?? $requestLetter->incident->name)})",
        ];
    }
}
