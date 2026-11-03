<?php

use App\Models\Application;
use App\Models\Monitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;


Route::view('/', 'status')->name('home');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::view('/register', 'auth.register')->name('register');
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::group(['middleware' => 'auth'], function () {    
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    
    Route::prefix('applications')->name('applications.')->group(function () {
        Route::view('/', 'applications.index')->name('index');
        Route::view('/create', 'applications.create')->name('create');
        Route::get('/{application}', function (Application $application) {
            abort_unless($application->user_id === Auth::id(), 404);

            return view('applications.show', ['application' => $application]);
        })->name('show');
    });
    
    Route::prefix('monitors')->name('monitors.')->group(function () {
        Route::view('/', 'monitors.index')->name('index');
        Route::view('/create', 'monitors.form')->name('create');

        Route::get('/{monitor}', function (Monitor $monitor) {
            abort_unless($monitor->user_id === Auth::id(), 404);

            return view('monitors.show', ['monitor' => $monitor]);
        })->name('show');

        Route::get('/{monitor}/edit', function (Monitor $monitor) {
            abort_unless($monitor->user_id === Auth::id(), 404);

            return view('monitors.form', ['monitor' => $monitor]);
        })->name('edit');
    });
    
    Route::view('/events', 'events.index')->name('events.index');
    Route::view('/alerts', 'alerts.index')->name('alerts.index');
    Route::view('/network-lab', 'network-lab.index')->name('network-lab');
    Route::view('/settings', 'settings.index')->name('settings');
});
