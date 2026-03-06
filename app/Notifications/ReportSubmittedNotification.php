<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ReportSubmittedNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $reportData
     */
    public function __construct(public array $reportData) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'report',
            'report' => $this->reportData,
        ];
    }
}
