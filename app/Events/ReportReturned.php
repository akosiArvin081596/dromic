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

class ReportReturned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{id: int, report_number: string, report_type: string, sequence_number: int, status: string, incident_id: int, incident_name: string, city_municipality_name: string, user_name: string, return_reason: string}  $reportData
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
     * @return array{id: int, report_number: string, report_type: string, sequence_number: int, status: string, incident_id: int, incident_name: string, city_municipality_name: string, user_name: string, return_reason: string, message: string}
     */
    public static function serializeReport(Report $report, ?User $actor = null): array
    {
        $report->loadMissing('incident', 'cityMunicipality', 'user');

        if ($actor) {
            $actor->loadMissing('cityMunicipality', 'province', 'region');
            $incidentName = $report->incident->display_name ?? $report->incident->name;
            $message = $actor->getActorDisplayName()." returned the DROMIC report of {$report->cityMunicipality->name} for {$incidentName}";
        } else {
            $message = 'A DROMIC report has been returned';
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
            'return_reason' => $report->return_reason,
            'message' => $message,
        ];
    }
}
