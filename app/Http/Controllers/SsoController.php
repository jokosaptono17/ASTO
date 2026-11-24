<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Masuk dengan AstoOne" untuk MekanikPintar (LMS).
 *
 * Alur: LMS → /sso/mekanikpintar?state=…&return=… → (login NRP + PIN bila belum)
 * → layar konfirmasi → surat jalan bertanda tangan HMAC-SHA256 kembali ke LMS.
 * Surat jalan berisi NRP & nama, berlaku 90 detik, sekali pakai (nonce), terikat
 * pada "state" dari HP yang sama. Rahasia: MEKANIKPINTAR_SSO_SECRET (sama dengan
 * ASTOONE_SSO_SECRET di LMS). Alamat kembali hanya yang terdaftar di konfigurasi.
 */
class SsoController extends Controller
{
    private const TTL = 90;

    public function show(Request $request): View|RedirectResponse
    {
        abort_unless($this->enabled(), 404);
        $state = (string) $request->query('state', '');
        $return = (string) $request->query('return', '');
        abort_unless(preg_match('/^[A-Za-z0-9]{20,80}$/', $state) && $this->allowedReturn($return), 400, 'Permintaan masuk tidak sah.');

        $user = $request->user();
        if (! $user->isActivated() || ! $user->is_active) {
            Auth::logout();

            return redirect()->route('login');
        }
        $request->session()->put('sso.mekanikpintar', ['state' => $state, 'return' => $return, 'at' => time()]);

        return view('sso.mekanikpintar', ['user' => $user]);
    }

    public function approve(Request $request): RedirectResponse
    {
        abort_unless($this->enabled(), 404);
        $pending = $request->session()->pull('sso.mekanikpintar');
        abort_unless(is_array($pending) && time() - ($pending['at'] ?? 0) <= 600 && $this->allowedReturn($pending['return'] ?? ''), 419, 'Sesi konfirmasi habis. Ulangi dari MekanikPintar.');

        $user = $request->user();
        $now = time();
        $payload = self::b64u(json_encode([
            'aud' => 'mekanikpintar',
            'nrp' => $user->nrp,
            'name' => $user->name,
            'activated_at' => $user->activated_at?->toIso8601String(),
            'iat' => $now,
            'exp' => $now + self::TTL,
            'nonce' => Str::random(32),
            'state' => $pending['state'],
        ], JSON_UNESCAPED_UNICODE));
        $sig = self::b64u(hash_hmac('sha256', $payload, (string) config('services.mekanikpintar.secret'), true));

        ActivityLog::record('sso', "{$user->name} ({$user->nrp}) masuk ke MekanikPintar lewat AstoOne");

        return redirect()->away($pending['return'].'?'.http_build_query(['assertion' => $payload, 'sig' => $sig]));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $pending = $request->session()->pull('sso.mekanikpintar');
        $return = is_array($pending) && $this->allowedReturn($pending['return'] ?? '') ? $pending['return'] : (string) config('services.mekanikpintar.callback');

        return redirect()->away($return.'?error=cancelled');
    }

    /** "Bukan saya": keluar dari AstoOne lalu masuk dengan NRP lain, kembali ke layar ini. */
    public function switchAccount(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('sso.mekanikpintar');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if (is_array($pending)) {
            $request->session()->put('url.intended', route('sso.mekanikpintar', ['state' => $pending['state'], 'return' => $pending['return']]));
        }

        return redirect()->route('login');
    }

    private function enabled(): bool
    {
        return filled(config('services.mekanikpintar.secret')) && filled(config('services.mekanikpintar.callback'));
    }

    /** Hanya alamat kembali yang terdaftar (mencegah surat jalan dikirim ke situs lain). */
    private function allowedReturn(string $url): bool
    {
        return $url !== '' && hash_equals((string) config('services.mekanikpintar.callback'), $url);
    }

    private static function b64u(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
