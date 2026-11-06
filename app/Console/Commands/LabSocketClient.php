<?php

namespace App\Console\Commands;

use App\Jobs\RunNetworkExperiment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Experiment D: TCP vs UDP client, same message format and load for both.
 *
 * Message: "<seq>|<send time in µs>|<padding>\n", padded to --size bytes.
 *
 *  pingpong  send one message, wait for its echo, repeat -> RTT per message, losses (timeouts)
 *  burst     send all messages as fast as possible, then collect echoes -> loss, reordering, rate
 *
 *   php artisan lab:socket-client --proto=tcp --mode=pingpong --count=100 --size=64
 *   php artisan lab:socket-client --proto=udp --mode=burst --count=1000 --size=512
 *   php artisan lab:socket-client --proto=both ...   (runs TCP then UDP and prints a comparison)
 */
#[Signature('lab:socket-client
    {--host=127.0.0.1}
    {--port=9601}
    {--proto=both : tcp, udp or both}
    {--mode=pingpong : pingpong or burst}
    {--count=100 : messages to send}
    {--size=64 : bytes per message (16 .. 8192)}
    {--timeout=1000 : pingpong: ms to wait for each echo. burst: ms of silence that ends the wait}
    {--label= : condition name, used in the file names}
    {--out= : output directory (default storage/app/lab)}')]
#[Description('TCP vs UDP micro test against lab:socket-server (Experiment D)')]
class LabSocketClient extends Command
{
    public function handle(): int
    {
        $protos = $this->option('proto') === 'both' ? ['tcp', 'udp'] : [$this->option('proto')];
        $mode = $this->option('mode');
        $count = max(1, (int) $this->option('count'));
        $size = max(16, min(8192, (int) $this->option('size')));
        $timeoutMs = max(10, (int) $this->option('timeout'));

        if (array_diff($protos, ['tcp', 'udp']) || ! in_array($mode, ['pingpong', 'burst'])) {
            $this->error('Use --proto=tcp|udp|both and --mode=pingpong|burst');

            return self::INVALID;
        }

        $rows = [];
        $summaries = [];
        foreach ($protos as $proto) {
            $this->info(strtoupper($proto)." {$mode}: {$count} messages of {$size} bytes to {$this->option('host')}:{$this->option('port')}");
            $result = $this->measure($proto, $mode, $count, $size, $timeoutMs);

            if ($result === null) {
                return self::FAILURE;
            }

            $rows[] = $result['summary'];
            $summaries[] = $result['summary'] + ['size' => $size, 'condition' => $this->option('label')];
            $this->saveCsv($proto, $mode, $size, $result['samples']);
            Log::channel('protocol')->info('lab.socket.client.done', $result['summary']);
        }

        if ($this->option('label')) {
            $dir = $this->option('out') ?: storage_path('app/lab');
            File::ensureDirectoryExists($dir);
            File::put($dir.'/'.$this->option('label').'-'.$mode.'-'.$size.'b.json', json_encode($summaries, JSON_PRETTY_PRINT));
        }

        $this->newLine();
        $this->table(
            ['proto', 'mode', 'sent', 'received', 'loss %', 'out of order', 'rtt mean ms', 'rtt median ms', 'rtt std dev ms', 'rtt p95 ms', 'msgs/s', 'payload KB/s', 'connect ms'],
            array_map(fn ($r) => array_values($r), $rows),
        );
        $this->line('CSV files in storage/app/lab/. Connect ms = TCP 3-way handshake (UDP has none).');

        return self::SUCCESS;
    }

    private function measure(string $proto, string $mode, int $count, int $size, int $timeoutMs): ?array
    {
        $address = "{$proto}://{$this->option('host')}:{$this->option('port')}";
        $t0 = hrtime(true);
        $sock = @stream_socket_client($address, $errno, $errstr, 3);
        $connectMs = round((hrtime(true) - $t0) / 1e6, 3);

        if (! $sock) {
            $this->error("Could not connect to {$address}: {$errstr}. Is lab:socket-server running?");

            return null;
        }

        $sent = [];      // seq => send time (ns)
        $samples = [];   // seq => [rtt_ms, arrival_index]
        $arrival = 0;
        $outOfOrder = 0;
        $lastSeq = -1;
        $buffer = '';
        $start = hrtime(true);
        $lastArrival = $start;

        $read = function (int $waitMs) use ($sock, $proto, &$buffer): array {
            // returns the complete messages available within $waitMs
            $r = [$sock];
            $w = $e = null;
            if (! @stream_select($r, $w, $e, 0, $waitMs * 1000)) {
                return [];
            }
            if ($proto === 'udp') {
                $d = stream_socket_recvfrom($sock, 65535);

                return $d === false || $d === '' ? [] : [rtrim($d, "\n")];
            }
            $chunk = fread($sock, 65535);
            if ($chunk === false || $chunk === '') {
                return [];
            }
            $buffer .= $chunk;
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines);  // keep the incomplete tail

            return $lines;
        };

