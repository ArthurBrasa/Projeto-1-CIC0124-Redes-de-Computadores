<?php

use Illuminate\Support\Facades\Route;


Route::view('/', 'status')->name('home');

Route::view('/login', 'auth.login')->name('login');
Route::view('/register', 'auth.register')->name('register');


Route::group(['middleware' => 'auth'], function () {    
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    
    Route::prefix('applications')->name('applications.')->group(function () {
        Route::view('/', 'applications.index')->name('index');
        Route::view('/create', 'applications.create')->name('create');
        Route::view('/demo', 'applications.show')->name('show');
    });
    
    Route::prefix('monitors')->name('monitors.')->group(function () {
        Route::view('/', 'monitors.index')->name('index');
        Route::view('/create', 'monitors.create')->name('create');
        Route::view('/demo', 'monitors.show')->name('show');
    });
    
    Route::view('/events', 'events.index')->name('events.index');
    Route::view('/alerts', 'alerts.index')->name('alerts.index');
    Route::view('/network-lab', 'network-lab.index')->name('network-lab');
    Route::view('/settings', 'settings.index')->name('settings');
});
