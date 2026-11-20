<?php

namespace App\Http\Controllers\Admin\Points;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PointController;
use App\Models\PointGrant;
use App\Models\PointTransaction;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Ringkasan Poin HEBAT untuk admin. */
class OverviewController extends Controller
{
    public function __invoke(): View
    {
        $since = today()->subDays(29);
        $daily = PointTransaction::where('created_at', '>=', $since)
            ->selectRaw("DATE(created_at) as d, SUM(CASE WHEN amount > 0 AND kind IN ('earn','grant','adjust') THEN amount ELSE 0 END) as given, SUM(CASE WHEN kind = 'redeem' THEN -amount ELSE 0 END) as spent")
            ->groupBy('d')->get()->keyBy('d');
        $series = collect(range(29, 0))->map(function ($i) use ($daily) {
            $d = today()->subDays($i);
            $row = $daily->get($d->toDateString());

            return ['date' => $d, 'given' => (int) ($row->given ?? 0), 'spent' => (int) ($row->spent ?? 0)];
        });
        $month = PointTransaction::where('created_at', '>=', now()->startOfMonth());

        return view('admin.points.overview', [
            'stats' => [
                'circulating' => (int) User::sum('points_balance'),
                'holders' => User::where('points_balance', '>', 0)->count(),
                'givenMonth' => (int) (clone $month)->where('amount', '>', 0)->whereIn('kind', ['earn', 'grant', 'adjust'])->sum('amount'),
                'autoMonth' => (int) (clone $month)->where('kind', 'earn')->sum('amount'),
                'grantMonth' => (int) (clone $month)->where('kind', 'grant')->sum('amount'),
                'spentMonth' => (int) -(clone $month)->where('kind', 'redeem')->sum('amount'),
                'pendingCoupons' => Redemption::where('status', 'pending')->count(),
                'pendingGrants' => PointGrant::where('status', 'pending')->count(),
                'lowStock' => Reward::where('is_active', true)->whereNotNull('stock')->where('stock', '<=', 3)->count(),
            ],
            'series' => $series,
            'leaders' => PointController::leaderboard(8),
            'popular' => Reward::withCount(['redemptions as taken' => fn ($q) => $q->where('status', '!=', 'cancelled')])->orderByDesc('taken')->limit(5)->get(),
            'recent' => PointTransaction::with('user')->latest('id')->limit(10)->get(),
            'grants' => PointGrant::with('creator')->where('status', 'pending')->latest('submitted_at')->get(),
        ]);
    }
}
