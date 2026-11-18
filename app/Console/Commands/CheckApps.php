<?php

namespace App\Console\Commands;

use App\Models\AppLink;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Mengecek apakah setiap aplikasi bisa dihubungi, untuk lampu status
 * di portal. Dijalankan scheduler tiap 10 menit.
 */
class CheckApps extends Command
{
    protected $signature = 'astoone:check-apps';

    protected $description = 'Cek status online aplikasi yang terdaftar di portal';

    public function handle(): int
    {
        $apps = AppLink::active()->where('health_check', true)->get();
        if ($apps->isEmpty()) {
            return self::SUCCESS;
        }

        $started = [];
        $responses = Http::pool(function (Pool $pool) use ($apps, &$started) {
            foreach ($apps as $app) {
                $started[$app->id] = microtime(true);
                $pool->as((string) $app->id)
                    ->withHeaders(['User-Agent' => 'AstoOne-HealthCheck/1.0'])
                    ->withOptions(['allow_redirects' => ['max' => 5]])
                    ->timeout(8)->connectTimeout(5)
                    ->get($app->url);
            }
        });

        foreach ($apps as $app) {
            $response = $responses[(string) $app->id] ?? null;
            $up = $response instanceof \Illuminate\Http\Client\Response && $response->status() < 500;
            $app->forceFill([
                'health_status' => $up ? 'up' : 'down',
                'health_ms' => $up ? (int) min(65535, (microtime(true) - $started[$app->id]) * 1000) : null,
                'health_checked_at' => now(),
            ])->saveQuietly();
            $this->line(sprintf('%-28s %s', $app->name, $up ? 'UP' : 'DOWN'));
        }
        Cache::forget('mentari:portal');

        return self::SUCCESS;
    }
}
