<?php

namespace App\Events;

use App\Models\Report;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{id: int, report_number: string, report_type: string, sequence_number: int, status: string, incident_id: int, incident_name: string, city_municipality_name: string, user_name: string}  $reportData
     * @param  list<int>  $recipientUserIds
     */
    public function __construct(
        public array $reportData,
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
        return ['report' => $this->reportData];
    }

    /**
     * Serialize a Report model to a plain array for broadcasting.
     *
     * @return array{id: int, report_number: string, report_type: string, sequence_number: int, status: string, incident_id: int, incident_name: string, city_municipality_name: string, user_name: string, message: string}
     */
    public static function serializeReport(Report $report, ?User $actor = null, bool $isResubmission = false): array
    {
        $report->loadMissing('incident', 'cityMunicipality', 'user');

        $actor ??= $report->user;
        $actor->loadMissing('cityMunicipality', 'province', 'region');

        $actorName = $actor->getActorDisplayName();

        if ($isResubmission) {
            $message = "{$actorName} has re-submitted their DROMIC report for {($report->incident->display_name ?? $report->incident->name)}";
        } else {
            $article = $report->report_type->value === 'initial' ? 'an' : 'a';
            $typeName = ucfirst($report->report_type->value);
            $message = "{$actorName} submitted {$article} {$typeName} Report for {($report->incident->display_name ?? $report->incident->name)}";
        }

        return [
            'id' => $report->id,
            'report_number' => $report->report_number,
            'report_type' => $report->report_type->value,
            'sequence_number' => $report->sequence_number,
            'status' => $report->status,
            'incident_id' => $report->incident_id,
            'incident_name' => ($report->incident->display_name ?? $report->incident->name),
            'city_municipality_name' => $report->cityMunicipality->name,
            'user_name' => $report->user->name,
            'message' => $message,
        ];
    }
}
