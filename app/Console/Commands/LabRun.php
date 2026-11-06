<?php

namespace App\Console\Commands;

use App\Jobs\RunNetworkExperiment;
use App\Models\NetworkExperiment;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Runs a Network Lab experiment from the terminal (same code as the web page) and saves
 * its summary as JSON and its samples as CSV, so experiments are repeatable and scriptable.
 *
 *   php artisan lab:run rtt --target=http://localhost:8000/up --samples=30 --label=loopback
 *   php artisan lab:run throughput --target=http://localhost:8000 --size=all --clients=5 --duration=10
 *   php artisan lab:run concurrency --target=http://localhost:8000/up --levels=1,2,5 --duration=10
 */
#[Signature('lab:run
    {type : rtt, throughput or concurrency}
    {--target=http://localhost:8000/up : URL to measure}
    {--samples=30 : RTT samples}
    {--clients=5 : parallel clients for throughput}
    {--duration=10 : seconds per step}
    {--size=all : throughput payload: target, all or a number of bytes}
    {--levels=1,2,5 : concurrency levels}
    {--label=run : condition name, used in the file names}
    {--out= : output directory (default storage/app/lab)}')]
#[Description('Run a Network Lab experiment from the terminal and save JSON + CSV')]
class LabRun extends Command
{
    public function handle(): int
    {
        $type = $this->argument('type');

        if (! in_array($type, NetworkExperiment::TYPES)) {
            $this->error('Type must be rtt, throughput or concurrency.');

            return self::INVALID;
        }

        $user = User::first();
        if (! $user) {
            $this->error('No user found. Run: php artisan db:seed');

            return self::FAILURE;
        }

        $size = $this->option('size');
        $params = match ($type) {
            NetworkExperiment::RTT => ['samples' => max(1, (int) $this->option('samples'))],
            NetworkExperiment::THROUGHPUT => [
                'clients' => max(1, (int) $this->option('clients')),
                'duration' => max(1, (int) $this->option('duration')),
                'sizes' => $size === 'target' ? [null] : ($size === 'all' ? [1024, 10240, 102400, 1048576] : [(int) $size]),
            ],
            NetworkExperiment::CONCURRENCY => [
                'levels' => array_map('intval', explode(',', $this->option('levels'))),
                'duration' => max(1, (int) $this->option('duration')),
            ],
        };

        $experiment = $user->networkExperiments()->create([
            'type' => $type,
            'target_url' => $this->option('target'),
            'params' => $params,
            'status' => 'queued',
        ]);

        RunNetworkExperiment::dispatchSync($experiment);
        $experiment->refresh();

        if ($experiment->status !== 'done') {
            $this->error('Experiment failed: '.$experiment->error);

            return self::FAILURE;
        }

        $dir = $this->option('out') ?: storage_path('app/lab');
        File::ensureDirectoryExists($dir);
        $base = $dir.'/'.$this->option('label').'-'.$type;

        File::put($base.'.json', json_encode([
            'condition' => $this->option('label'),
            'type' => $type,
            'target' => $experiment->target_url,
            'params' => $params,
            'started_at' => $experiment->started_at->toIso8601String(),
            'finished_at' => $experiment->finished_at->toIso8601String(),
            'results' => $experiment->results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $out = fopen($base.'.csv', 'w');
        foreach ($experiment->csvRows() as $row) {
            fputcsv($out, $row);
        }
        fclose($out);

        $this->info(strtoupper($type).' '.$this->option('label').': '.$experiment->resultLabel());
        $this->line('Saved '.$base.'.json and .csv');

        return self::SUCCESS;
    }
}