        $record = function (string $msg) use (&$sent, &$samples, &$arrival, &$outOfOrder, &$lastSeq, &$lastArrival) {
            $seq = (int) strtok($msg, '|');
            if (! isset($sent[$seq]) || isset($samples[$seq])) {
                return; // unknown or duplicate
            }
            if ($seq < $lastSeq) {
                $outOfOrder++;
            }
            $lastSeq = max($lastSeq, $seq);
            $lastArrival = hrtime(true);
            $samples[$seq] = ['rtt' => round(($lastArrival - $sent[$seq]) / 1e6, 3), 'arrival' => $arrival++];
        };

        $message = fn (int $seq) => str_pad($seq.'|'.intdiv(hrtime(true), 1000).'|', $size - 1, 'x')."\n";

        if ($mode === 'pingpong') {
            for ($seq = 0; $seq < $count; $seq++) {
                $sent[$seq] = hrtime(true);
                $proto === 'udp' ? stream_socket_sendto($sock, $message($seq)) : fwrite($sock, $message($seq));

                $deadline = hrtime(true) + $timeoutMs * 1e6;
                while (! isset($samples[$seq]) && hrtime(true) < $deadline) {
                    foreach ($read(max(1, (int) (($deadline - hrtime(true)) / 1e6))) as $msg) {
                        $record($msg);
                    }
                }
            }
        } else {
            for ($seq = 0; $seq < $count; $seq++) {
                $sent[$seq] = hrtime(true);
                $proto === 'udp' ? stream_socket_sendto($sock, $message($seq)) : fwrite($sock, $message($seq));
                foreach ($read(0) as $msg) {
                    $record($msg);       // collect echoes that already came back
                }
            }
            // TCP echoes may still be in flight or being retransmitted: stop only after a silence
            while (count($samples) < $count && hrtime(true) < $lastArrival + $timeoutMs * 1e6) {
                foreach ($read(50) as $msg) {
                    $record($msg);
                }
            }
        }

        // the rate ends at the last echo, so the final wait for lost messages does not distort it
        $elapsed = max(($lastArrival - $start) / 1e9, 0.000001);
        fclose($sock);

        $rtts = array_column($samples, 'rtt');
        $received = count($samples);

        $rows = [];
        for ($seq = 0; $seq < $count; $seq++) {
            $rows[] = [$seq, isset($samples[$seq]) ? 1 : 0, $samples[$seq]['rtt'] ?? '', $samples[$seq]['arrival'] ?? ''];
        }

        return [
            'samples' => $rows,
            'summary' => [
                'proto' => $proto,
                'mode' => $mode,
                'sent' => $count,
                'received' => $received,
                'loss' => round(($count - $received) / $count * 100, 2),
                'out_of_order' => $outOfOrder,
                'rtt_mean' => RunNetworkExperiment::mean($rtts),
                'rtt_median' => RunNetworkExperiment::median($rtts),
                'rtt_stddev' => RunNetworkExperiment::stddev($rtts),
                'rtt_p95' => RunNetworkExperiment::percentile($rtts, 95),
                'msgs_per_sec' => round($received / $elapsed, 1),
                'payload_kb_s' => round($received * $size / 1024 / $elapsed, 1),
                'connect_ms' => $proto === 'tcp' ? $connectMs : '—',
            ],
        ];
    }

    private function saveCsv(string $proto, string $mode, int $size, array $rows): void
    {
        $dir = $this->option('out') ?: storage_path('app/lab');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/'.($this->option('label') ?: 'socket-'.now()->local()->format('Ymd-His')).'-'.$proto.'-'.$mode.'-'.$size.'b.csv';

        $out = fopen($file, 'w');
        fputcsv($out, ['seq', 'received', 'rtt_ms', 'arrival_order']);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
    }
}
