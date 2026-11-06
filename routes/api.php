<?php

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

/*
| Webhook that applications use to send events.
| POST /api/v1/hooks/{public_id}  with  Authorization: Bearer <token>
*/
Route::post('/v1/hooks/{application}', function (Request $request, Application $application) {
    $log = Log::channel('protocol');
    $context = ['app' => $application->public_id, 'ip' => $request->ip(), 'bytes' => strlen($request->getContent())];

    if (! $application->tokenMatches($request->bearerToken())) {
        $log->warning('webhook.rejected.unauthorized', $context);

        return response()->json(['error' => 'unauthorized', 'message' => 'Invalid or missing token.'], 401);
    }

    // body declared as JSON but not parseable
    if ($request->isJson() && $request->getContent() !== '') {
        json_decode($request->getContent());
        $jsonError = json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() : null;

        if ($jsonError) {
            $log->warning('webhook.rejected.invalid_json', $context + ['json_error' => $jsonError]);

            return response()->json(['error' => 'invalid_json', 'message' => 'Body is not valid JSON: '.$jsonError.'.'], 400);
        }
    }

    try {
        $data = $request->validate([
            'level' => 'required|string|in:info,warning,warn,error',
            'message' => 'required|string|max:2000',
            'source' => 'nullable|string|max:255',
            'payload' => 'nullable|array',
        ]);
    } catch (ValidationException $e) {
        $log->warning('webhook.rejected.validation', $context + ['errors' => $e->errors()]);

        return response()->json(['error' => 'validation_failed', 'message' => 'The message has invalid fields.', 'errors' => $e->errors()], 422);
    }

    $data['level'] = $data['level'] === 'warn' ? 'warning' : $data['level'];

    $event = $application->events()->create($data);
    $log->info('webhook.accepted', $context + ['event_id' => $event->id, 'level' => $event->level]);

    return response()->json(['id' => $event->id, 'received_at' => $event->created_at], 202);
})->middleware('throttle:600,1')->name('hooks.store');

/*
| Network Lab: returns exactly N bytes of payload, for throughput tests with
| different message sizes. GET /api/v1/lab/payload?bytes=10240  (1 B .. 1 MB)
| No rate limit on purpose: throughput tests send hundreds of requests per second.
*/
Route::get('/v1/lab/payload', function (Request $request) {
    $bytes = (int) $request->validate(['bytes' => 'required|integer|between:1,1048576'])['bytes'];

    return response(str_repeat('x', $bytes), 200, [
        'Content-Type' => 'application/octet-stream',
        'Cache-Control' => 'no-store',
    ]);
})->name('lab.payload');
