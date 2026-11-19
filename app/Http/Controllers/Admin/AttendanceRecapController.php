<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AttendanceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceRecapController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to, $query] = $this->query($request);
        $all = (clone $query)->with('session.type', 'location')->get();

        // Per hari
        $days = collect();
        for ($d = $from->copy(); $d->lte($to) && $days->count() < 62; $d->addDay()) {
            $key = $d->toDateString();
            $day = $all->filter(fn ($r) => $r->session->starts_at->toDateString() === $key);
            $days->push(['date' => $d->copy(), 'total' => $day->count(), 'late' => $day->where('is_late', true)->count(), 'risk' => $day->where('fatigue', 'risk')->count()]);
        }

        // Per seksi
        $sections = $all->groupBy(fn ($r) => $r->department ?: 'Tanpa seksi')->map(fn ($g, $name) => [
            'name' => $name,
            'total' => $g->count(),
            'people' => $g->pluck('nrp')->unique()->count(),
            'late' => $g->where('is_late', true)->count(),
            'risk' => $g->where('fatigue', 'risk')->count(),
            'coaching' => $g->where('fatigue', 'coaching')->count(),
        ])->sortByDesc('total')->values();

        $sessions = AttendanceSession::visibleTo($request->user())->whereBetween('starts_at', [$from, $to->copy()->endOfDay()])
            ->when($request->query('type'), fn ($q, $t) => $q->where('attendance_type_id', $t));

        return view('admin.attendance.recap', [
            'from' => $from, 'to' => $to,
            'types' => AttendanceType::orderBy('sort_order')->get(),
            'departments' => AttendanceRecord::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'stats' => [
                'sessions' => $sessions->count(),
                'total' => $all->count(),
                'people' => $all->pluck('nrp')->unique()->count(),
                'late' => $all->where('is_late', true)->count(),
                'fit' => $all->where('fatigue', 'fit')->count(),
                'coaching' => $all->where('fatigue', 'coaching')->count(),
                'risk' => $all->where('fatigue', 'risk')->count(),
                'speaker' => $all->where('role', 'pemateri')->count(),
            ],
            'days' => $days,
            'sections' => $sections,
            'records' => (clone $query)->with('session.type', 'location')->orderByDesc('checked_in_at')->paginate(30)->withQueryString(),
            'speakers' => $all->where('role', 'pemateri')->groupBy('nrp')->map(fn ($g) => ['name' => $g->first()->name, 'total' => $g->count()])->sortByDesc('total')->take(5)->values(),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        [$from, $to, $query] = $this->query($request);
        $path = AttendanceExport::write($query->with('session.type', 'location')->orderBy('checked_in_at')->get(), 'Rekap');

        return response()->download($path, 'rekap-absensi-'.$from->format('Ymd').'-'.$to->format('Ymd').'.xlsx')->deleteFileAfterSend();
    }

    /** @return array{0:Carbon,1:Carbon,2:Builder} */
    private function query(Request $request): array
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        $user = $request->user();

        $query = AttendanceRecord::query()
            ->whereHas('session', fn ($s) => $s->visibleTo($user)
                ->whereBetween('starts_at', [$from, $to])
                ->when($request->query('type'), fn ($q, $t) => $q->where('attendance_type_id', $t)))
            ->when($request->query('department'), fn ($q, $d) => $q->where('department', $d))
            ->when($request->query('fatigue'), fn ($q, $f) => $q->where('fatigue', $f))
            ->when($request->query('late') === '1', fn ($q) => $q->where('is_late', true))
            ->when($request->query('geo') === 'unverified', fn ($q) => $q->where('geo_status', 'unverified'))
            ->when($request->query('area'), fn ($q, $a) => $q->where('attendance_location_id', $a))
            ->when($request->query('role') === 'pemateri', fn ($q) => $q->where('role', 'pemateri'))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('nrp', 'like', '%'.strtoupper($s).'%')->orWhere('name', 'like', '%'.strtoupper($s).'%')));

        return [$from, $to, $query];
    }
}
