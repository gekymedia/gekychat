<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Services\SmsServiceInterface;
use App\Services\ArkeselSmsService;
use App\Services\LinkPreviewService;
use App\Models\Group;
use App\Models\User;
use App\Models\Contact;
use Illuminate\Support\Facades\Route;


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
         $this->app->singleton(LinkPreviewService::class, function ($app) {
        return new LinkPreviewService();
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

        // Messaging permission: restrict channel posting to admins/owners. For regular groups,
        // any member who can view the group may send.
        Gate::define('send-group-message', function (User $user, Group $group) {
            // Only restrict sending for channels (not regular groups, even if public)
            if ($group->type === 'channel') {
                // Global admin can send
                if ($user->is_admin) return true;
                // Owner of the group can send
                if ($group->owner_id === $user->id) return true;
                // Group admin can send
                $isGroupAdmin = $group->members()
                    ->where('users.id', $user->id)
                    ->where('group_members.role', 'admin')
                    ->exists();
                return $isGroupAdmin;
            }
            // For regular groups (not channels), any member can send
            return Gate::allows('view-group', $group);
        });

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
             // Custom route model binding for groups
        Route::bind('group', function ($value) {
            // First try to find by slug
            $group = Group::where('slug', $value)->first();
            
            // If not found by slug, try by ID
            if (!$group) {
                $group = Group::find($value);
            }
            
            if (!$group) {
                abort(404, 'Group not found');
            }
            
            return $group;
        });
    }
}
