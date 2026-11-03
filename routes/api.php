<?php

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Webhook that applications use to send events.
| POST /api/v1/hooks/{public_id}  with  Authorization: Bearer <token>
*/
Route::post('/v1/hooks/{application}', function (Request $request, Application $application) {
    if (! $application->tokenMatches($request->bearerToken())) {
        return response()->json(['message' => 'Invalid or missing token.'], 401);
    }

    $data = $request->validate([
        'level' => 'required|string|in:info,warning,warn,error',
        'message' => 'required|string|max:2000',
        'source' => 'nullable|string|max:255',
        'payload' => 'nullable|array',
    ]);

    $data['level'] = $data['level'] === 'warn' ? 'warning' : $data['level'];

    $event = $application->events()->create($data);

    return response()->json(['id' => $event->id, 'received_at' => $event->created_at], 202);
})->middleware('throttle:600,1')->name('hooks.store');
