<?php

namespace App\Providers;

use App\Http\Controllers\HomeController;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Gate::define('manage-content', fn (User $user) => $user->canManageContent());
        Gate::define('admin', fn (User $user) => $user->isAdmin());
        Gate::define('host-attendance', fn (User $user) => $user->canHostAttendance());

        // Data yang dipakai semua tampilan
        View::composer('*', fn ($view) => $view->with('siteName', Setting::get('site_name')));
        View::composer('components.layouts.portal', function ($view) {
            $user = auth()->user();
            $view->with('unreadCount', $user && ! request()->routeIs('info.index') ? HomeController::unread($user) : 0);
        });
    }
}
