<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;

class LocalFilamentAdmin extends FilamentAuthenticate
{
    /**
     * In local Docker development, keep the Filament admin usable even when the
     * browser automation environment refuses to persist Laravel session cookies.
     */
    public function handle($request, Closure $next, ...$guards)
    {
        if (! app()->environment('local')) {
            return parent::handle($request, $next, ...$guards);
        }

        if (! Filament::auth()->check()) {
            $admin = User::query()
                ->where('email', 'admin@truthshield.local')
                ->where('is_admin', true)
                ->first();

            if ($admin) {
                Filament::auth()->setUser($admin);
            }
        }

        return $next($request);
    }
}
