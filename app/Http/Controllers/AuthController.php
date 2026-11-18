<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Masuk dengan NRP dalam dua langkah:
 * 1. Ketik NRP → sistem memeriksa apakah terdaftar & sudah aktivasi.
 * 2a. Sudah aktivasi → masukkan PIN 6 angka.
 * 2b. Belum aktivasi → konfirmasi nama depan lalu buat PIN.
 */
class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    public function show(): View
    {
        return view('auth.login', ['nrp' => old('nrp', session('login.nrp'))]);
    }

    public function check(Request $request): RedirectResponse
    {
        $request->validate(['nrp' => ['required', 'string', 'max:30']], [], ['nrp' => 'NRP']);
        $nrp = User::normalizeNrp($request->input('nrp'));
        $user = User::firstWhere('nrp', $nrp);

        if (! $user) {
            return back()->withInput()->with('unregistered', $nrp);
        }
        if (! $user->is_active) {
            return back()->withInput()->withErrors(['nrp' => 'Akun NRP ini sedang dinonaktifkan. Hubungi admin bila ini keliru.']);
        }

        session(['login.nrp' => $nrp]);

        return redirect()->route($user->isActivated() ? 'login.pin' : 'activate');
    }

    public function pinForm(): View|RedirectResponse
    {
        $user = $this->pendingUser();
        if (! $user || ! $user->isActivated()) {
            return redirect()->route('login');
        }

        return view('auth.pin', ['user' => $user]);
    }

    public function pin(Request $request): RedirectResponse
    {
        $user = $this->pendingUser();
        if (! $user) {
            return redirect()->route('login');
        }
        $request->validate(['pin' => ['required', 'digits:6']], ['pin.digits' => 'PIN terdiri dari 6 angka.']);

        $key = $this->throttleKey($request, $user->nrp);
        $this->ensureNotLocked($key, 'pin');

        if (! Auth::attempt(['nrp' => $user->nrp, 'password' => $request->input('pin'), 'is_active' => true], $request->boolean('remember', true))) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $left = RateLimiter::remaining($key, self::MAX_ATTEMPTS);
            throw ValidationException::withMessages([
                'pin' => $left > 0 ? "PIN salah. Sisa percobaan: $left." : 'PIN salah 5 kali. Coba lagi 5 menit lagi atau minta reset ke admin.',
            ]);
        }

        RateLimiter::clear($key);

        return $this->completeLogin($request);
    }

    public function activateForm(): View|RedirectResponse
    {
        $user = $this->pendingUser();
        if (! $user || $user->isActivated()) {
            return redirect()->route('login');
        }

        return view('auth.activate', ['user' => $user, 'hint' => $this->nameHint($user->name)]);
    }

    public function activate(Request $request): RedirectResponse
    {
        $user = $this->pendingUser();
        if (! $user || $user->isActivated()) {
            return redirect()->route('login');
        }

        $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'pin' => ['required', 'digits:6', 'confirmed', function ($attr, $value, $fail) {
                if (preg_match('/^(\d)\1{5}$/', $value) || in_array($value, ['123456', '654321', '012345', '543210'], true)) {
                    $fail('PIN terlalu mudah ditebak. Hindari angka berurutan atau sama semua.');
                }
            }],
        ], [
            'pin.digits' => 'PIN terdiri dari 6 angka.',
            'pin.confirmed' => 'Ulangi PIN tidak sama.',
        ], ['first_name' => 'nama depan']);

        $key = $this->throttleKey($request, $user->nrp);
        $this->ensureNotLocked($key, 'first_name');

        $typed = Str::upper(Str::before(trim(preg_replace('/\s+/', ' ', $request->input('first_name'))).' ', ' '));
        $actual = Str::upper(Str::before($user->name.' ', ' '));
        if ($typed !== $actual) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            throw ValidationException::withMessages(['first_name' => 'Nama depan tidak cocok dengan data NRP ini.']);
        }
        RateLimiter::clear($key);

        $user->forceFill(['pin' => $request->input('pin'), 'activated_at' => now()])->save();
        Auth::login($user, true);
        ActivityLog::record('aktivasi', "{$user->name} ({$user->nrp}) mengaktifkan akun");

        return $this->completeLogin($request, 'Akun aktif! Selamat datang di AstoOne.');
    }

    public function forget(Request $request): RedirectResponse
    {
        $request->session()->forget('login.nrp');

        return redirect()->route('login');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function completeLogin(Request $request, ?string $message = null): RedirectResponse
    {
        $request->session()->regenerate();
        $request->session()->forget('login.nrp');
        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('home'))->with('toast', $message);
    }

    private function pendingUser(): ?User
    {
        $nrp = session('login.nrp');

        return $nrp ? User::where('nrp', $nrp)->where('is_active', true)->first() : null;
    }

    private function throttleKey(Request $request, string $nrp): string
    {
        return 'login:'.$nrp.'|'.$request->ip();
    }

    private function ensureNotLocked(string $key, string $field): void
    {
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            throw ValidationException::withMessages([$field => "Terlalu banyak percobaan. Coba lagi dalam $minutes menit atau hubungi admin."]);
        }
    }

    /** Petunjuk tanpa membuka nama lengkap: "A•••• (5 huruf)". */
    private function nameHint(string $name): string
    {
        $first = Str::before($name.' ', ' ');

        return Str::substr($first, 0, 1).str_repeat('•', max(0, Str::length($first) - 1)).' ('.Str::length($first).' huruf)';
    }
}
