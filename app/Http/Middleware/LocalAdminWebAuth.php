<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

class LocalAdminWebAuth
{
    public function handle($request, Closure $next)
    {
        if (! app()->environment('local')) {
            return $next($request);
        }

        if (! $request->is('admin*') && ! $request->is('livewire/update')) {
            return $next($request);
        }

        if (! Auth::check()) {
            $admin = User::query()
                ->where('email', 'admin@truthshield.local')
                ->where('is_admin', true)
                ->first();

            if ($admin) {
                Auth::onceUsingId($admin->id);
                Filament::auth()->setUser($admin);
            }
        }

        return $next($request);
    }
}
