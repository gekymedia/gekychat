<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class WebPushMessageNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public array $data = [],
        public ?string $tag = null,
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $message = WebPushMessage::create()
            ->title($this->title)
            ->body($this->body)
            ->data(array_merge([
                'url' => $this->data['url'] ?? '/',
            ], $this->data))
            ->options(['TTL' => 3600, 'urgency' => 'high']);

        if ($this->tag) {
            $message->tag($this->tag);
        }

        return $message;
    }
}
