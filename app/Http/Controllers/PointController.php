<?php

namespace App\Http\Controllers;

use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use App\Services\Points\PointException;
use App\Services\Points\PointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/** Poin HEBAT dari sisi karyawan: dompet, riwayat, katalog hadiah, kupon. */
class PointController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = in_array($request->query('riwayat'), ['masuk', 'keluar'], true) ? $request->query('riwayat') : 'semua';
        $tx = PointTransaction::where('user_id', $user->id)
            ->when($filter === 'masuk', fn ($q) => $q->where('amount', '>', 0))
            ->when($filter === 'keluar', fn ($q) => $q->where('amount', '<', 0))
            ->latest('id')->paginate(20)->withQueryString();

        $month = PointTransaction::where('user_id', $user->id)->where('created_at', '>=', now()->startOfMonth());

        return view('portal.points.index', [
            'user' => $user,
            'level' => PointService::level($user),
            'transactions' => $tx,
            'filter' => $filter,
            'stats' => [
                'monthIn' => (int) (clone $month)->where('amount', '>', 0)->whereIn('kind', ['earn', 'grant', 'adjust'])->sum('amount'),
                'monthOut' => (int) -(clone $month)->where('kind', 'redeem')->sum('amount'),
            ],
            'coupons' => Redemption::with('reward')->where('user_id', $user->id)->where('status', 'pending')->latest()->get(),
            'rules' => PointRule::where('is_active', true)->where('points', '>', 0)->orderBy('sort_order')->get(),
            'leaders' => self::leaderboard(),
            'myRank' => self::rankOf($user),
            'picks' => Reward::available()->get()->sortBy(fn ($r) => [$r->inStock() ? 0 : 1, abs($r->cost - $user->points_balance)])->take(4)->values(),
        ]);
    }

    public function rewards(Request $request): View
    {
        $user = $request->user();
        $rewards = Reward::available()->withCount(['redemptions as mine' => fn ($q) => $q->where('user_id', $user->id)->where('status', '!=', 'cancelled')])->get();

        return view('portal.points.rewards', [
            'user' => $user,
            'rewards' => $rewards,
            'categories' => collect(Reward::CATEGORIES)->only($rewards->pluck('category')->unique()),
        ]);
    }

    /** Tukar poin dengan hadiah — dikonfirmasi dengan PIN. */
    public function redeem(Request $request, Reward $reward, PointService $points): RedirectResponse
    {
        $user = $request->user();
        $request->validate(['pin' => ['required', 'digits:6']], ['pin.required' => 'Masukkan PIN Anda.', 'pin.digits' => 'PIN terdiri dari 6 angka.']);

        $key = 'redeem-pin:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withInput(['reward' => $reward->id])->withErrors(['pin' => 'Terlalu banyak percobaan. Coba lagi dalam '.ceil(RateLimiter::availableIn($key) / 60).' menit.']);
        }
        if (! Hash::check($request->input('pin'), $user->pin)) {
            RateLimiter::hit($key, 600);

            return back()->withInput(['reward' => $reward->id])->withErrors(['pin' => 'PIN salah.']);
        }
        RateLimiter::clear($key);

        try {
            $redemption = $points->redeem($user, $reward);
        } catch (PointException $e) {
            return back()->withInput(['reward' => $reward->id])->withErrors(['pin' => $e->getMessage()]);
        }

        return redirect()->route('points.coupon', $redemption)->with('fresh', true);
    }

    public function coupon(Request $request, Redemption $redemption): View
    {
        abort_unless($redemption->user_id === $request->user()->id || $request->user()->can('manage-points'), 404);

        return view('portal.points.coupon', [
            'redemption' => $redemption->load('reward', 'handler'),
            'fresh' => (bool) session('fresh'),
        ]);
    }

    /** 10 besar penerima poin bulan ini. */
    public static function leaderboard(int $limit = 10)
    {
        $rows = PointTransaction::whereIn('kind', ['earn', 'grant'])->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('user_id, SUM(amount) as total')->groupBy('user_id')->orderByDesc('total')->limit($limit)->get();
        $users = User::whereIn('id', $rows->pluck('user_id'))->get(['id', 'name', 'department', 'nrp'])->keyBy('id');

        return $rows->map(fn ($r) => ['user' => $users->get($r->user_id), 'total' => (int) $r->total])->filter(fn ($r) => $r['user'])->values();
    }

    public static function rankOf(User $user): ?int
    {
        $mine = (int) PointTransaction::where('user_id', $user->id)->whereIn('kind', ['earn', 'grant'])->where('created_at', '>=', now()->startOfMonth())->sum('amount');
        if ($mine <= 0) {
            return null;
        }
        $above = PointTransaction::whereIn('kind', ['earn', 'grant'])->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('user_id, SUM(amount) as total')->groupBy('user_id')->havingRaw('SUM(amount) > ?', [$mine])->get()->count();

        return $above + 1;
    }
}
