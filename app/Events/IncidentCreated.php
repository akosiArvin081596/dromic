<?php

namespace App\Events;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncidentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{id: int, name: string, type: string, status: string, description: ?string, created_by: int, reports_count: int, created_at: string, updated_at: string}  $incidentData
     * @param  list<int>  $recipientUserIds
     */
    public function __construct(
        public array $incidentData,
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
        return ['incident' => $this->incidentData];
    }

    /**
     * Serialize an Incident model to a plain array matching the frontend Incident type.
     *
     * @return array{id: int, name: string, type: string, status: string, description: ?string, created_by: int, reports_count: int, created_at: string, updated_at: string, message: string}
     */
    public static function serializeIncident(Incident $incident, User $actor): array
    {
        $incident->refresh();
        $actor->loadMissing('cityMunicipality', 'province', 'region');

        return [
            'id' => $incident->id,
            'name' => $incident->display_name ?? $incident->name,
            'type' => $incident->type->value,
            'status' => $incident->status->value,
            'description' => $incident->description,
            'created_by' => $incident->created_by,
            'reports_count' => 0,
            'created_at' => $incident->created_at->toISOString(),
            'updated_at' => $incident->updated_at->toISOString(),
            'message' => $actor->getActorDisplayName().' created a new incident',
        ];
    }
}
