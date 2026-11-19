<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\AppLink;
use App\Models\AppVisit;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceSession;
use App\Models\Campaign;
use App\Models\CampaignClick;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $since = now()->subDays(6)->startOfDay();
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->startOfDay());

        $visitsByDay = AppVisit::where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as n')->groupBy('d')->pluck('n', 'd');
        $clicksByDay = CampaignClick::where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as n')->groupBy('d')->pluck('n', 'd');
        $loginsByDay = User::where('last_login_at', '>=', $since)
            ->selectRaw('DATE(last_login_at) as d, COUNT(*) as n')->groupBy('d')->pluck('n', 'd');

        $series = $days->map(fn (Carbon $d) => [
            'label' => $d->locale('id')->translatedFormat('D'),
            'date' => $d->format('j M'),
            'visits' => (int) ($visitsByDay[$d->toDateString()] ?? 0),
            'clicks' => (int) ($clicksByDay[$d->toDateString()] ?? 0),
        ]);

        $topApps = AppVisit::where('created_at', '>=', now()->subDays(30))
            ->select('app_id', DB::raw('COUNT(*) as total'))->groupBy('app_id')->orderByDesc('total')->limit(6)
            ->with('app')->get()->filter(fn ($v) => $v->app);


        // Absensi hari ini
        AttendanceSchedule::ensureSessions();
        $todaySessions = AttendanceSession::with('type')->whereDate('date', today())
            ->withCount([
                'records',
                'records as risk_count' => fn ($q) => $q->where('fatigue', 'risk'),
                'records as unverified_count' => fn ($q) => $q->where('geo_status', 'unverified'),
            ])->orderBy('opens_at')->get()
            ->each(fn (AttendanceSession $s) => $s->setAttribute('expected_count', $s->target_departments ? User::where('is_active', true)->whereIn('department', $s->target_departments)->count() : null));
        $attendanceToday = AttendanceRecord::whereDate('checked_in_at', today());

        // Aktivasi akun per seksi (8 seksi terbesar)
        $byDept = User::where('is_active', true)->whereNotNull('department')
            ->selectRaw('department, COUNT(*) as total, SUM(CASE WHEN pin IS NOT NULL THEN 1 ELSE 0 END) as activated')
            ->groupBy('department')->orderByDesc('total')->limit(8)->get();

        return view('admin.dashboard', [
            'todaySessions' => $todaySessions,
            'attendance' => [
                'present' => (clone $attendanceToday)->count(),
                'risk' => (clone $attendanceToday)->where('fatigue', 'risk')->count(),
                'coaching' => (clone $attendanceToday)->where('fatigue', 'coaching')->count(),
                'unverified' => (clone $attendanceToday)->where('geo_status', 'unverified')->count(),
                'open' => $todaySessions->filter->isOpen()->count(),
            ],
            'byDept' => $byDept,
            'health' => AppLink::active()->where('health_check', true)->orderByRaw("CASE health_status WHEN 'down' THEN 0 WHEN 'unknown' THEN 1 ELSE 2 END")->orderBy('name')->get(),
            'stats' => [
                'users' => User::where('is_active', true)->count(),
                'activated' => User::where('is_active', true)->whereNotNull('pin')->count(),
                'active7' => User::where('last_login_at', '>=', $since)->count(),
                'visits7' => $series->sum('visits'),
                'clicks7' => $series->sum('clicks'),
                'apps' => AppLink::active()->count(),
                'appsDown' => AppLink::active()->where('health_check', true)->where('health_status', 'down')->count(),
                'campaignsLive' => Campaign::live()->count(),
                'announcementsLive' => Announcement::live()->count(),
            ],
            'series' => $series,
            'topApps' => $topApps,
            'endingSoon' => Campaign::live()->whereNotNull('ends_at')->where('ends_at', '<=', now()->addDays(7))->orderBy('ends_at')->get(),
            'scheduled' => Campaign::where('is_active', true)->where('starts_at', '>', now())->orderBy('starts_at')->limit(5)->get(),
            'activities' => ActivityLog::with('user')->latest('created_at')->limit(10)->get(),
            'downApps' => AppLink::active()->where('health_check', true)->where('health_status', 'down')->get(),
            'loginsToday' => (int) ($loginsByDay[now()->toDateString()] ?? 0),
        ]);
    }
}
