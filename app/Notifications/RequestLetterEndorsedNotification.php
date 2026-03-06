<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class RequestLetterEndorsedNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $requestLetterData
     */
    public function __construct(public array $requestLetterData) {}

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
            'kind' => 'request_letter',
            'request_letter' => $this->requestLetterData,
        ];
    }
}
