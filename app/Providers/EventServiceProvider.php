<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\MessageSent;
use App\Events\GroupMessageSent;
use App\Listeners\ProcessAutoReply;
use App\Listeners\SendPushNotification;
use App\Listeners\SendMessageNotification;
use App\Listeners\SendGroupMessageNotification;
use App\Listeners\SendMentionNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        MessageSent::class => [
            ProcessAutoReply::class,
            SendPushNotification::class,
            SendMessageNotification::class, // WhatsApp-style: Trigger FCM for background sync
            SendMentionNotification::class, // NEW: Send notifications for @mentions
        ],
        GroupMessageSent::class => [
            SendGroupMessageNotification::class, // WhatsApp-style: Trigger FCM for background sync
            SendMentionNotification::class, // NEW: Send notifications for @mentions
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
