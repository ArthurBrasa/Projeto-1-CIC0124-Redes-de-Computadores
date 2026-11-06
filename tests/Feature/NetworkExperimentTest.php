<?php

use App\Jobs\RunNetworkExperiment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('computes mean, median, sample standard deviation and percentile', function () {
    $v = [2, 4, 4, 4, 5, 5, 7, 9];
    expect(RunNetworkExperiment::mean($v))->toBe(5.0)
        ->and(RunNetworkExperiment::median($v))->toBe(4.5)
        ->and(RunNetworkExperiment::stddev($v))->toBe(2.14)
        ->and(RunNetworkExperiment::percentile([1, 2, 3, 4, 5], 95))->toBe(4.8);
});

it('fails a payload test when the target answers something else, like a 404 page', function () {
    Http::fake(['*' => Http::response(str_repeat('x', 500), 404)]);
    $experiment = User::factory()->create()->networkExperiments()->create([
        'type' => 'throughput', 'target_url' => 'http://other.test/up', 'status' => 'queued',
        'params' => ['clients' => 1, 'duration' => 1, 'sizes' => [1024]],
    ]);

    RunNetworkExperiment::dispatchSync($experiment);

    expect($experiment->refresh()->status)->toBe('failed')->and($experiment->error)->toContain('HTTP 404');
});
