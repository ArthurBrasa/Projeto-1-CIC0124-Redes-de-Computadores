<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Experiment D: echo server for the TCP vs UDP micro test.
 *
 * TCP: messages are lines ending in "\n" (TCP is a byte stream, so we need framing);
 *      every line is sent back as is.
 * UDP: every datagram is sent back as is to whoever sent it (no connection, no framing).
 *
 *   php artisan lab:socket-server --port=9601
 */
#[Signature('lab:socket-server {--host=0.0.0.0} {--port=9601} {--drop=0 : % of UDP datagrams to drop on purpose (simulated loss)}')]
#[Description('TCP + UDP echo server for the transport experiment (Experiment D)')]
class LabSocketServer extends Command
{
    public function handle(): int
    {
        $host = $this->option('host');
        $port = (int) $this->option('port');
        $drop = max(0, min(100, (int) $this->option('drop')));

        $tcp = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        $udp = @stream_socket_server("udp://{$host}:{$port}", $uerrno, $uerrstr, STREAM_SERVER_BIND);

        if (! $tcp || ! $udp) {
            $this->error('Could not open sockets: '.($errstr ?: $uerrstr));

            return self::FAILURE;
        }

        stream_set_blocking($tcp, false);
        $this->info("Echo server on tcp://{$host}:{$port} and udp://{$host}:{$port}".($drop ? " (dropping {$drop}% of UDP)" : ''));
        Log::channel('protocol')->info('lab.socket.server.started', compact('host', 'port', 'drop'));

        $clients = [];   // TCP connections
        $buffers = [];   // partial lines per connection
        $pending = [];   // echo bytes the kernel did not accept yet, per connection
        $stats = ['tcp_msgs' => 0, 'udp_msgs' => 0, 'udp_dropped' => 0];
        $lastReport = time();

        while (true) {
            $read = array_merge([$tcp, $udp], $clients);
            $write = array_values(array_intersect_key($clients, array_filter($pending)));
            $except = null;

            if (@stream_select($read, $write, $except, 1) === false) {
                continue;
            }

            // non-blocking sockets can accept only part of a write: keep the rest for later
            foreach ($write as $sock) {
                $id = (int) $sock;
                $sent = @fwrite($sock, $pending[$id]);
                $pending[$id] = $sent ? (string) substr($pending[$id], $sent) : $pending[$id];
            }

            foreach ($read as $sock) {
                if ($sock === $tcp) {                       // new TCP connection (3-way handshake done)
                    $conn = @stream_socket_accept($tcp, 0, $peer);
                    if ($conn) {
                        stream_set_blocking($conn, false);
                        $clients[(int) $conn] = $conn;
                        $buffers[(int) $conn] = '';
                        $pending[(int) $conn] = '';
                        $this->line("  tcp connect    {$peer}");
                        Log::channel('protocol')->info('lab.socket.tcp.connect', ['peer' => $peer]);
                    }
                } elseif ($sock === $udp) {                 // one UDP datagram
                    $data = stream_socket_recvfrom($udp, 65535, 0, $peer);
                    if ($data === false || $data === '') {
                        continue;
                    }
                    if ($drop && random_int(1, 100) <= $drop) {
                        $stats['udp_dropped']++;

                        continue;
                    }
                    stream_socket_sendto($udp, $data, 0, $peer);
                    $stats['udp_msgs']++;
                } else {                                     // data on a TCP connection
                    $id = (int) $sock;
                    $chunk = fread($sock, 65535);

                    if ($chunk === '' || $chunk === false) {
                        if (feof($sock)) {                   // client closed (FIN)
                            $this->line('  tcp disconnect '.stream_socket_get_name($sock, true));
                            Log::channel('protocol')->info('lab.socket.tcp.disconnect', ['peer' => stream_socket_get_name($sock, true)]);
                            fclose($sock);
                            unset($clients[$id], $buffers[$id], $pending[$id]);
                        }

                        continue;
                    }

                    $buffers[$id] .= $chunk;
                    while (($pos = strpos($buffers[$id], "\n")) !== false) {
                        $line = substr($buffers[$id], 0, $pos + 1);
                        $buffers[$id] = substr($buffers[$id], $pos + 1);
                        $pending[$id] .= $line;
                        $stats['tcp_msgs']++;
                    }
                }
            }

            if (time() - $lastReport >= 10 && array_sum($stats)) {
                $this->line(sprintf('  echoed tcp=%d udp=%d (udp dropped=%d) · %d tcp clients', $stats['tcp_msgs'], $stats['udp_msgs'], $stats['udp_dropped'], count($clients)));
                $lastReport = time();
            }
        }
    }
}
