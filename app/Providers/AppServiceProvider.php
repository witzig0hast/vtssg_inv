<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Carbon\Carbon::setLocale(config('app.locale'));
        \Illuminate\Pagination\Paginator::defaultView('pagination::tailwind');
        \Illuminate\Validation\Rules\Password::defaults(fn () => \Illuminate\Validation\Rules\Password::min(config('inventory.password_min'))->letters()->mixedCase()->numbers());
        // Super-Admin darf alles (Rolle „Administrator“), Rest über Permissions
        \Illuminate\Support\Facades\Gate::before(fn ($user) => $user->hasRole('Administrator') ? true : null);
        \Illuminate\Support\Facades\RateLimiter::for('api', fn ($r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
    }
}
