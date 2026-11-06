<?php

namespace App\Jobs;

use App\Models\NetworkExperiment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Runs one Network Lab experiment against a URL and stores the measurements.
 *
 * Timings come from cURL (Guzzle handler stats):
 *  - dns   = name lookup time
 *  - rtt   = TCP handshake (connect_time - namelookup_time): one network round trip
 *  - ttfb  = time to first byte
 *  - total = whole request (DNS + connect + TLS + server + transfer)
 * Every request opens a new connection, so each one includes a fresh TCP handshake.
 *
 * A) RTT          N sequential requests -> mean, median, std dev, p95, jitter, loss
 * B) Throughput   C parallel clients for D seconds per payload size -> req/s and goodput (body bytes/s)
 * C) Concurrency  for each level (e.g. 1, 2, 5 clients) run D seconds -> delay and message rate
 */
class RunNetworkExperiment implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    private const REQUEST_TIMEOUT = 10;

    private float $lastSave = 0;

    public function __construct(public NetworkExperiment $experiment) {}

    public function handle(): void
    {
        $e = $this->experiment;
        $e->forceFill(['status' => 'running', 'progress' => 0, 'started_at' => now()])->save();
        Log::channel('protocol')->info('lab.experiment.started', ['id' => $e->id, 'type' => $e->type, 'target' => $e->target_url, 'params' => $e->params]);

        try {
            match ($e->type) {
                NetworkExperiment::RTT => $this->rtt($e),
                NetworkExperiment::THROUGHPUT => $this->throughput($e),
                NetworkExperiment::CONCURRENCY => $this->concurrency($e),
            };

            $e->forceFill(['status' => 'done', 'progress' => 100, 'finished_at' => now()])->save();
            Log::channel('protocol')->info('lab.experiment.done', ['id' => $e->id, 'results' => $e->results]);
        } catch (Throwable $ex) {
            $this->failed($ex);
        }
    }

    public function failed(?Throwable $ex): void
    {
        $this->experiment->forceFill([
            'status' => 'failed',
            'error' => Str::limit($ex?->getMessage() ?? 'Unknown error', 250),
            'finished_at' => now(),
        ])->save();

        Log::channel('protocol')->error('lab.experiment.failed', ['id' => $this->experiment->id, 'error' => $ex?->getMessage()]);
    }

    /* ---------------- A) RTT: sequential requests, one new connection each ---------------- */

    private function rtt(NetworkExperiment $e): void
    {
        $n = $e->params['samples'];
        $samples = [];

        for ($i = 0; $i < $n; $i++) {
            $samples[] = $this->request($e->target_url);

            $e->samples = $samples;
            $e->results = $this->rttSummary($samples);
            $e->progress = (int) floor(($i + 1) / $n * 100);
            $this->save($e);

            usleep(50_000); // be gentle with the target
        }
    }

    private function rttSummary(array $samples): array
    {
        $ok = array_values(array_filter($samples, fn ($s) => $s['ok']));
        $rtt = array_column($ok, 'rtt');
        $total = array_column($ok, 'total');

        // jitter as in RFC 3550: mean absolute difference between consecutive RTTs
        $diffs = [];
        for ($i = 1; $i < count($rtt); $i++) {
            $diffs[] = abs($rtt[$i] - $rtt[$i - 1]);
        }

        $sent = count($samples);

        return [
            'samples' => $sent,
            'received' => count($ok),
            'http_errors' => count(array_filter($ok, fn ($s) => ($s['status'] ?? 0) >= 400)),
            'loss' => $sent ? round(($sent - count($ok)) / $sent * 100, 1) : 0,
            'rtt_avg' => self::mean($rtt),
            'rtt_median' => self::median($rtt),
            'rtt_stddev' => self::stddev($rtt),
            'rtt_p95' => self::percentile($rtt, 95),
            'rtt_min' => $rtt ? min($rtt) : null,
            'rtt_max' => $rtt ? max($rtt) : null,
            'jitter' => $diffs ? round(array_sum($diffs) / count($diffs), 2) : 0,
            'dns_avg' => self::mean(array_column($ok, 'dns')),
            'ttfb_avg' => self::mean(array_column($ok, 'ttfb')),
            'total_avg' => self::mean($total),
            'total_median' => self::median($total),
            'total_stddev' => self::stddev($total),
        ];
    }

    /* ---------------- B) Throughput: C parallel clients, D seconds per payload size ---------------- */

    private function throughput(NetworkExperiment $e): void
    {
        $clients = $e->params['clients'];
        $duration = $e->params['duration'];
        $sizes = $e->params['sizes'] ?? [null]; // null = the target URL as it is
        $steps = [];

        foreach ($sizes as $index => $size) {
            $url = $size ? $this->payloadUrl($e->target_url, $size) : $e->target_url;

            $steps[$index] = $this->load($url, $clients, $duration, $size, function (array $step, float $part) use ($e, &$steps, $index, $sizes, $size) {
                $steps[$index] = $step + ['size' => $size];
                $e->samples = ['steps' => array_values($steps)];
                $e->results = $this->throughputSummary(array_values($steps), $e);
                $e->progress = min(99, (int) floor(($index + $part) / count($sizes) * 100));
                $this->save($e);
            }) + ['size' => $size];
        }

        $e->samples = ['steps' => array_values($steps)];
        $e->results = $this->throughputSummary(array_values($steps), $e);
    }

    private function throughputSummary(array $steps, NetworkExperiment $e): array
    {
        $last = end($steps) ?: [];

        return [
            'clients' => $e->params['clients'],
            'duration' => $e->params['duration'],
            'sizes' => count($steps),
            'requests' => array_sum(array_column($steps, 'requests')),
            'errors' => array_sum(array_column($steps, 'errors')),
            // last step, used by the KPI tiles
            'size' => $last['size'] ?? null,
            'req_per_sec' => $last['req_per_sec'] ?? null,
            'bytes_per_sec' => $last['goodput'] ?? null,
            'avg_ms' => $last['avg_ms'] ?? null,
            'best_goodput' => $steps ? max(array_column($steps, 'goodput')) : null,
        ];
    }

    /* ---------------- C) Concurrency: compare levels such as 1, 2 and 5 clients ---------------- */

    private function concurrency(NetworkExperiment $e): void
    {
        $levels = $e->params['levels'] ?? [1, 2, 5];
        $duration = $e->params['duration'] ?? 5;
        $steps = [];

        foreach ($levels as $index => $clients) {
            $steps[$index] = $this->load($e->target_url, $clients, $duration, null, function (array $step, float $part) use ($e, &$steps, $index, $levels) {
                $steps[$index] = $step;
                $e->samples = ['steps' => array_values($steps)];
                $e->results = $this->concurrencySummary(array_values($steps));
                $e->progress = min(99, (int) floor(($index + $part) / count($levels) * 100));
                $this->save($e);
            });

            usleep(300_000);
        }

        $e->samples = ['steps' => array_values($steps)];
        $e->results = $this->concurrencySummary(array_values($steps));
    }

    private function concurrencySummary(array $steps): array
    {
        $first = $steps[0] ?? [];
        $last = end($steps) ?: [];

        return [
            'levels' => array_column($steps, 'clients'),
            'max_clients' => $last['clients'] ?? null,
            'avg_at_one' => $first['avg_ms'] ?? null,
            'avg_at_max' => $last['avg_ms'] ?? null,
            'rate_at_max' => $last['req_per_sec'] ?? null,
            'slowdown' => ! empty($first['avg_ms']) && ! empty($last['avg_ms']) ? round($last['avg_ms'] / $first['avg_ms'], 2) : null,
            'errors' => array_sum(array_column($steps, 'errors')),
        ];
    }

    /* ---------------- shared load loop ---------------- */

    /**
     * Keep $clients parallel requests going for $duration seconds.
     * A response only counts when its status is below 400 and, for payload tests,
     * its body has exactly the requested size. Anything else is an error, so a 404
     * page can never be mistaken for goodput. No valid response at all fails the experiment.
     * Calls $progress(step, fraction) while running; returns the final step.
     */
    private function load(string $url, int $clients, int $duration, ?int $expectedBytes, callable $progress): array
    {
        $lastProblem = 'no response';
        $perSecond = array_fill(0, $duration, 0);
        $times = [];
        $bytes = 0;
        $errors = 0;
        $start = microtime(true);

        $step = function () use ($clients, &$perSecond, &$times, &$bytes, &$errors, $start) {
            $elapsed = max(microtime(true) - $start, 0.001);
            $done = count($times);

            return [
                'clients' => $clients,
                'elapsed' => round($elapsed, 2),
                'requests' => $done,
                'errors' => $errors,
                'req_per_sec' => round($done / $elapsed, 1),
                'goodput' => (int) round($bytes / $elapsed),   // payload bytes per second
                'avg_ms' => self::mean($times),
                'median_ms' => self::median($times),
                'p95_ms' => self::percentile($times, 95),
                'stddev_ms' => self::stddev($times),
                'per_second' => $perSecond,
            ];
        };

        while (microtime(true) - $start < $duration) {
            foreach ($this->burst($url, $clients) as $r) {
                $valid = $r['ok'] && $r['status'] < 400 && ($expectedBytes === null || $r['bytes'] === $expectedBytes);

                if ($valid) {
                    $times[] = $r['total'];
                    $bytes += $r['bytes'];
                    $perSecond[min($duration - 1, (int) floor(microtime(true) - $start))]++;
                } else {
                    $errors++;
                    $lastProblem = $r['ok'] ? 'HTTP '.$r['status'].', '.$r['bytes'].' bytes' : 'no response';
                }
            }

            $progress($step(), min(1, (microtime(true) - $start) / $duration));
        }

        if (! $times) {
            throw new RuntimeException("No valid response from {$url} (last: {$lastProblem}). Payload sizes need a target running PULSEMONITOR.");
        }

        return $step();
    }

    /** /api/v1/lab/payload on the target's host (the target must run PULSEMONITOR). */
    private function payloadUrl(string $target, int $bytes): string
    {
        $p = parse_url($target);
        $base = ($p['scheme'] ?? 'http').'://'.($p['host'] ?? 'localhost').(isset($p['port']) ? ':'.$p['port'] : '');

        return $base.'/api/v1/lab/payload?bytes='.$bytes;
    }

    /** One request with timing; ok = the server answered (any HTTP status), so the round trip happened. */
    private function request(string $url): array
    {
        try {
            return $this->timing(Http::timeout(self::REQUEST_TIMEOUT)->withUserAgent('PULSEMONITOR NetworkLab')->get($url));
        } catch (Throwable) {
            return ['ok' => false];
        }
    }

    /** $n requests at the same time. */
    private function burst(string $url, int $n): array
    {
        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn () => $pool->timeout(self::REQUEST_TIMEOUT)->withUserAgent('PULSEMONITOR NetworkLab')->get($url),
            range(1, $n),
        ));

        return array_map(fn ($r) => $r instanceof Response ? $this->timing($r) : ['ok' => false], $responses);
    }

    private function timing(Response $response): array
    {
        $s = $response->handlerStats();
        $ms = fn (string $key) => round(($s[$key] ?? 0) * 1000, 2);

        return [
            'ok' => true,
            'status' => $response->status(),
            'dns' => $ms('namelookup_time'),
            'rtt' => round(max(0, ($s['connect_time'] ?? 0) - ($s['namelookup_time'] ?? 0)) * 1000, 2),
            'ttfb' => $ms('starttransfer_time'),
            'total' => $ms('total_time'),
            'bytes' => (int) ($s['size_download'] ?? 0),
        ];
    }

    /** Save (and broadcast) at most ~3 times per second. */
    private function save(NetworkExperiment $e): void
    {
        if (microtime(true) - $this->lastSave >= 0.3) {
            $e->save();
            $this->lastSave = microtime(true);
        }
    }

    /* ---------------- statistics ---------------- */

    public static function mean(array $v): ?float
    {
        return $v ? round(array_sum($v) / count($v), 2) : null;
    }

    public static function median(array $v): ?float
    {
        return self::percentile($v, 50);
    }

    /** Sample standard deviation (n - 1). */
    public static function stddev(array $v): ?float
    {
        $n = count($v);
        if ($n < 2) {
            return $n ? 0.0 : null;
        }
        $m = array_sum($v) / $n;

        return round(sqrt(array_sum(array_map(fn ($x) => ($x - $m) ** 2, $v)) / ($n - 1)), 2);
    }

    /** Linear interpolation between closest ranks. */
    public static function percentile(array $v, float $p): ?float
    {
        if (! $v) {
            return null;
        }
        sort($v);
        $rank = ($p / 100) * (count($v) - 1);
        $lo = (int) floor($rank);
        $hi = (int) ceil($rank);

        return round($v[$lo] + ($v[$hi] - $v[$lo]) * ($rank - $lo), 2);
    }
}
