<?php

use App\Models\User;
use App\Http\Controllers\SharePreviewController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Session\Middleware\StartSession;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/share/events/{event}', [SharePreviewController::class, 'event']);
Route::get('/share/events/{event}/image.png', [SharePreviewController::class, 'eventImage']);

Route::get('/admin/local-login', function () {
    abort_unless(app()->environment('local'), 404);

    $admin = User::query()
        ->where('email', 'admin@truthshield.local')
        ->where('is_admin', true)
        ->firstOrFail();

    Auth::login($admin);
    request()->session()->regenerate();

    return redirect('/admin');
})->middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
]);
