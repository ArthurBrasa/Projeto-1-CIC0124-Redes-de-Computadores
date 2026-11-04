<?php

namespace App\Jobs;

use App\Models\NetworkExperiment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs one Network Lab experiment against a URL and stores the measurements.
 *
 * Timings come from cURL (Guzzle handler stats):
 *  - dns   = name lookup time
 *  - rtt   = TCP handshake (connect_time - namelookup_time): one network round trip
 *  - ttfb  = time to first byte
 *  - total = whole request
 * Every request uses a new connection, so each one includes a fresh TCP handshake.
 */
class RunNetworkExperiment implements ShouldQueue
{
    use Queueable;

    public int $timeout = 80;

    public int $tries = 1;

    private const REQUEST_TIMEOUT = 10;

    private float $lastSave = 0;

    public function __construct(public NetworkExperiment $experiment) {}

    public function handle(): void
    {
        $e = $this->experiment;
        $e->forceFill(['status' => 'running', 'progress' => 0, 'started_at' => now()])->save();

        try {
            match ($e->type) {
                NetworkExperiment::RTT => $this->rtt($e),
                NetworkExperiment::THROUGHPUT => $this->throughput($e),
                NetworkExperiment::CONCURRENCY => $this->concurrency($e),
            };

            $e->forceFill(['status' => 'done', 'progress' => 100, 'finished_at' => now()])->save();
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
    }

    /* ---------------- RTT: sequential requests, one new connection each ---------------- */

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

        return [
            'samples' => count($samples),
            'received' => count($ok),
            'loss' => count($samples) ? round((count($samples) - count($ok)) / count($samples) * 100, 1) : 0,
            'rtt_avg' => $rtt ? round(array_sum($rtt) / count($rtt), 2) : null,
            'rtt_min' => $rtt ? min($rtt) : null,
            'rtt_max' => $rtt ? max($rtt) : null,
            'jitter' => $diffs ? round(array_sum($diffs) / count($diffs), 2) : 0,
            'dns_avg' => $ok ? round(array_sum(array_column($ok, 'dns')) / count($ok), 2) : null,
            'ttfb_avg' => $ok ? round(array_sum(array_column($ok, 'ttfb')) / count($ok), 2) : null,
            'total_avg' => $total ? round(array_sum($total) / count($total), 2) : null,
        ];
    }

    /* ---------------- Throughput: N parallel clients for X seconds ---------------- */

    private function throughput(NetworkExperiment $e): void
    {
        $clients = $e->params['clients'];
        $duration = $e->params['duration'];
        $perSecond = array_fill(0, $duration, 0);
        $bytes = 0;
        $done = 0;
        $errors = 0;
        $times = [];
        $start = microtime(true);

        while (($elapsed = microtime(true) - $start) < $duration) {
            foreach ($this->burst($e->target_url, $clients) as $r) {
                $second = min($duration - 1, (int) floor(microtime(true) - $start));

                if ($r['ok']) {
                    $done++;
                    $bytes += $r['bytes'];
                    $times[] = $r['total'];
                    $perSecond[$second]++;
                } else {
                    $errors++;
                }
            }

            $elapsed = microtime(true) - $start;
            $e->samples = ['per_second' => $perSecond];
            $e->results = [
                'clients' => $clients,
                'duration' => round($elapsed, 1),
                'requests' => $done,
                'errors' => $errors,
                'req_per_sec' => round($done / max($elapsed, 0.001), 1),
                'bytes_per_sec' => (int) round($bytes / max($elapsed, 0.001)),
                'avg_ms' => $times ? round(array_sum($times) / count($times), 1) : null,
            ];
            $e->progress = min(99, (int) floor($elapsed / $duration * 100));
            $this->save($e);
        }
    }

    /* ---------------- Concurrency: response time as parallel clients grow ---------------- */

    private function concurrency(NetworkExperiment $e): void
    {
        $max = $e->params['clients'];
        $levels = collect([1, $max / 4, $max / 2, $max * 3 / 4, $max])
            ->map(fn ($v) => max(1, (int) ceil($v)))->unique()->sort()->values();

        $steps = [];

        foreach ($levels as $i => $level) {
            $results = $this->burst($e->target_url, $level);
            $ok = array_filter($results, fn ($r) => $r['ok']);
            $times = array_column($ok, 'total');

            $steps[] = [
                'clients' => $level,
                'avg_ms' => $times ? round(array_sum($times) / count($times), 1) : null,
                'max_ms' => $times ? max($times) : null,
                'errors' => count($results) - count($ok),
            ];

            $last = end($steps);
            $first = $steps[0];
            $e->samples = ['steps' => $steps];
            $e->results = [
                'max_clients' => $level,
                'avg_at_one' => $first['avg_ms'],
                'avg_at_max' => $last['avg_ms'],
                'slowdown' => $first['avg_ms'] && $last['avg_ms'] ? round($last['avg_ms'] / $first['avg_ms'], 1) : null,
                'errors' => array_sum(array_column($steps, 'errors')),
            ];
            $e->progress = (int) floor(($i + 1) / $levels->count() * 100);
            $this->save($e);

            usleep(300_000);
        }
    }

    /* ---------------- helpers ---------------- */

    /** One request with timing; ok = the server answered (any HTTP status). */
    private function request(string $url): array
    {
        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)->withUserAgent('PULSEMONITOR NetworkLab')->get($url);

            return $this->timing($response);
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
}
