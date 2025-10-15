<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Services\SmsServiceInterface;
use App\Services\ArkeselSmsService;
use App\Models\Group;
use App\Models\User;
use App\Models\Contact;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // SMS bindings
        $this->app->singleton(SmsServiceInterface::class, ArkeselSmsService::class);

        $this->app->singleton('arkesel-sms', function () {
            return new ArkeselSmsService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // (Optional) Global admin bypass
        Gate::before(function (?User $user, string $ability) {
            if ($user && property_exists($user, 'is_admin') && $user->is_admin) {
                return true;
            }
            return null;
        });

        // Can the user view/use a group (read & send)
        Gate::define('view-group', function (User $user, Group $group) {
            // ✅ Allow owners OR members
            return $group->owner_id === $user->id
                || $group->members()->where('users.id', $user->id)->exists();
        });

        // Can the user manage a group (admin actions)
        Gate::define('manage-group', function (User $user, Group $group) {
            return $group->owner_id === $user->id
                || $group->members()
                ->where('users.id', $user->id)
                ->where('group_members.role', 'admin')
                ->exists();
        });

        // Alias for messaging (keeps controllers explicit)
        Gate::define(
            'send-group-message',
            fn(User $user, Group $group) =>
            Gate::allows('view-group', $group)
        );

        User::created(function (User $user) {
            $norm = Contact::normalizePhone($user->phone);
            $last9 = Contact::last9($norm);

            Contact::whereNull('contact_user_id')
                ->where(function ($q) use ($norm, $last9) {
                    $q->where('normalized_phone', $norm)
                        ->orWhereRaw('RIGHT(REGEXP_REPLACE(normalized_phone, "[^0-9]", ""), 9) = ?', [$last9]);
                })
                ->update([
                    'contact_user_id' => $user->id,
                ]);
        });
    }
}
