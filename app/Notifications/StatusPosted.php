<?php

namespace App\Notifications;

use App\Models\Status;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StatusPosted extends Notification
{
    use Queueable;

    protected $status;

    /**
     * Create a new notification instance.
     */
    public function __construct(Status $status)
    {
        $this->status = $status;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'status_id' => $this->status->id,
            'user_id' => $this->status->user_id,
            'user_name' => $this->status->user->name ?? 'Someone',
            'user_avatar' => $this->status->user->avatar_url ?? null,
            'status_type' => $this->status->type,
            'message' => $this->status->user->name . ' posted a new status',
        ];
    }
}
