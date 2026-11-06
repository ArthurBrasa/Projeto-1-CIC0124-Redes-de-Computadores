<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Non-sensitive demo data: one user, two independent clients (applications with
 * their own tokens), one public monitor and some past events.
 * The credentials below are public on purpose and exist only for local testing.
 */
class DatabaseSeeder extends Seeder
{
    public const EMAIL = 'demo@pulsemonitor.test';

    public const PASSWORD = 'password';

    public const CLIENTS = [
        'sensor-lab-a' => ['public_id' => 'app_sensorlaba', 'token' => 'pm_demo_sensor_a_0000000000000000000000000'],
        'sensor-lab-b' => ['public_id' => 'app_sensorlabb', 'token' => 'pm_demo_sensor_b_0000000000000000000000000'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must not be seeded in production.');
        }

        $user = User::firstOrCreate(
            ['email' => self::EMAIL],
            ['name' => 'Demo Player', 'password' => self::PASSWORD],
        );

        foreach (self::CLIENTS as $name => $client) {
            $app = Application::where('public_id', $client['public_id'])->first()
                ?? $user->applications()->make(['name' => $name, 'description' => 'Simulated client', 'environment' => 'development', 'url' => 'http://localhost:8000/up']);

            $app->forceFill([
                'public_id' => $client['public_id'],
                'token_hash' => hash('sha256', $client['token']),
                'token_prefix' => substr($client['token'], 0, 10),
                'token_rotated_at' => now(),
            ])->save();

            if ($app->events()->doesntExist()) {
                ApplicationEvent::withoutEvents(function () use ($app) {
                    foreach (range(1, 20) as $i) {
                        $level = $i % 10 === 0 ? 'error' : ($i % 4 === 0 ? 'warning' : 'info');
                        $event = $app->events()->create([
                            'level' => $level,
                            'message' => ucfirst($level).' reading #'.$i,
                            'source' => 'seeder',
                            'payload' => ['reading' => $i],
                        ]);
                        $event->forceFill(['created_at' => now()->subMinutes(20 - $i)])->save();
                    }
                });
            }
        }

        $user->monitors()->firstOrCreate(
            ['name' => 'Local health'],
            ['url' => env('DEMO_MONITOR_URL', config('app.url').'/up'), 'application_id' => Application::where('public_id', 'app_sensorlaba')->value('id'), 'is_public' => true],
        );

        $this->command?->info('Login: '.self::EMAIL.' / '.self::PASSWORD);
        foreach (self::CLIENTS as $name => $client) {
            $this->command?->line("  {$name}: POST /api/v1/hooks/{$client['public_id']}  Bearer {$client['token']}");
        }
    }
}
