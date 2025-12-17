<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Http\View\Composers\SidebarComposer;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Apply SidebarComposer to all views that use the chat sidebar
        View::composer([
            'partials.chat_sidebar',
            'chat.*',
            'groups.*',
            'contacts.*',
            'settings.*',
        ], SidebarComposer::class);
    }
}
