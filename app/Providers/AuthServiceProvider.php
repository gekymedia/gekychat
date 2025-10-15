<?php

namespace App\Providers;

use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // You can map policies here if you prefer class-based policies.
        // Group::class => \App\Policies\GroupPolicy::class,
        // GroupMessage::class => \App\Policies\GroupMessagePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // A user can view a group if they are a member of it or the owner
        Gate::define('view-group', function (User $user, Group $group) {
            return $group->owner_id === $user->id
                || $group->members()->where('user_id', $user->id)->exists();
        });

        // Manage group (add/remove members, update settings) if owner or admin
        Gate::define('manage-group', function (User $user, Group $group) {
            if ($group->owner_id === $user->id) return true;
            return $group->members()
                ->where('user_id', $user->id)
                ->wherePivot('role', 'admin')
                ->exists();
        });

        // Transfer ownership only by current owner
        Gate::define('transfer-ownership', function (User $user, Group $group) {
            return $group->owner_id === $user->id;
        });

        // Edit a group message only if you are the sender
        Gate::define('edit-message', function (User $user, GroupMessage $message) {
            return $message->sender_id === $user->id;
        });

        // Delete a group message only if you are the sender
        Gate::define('delete-message', function (User $user, GroupMessage $message) {
            return $message->sender_id === $user->id;
        });
    }
}
