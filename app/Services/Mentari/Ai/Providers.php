<?php

namespace App\Services\Mentari\Ai;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Daftar penyedia AI gratis, kuota harian (perkiraan, dihitung AstoOne sendiri), jeda saat gangguan,
 * dan batas pertanyaan AI per karyawan per hari. Semua penghitung di cache (tanpa tabel).
 */
class Providers
{
    /** Penyedia yang diatur (punya kunci), berurutan sesuai MENTARI_AI_ORDER. */
    public function configured(): array
    {
        $all = config('services.mentari.ai.providers', []);
        $order = array_filter(array_map('trim', explode(',', (string) config('services.mentari.ai.order', ''))));
        $out = [];
        foreach ($order as $name) {
            if (isset($all[$name]) && filled($all[$name]['key'] ?? null) && filled($all[$name]['model'] ?? null)) {
                $out[$name] = $all[$name] + ['name' => $name];
            }
        }

        return $out;
    }

    public function enabled(): bool
    {
        return Setting::get('mentari_ai_enabled', '1') === '1' && $this->configured() !== [];
    }

    /** Penyedia yang siap dipakai sekarang (tidak sedang jeda, kuota harian belum habis). */
    public function ready(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return array_filter($this->configured(), fn ($p) => ! $this->coolingUntil($p['name']) && $this->usedToday($p['name']) < (int) $p['daily']);
    }

    public function perUserLimit(): int
    {
        return max(0, (int) Setting::get('mentari_ai_per_user', (string) config('services.mentari.ai.per_user_daily', 25)));
    }

    public function userLeft(User $user): int
    {
        return max(0, $this->perUserLimit() - (int) Cache::get($this->userKey($user), 0));
    }

    public function chargeUser(User $user): void
    {
        $this->bump($this->userKey($user));
    }

    public function consume(string $provider): void
    {
        $this->bump('mentari:ai:use:'.$provider.':'.now()->format('Ymd'));
    }

    public function usedToday(string $provider): int
    {
        return (int) Cache::get('mentari:ai:use:'.$provider.':'.now()->format('Ymd'), 0);
    }

    /** Jeda penyedia (kuota habis / kunci salah / gangguan) lalu catat sebabnya untuk panel admin. */
    public function coolDown(string $provider, int $seconds, string $reason): void
    {
        Cache::put('mentari:ai:cool:'.$provider, now()->addSeconds($seconds)->timestamp, $seconds);
        Cache::put('mentari:ai:err:'.$provider, ['at' => now()->format('d/m H:i'), 'reason' => mb_substr($reason, 0, 200)], now()->addDays(2));
    }

    public function coolingUntil(string $provider): ?int
    {
        $until = Cache::get('mentari:ai:cool:'.$provider);

        return $until && $until > time() ? (int) $until : null;
    }

    public function markOk(string $provider): void
    {
        Cache::put('mentari:ai:ok:'.$provider, now()->format('d/m H:i'), now()->addDays(2));
    }

    /** Ringkasan untuk panel admin (tanpa kunci API). */
    public function status(): array
    {
        $configured = $this->configured();

        return collect(config('services.mentari.ai.providers', []))->map(function ($p, $name) use ($configured) {
            $until = $this->coolingUntil($name);

            return [
                'name' => $name, 'label' => $p['label'], 'model' => $p['model'], 'configured' => isset($configured[$name]),
                'personal' => (bool) $p['personal'], 'daily' => (int) $p['daily'], 'used' => $this->usedToday($name),
                'cooling' => $until ? date('H:i', $until) : null,
                'error' => Cache::get('mentari:ai:err:'.$name), 'ok' => Cache::get('mentari:ai:ok:'.$name),
            ];
        })->values()->all();
    }

    private function userKey(User $user): string
    {
        return 'mentari:ai:user:'.$user->id.':'.now()->format('Ymd');
    }

    private function bump(string $key): void
    {
        Cache::add($key, 0, now()->addDays(2));
        Cache::increment($key);
    }
}
