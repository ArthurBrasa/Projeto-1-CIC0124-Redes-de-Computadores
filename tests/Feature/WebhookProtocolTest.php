<?php

use App\Models\User;

beforeEach(function () {
    $this->app_ = User::factory()->create()->applications()->create([
        'name' => 'sensor', 'environment' => 'development', 'url' => 'http://example.test',
    ]);
    $this->token = $this->app_->generateToken();
    $this->url = '/api/v1/hooks/'.$this->app_->public_id;
});

it('accepts a valid message and stores it', function () {
    $this->withToken($this->token)
        ->postJson($this->url, ['level' => 'warn', 'message' => 'Hot', 'source' => 'lab', 'payload' => ['t' => 41]])
        ->assertStatus(202)->assertJsonStructure(['id', 'received_at']);

    $event = $this->app_->events()->sole();
    expect($event->level)->toBe('warning')->and($event->payload)->toBe(['t' => 41]);
});

it('rejects a missing or wrong token with 401', function () {
    $this->postJson($this->url, ['level' => 'info', 'message' => 'x'])->assertStatus(401)->assertJsonPath('error', 'unauthorized');
    $this->withToken('nope')->postJson($this->url, ['level' => 'info', 'message' => 'x'])->assertStatus(401);
    expect($this->app_->events()->count())->toBe(0);
});

it('does not accept the token of another client', function () {
    $other = User::factory()->create()->applications()->create(['name' => 'b', 'environment' => 'development', 'url' => 'http://b.test']);
    $this->withToken($other->generateToken())->postJson($this->url, ['level' => 'info', 'message' => 'x'])->assertStatus(401);
});

it('rejects malformed JSON with 400', function () {
    $this->withToken($this->token)
        ->call('POST', $this->url, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$this->token], '{"level": oops')
        ->assertStatus(400)->assertJsonPath('error', 'invalid_json');
});

it('rejects invalid fields with 422', function () {
    $this->withToken($this->token)->postJson($this->url, ['level' => 'fatal'])
        ->assertStatus(422)->assertJsonPath('error', 'validation_failed')->assertJsonValidationErrors(['level', 'message']);
});

it('answers 404 for an unknown application', function () {
    $this->withToken($this->token)->postJson('/api/v1/hooks/app_missing', ['level' => 'info', 'message' => 'x'])->assertStatus(404);
});

it('returns exactly the requested payload size', function () {
    expect(strlen($this->get('/api/v1/lab/payload?bytes=2048')->assertOk()->getContent()))->toBe(2048);
    $this->getJson('/api/v1/lab/payload?bytes=99999999')->assertStatus(422);
});
