<?php

namespace App\Http\Controllers\Admin\Exams;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\QuestionSet;
use App\Models\User;
use App\Services\Exams\ExamService;
use App\Services\Spreadsheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExamController extends Controller
{
    public function __construct(private ExamService $exams) {}

    /** Pilihan periode dashboard. */
    private const PERIODS = ['30' => '30 hari', '90' => '90 hari', 'tahun' => 'Tahun ini', 'semua' => 'Semua waktu'];

    /** Pemeriksaan "Perlu perhatian" yang bisa dipakai sebagai saringan daftar. */
    private const CHECKS = [
        'tanpa-soal' => 'Terbit tetapi belum ada paket soal',
        'tanpa-peserta' => 'Terbit tetapi belum ada peserta ditunjuk',
        'draf-lama' => 'Draf tanpa hasil, tidak diubah 14 hari',
        'siap-arsip' => 'Selesai lebih dari 30 hari, belum diarsipkan',
    ];

    public function index(Request $request): View
    {
        $this->exams->closeOverdue();
        $this->exams->purgeTrash();

        $view = (string) $request->query('status', '');
        $view = in_array($view, ['', 'open', 'scheduled', 'draft', 'closed', 'arsip', 'sampah'], true) ? $view : '';
        $filters = $this->filters($request);

        $list = $this->listQuery($view, $filters);
        $per = in_array((int) $request->query('per'), [20, 50, 100], true) ? (int) $request->query('per') : 20;
        $exams = $list->paginate($per)->withQueryString();

        $tab = fn (callable $scope) => $scope($this->filtered(Exam::query(), $filters))->count();
        $counts = [
            '' => $tab(fn ($q) => $q->active()),
            'open' => $tab(fn ($q) => $q->active()->inState('open')),
            'scheduled' => $tab(fn ($q) => $q->active()->inState('scheduled')),
            'draft' => $tab(fn ($q) => $q->active()->inState('draft')),
            'closed' => $tab(fn ($q) => $q->active()->inState('closed')),
            'arsip' => $tab(fn ($q) => $q->archived()),
            'sampah' => $tab(fn ($q) => $q->onlyTrashed()),
        ];
        $total = Exam::withTrashed()->count();
        $period = array_key_exists((string) $request->query('periode'), self::PERIODS) ? (string) $request->query('periode') : 'tahun';

        return view('admin.exams.index', [
            'exams' => $exams,
            'view' => $view,
            'filters' => $filters,
            'counts' => $counts,
            'per' => $per,
            'isEmpty' => $total === 0,
            'dash' => $total ? $this->dashboard($period) : null,
            'period' => $period,
            'periods' => self::PERIODS,
            'checks' => self::CHECKS,
            'categories' => Exam::withTrashed()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'uncategorized' => Exam::whereNull('category')->exists(),
            'years' => $this->years(),
            'bank' => ['sets' => QuestionSet::count(), 'questions' => \App\Models\Question::where('is_active', true)->count()],
        ]);
    }

    /** Rekap daftar event (mengikuti saringan yang sedang aktif) ke Excel. */
    public function exportList(Request $request): BinaryFileResponse
    {
        $view = (string) $request->query('status', '');
        $rows = [['Judul', 'Kode', 'Kategori', 'Status', 'Arsip', 'Buka', 'Tutup', 'Durasi (menit)', 'Jumlah soal', 'Peserta ditunjuk', 'Selesai', 'Diskualifikasi', 'Rata-rata', 'Lulus', 'Tingkat lulus (%)', 'Dibuat']];
        $this->listQuery($view, $this->filters($request))->with('sets')->chunk(200, function ($chunk) use (&$rows) {
            foreach ($chunk as $e) {
                $rows[] = [
                    $e->title, $e->code, $e->category, $e->trashed() ? 'Tempat sampah' : $e->stateInfo()['label'], $e->isArchived() ? 'Ya' : '',
                    $e->opens_at?->format('d/m/Y H:i'), $e->closes_at?->format('d/m/Y H:i'), $e->duration_minutes, $e->questionCount(),
                    $e->participants_count, $e->done_count, $e->dq_count,
                    $e->avg_score !== null ? number_format($e->avg_score, 2, ',', '') : '', $e->pass_count,
                    $e->done_count ? round($e->pass_count / $e->done_count * 100) : '', $e->created_at->format('d/m/Y'),
                ];
            }
        });

        $path = storage_path('app/rekap-ujian-'.Str::random(6).'.xlsx');
        Spreadsheet::writeXlsx($path, $rows, 'Event ujian', [40, 14, 18, 14, 8, 16, 16, 10, 10, 12, 10, 12, 10, 8, 12, 12]);

        return response()->download($path, 'Rekap event ujian '.now()->format('Ymd-Hi').'.xlsx')->deleteFileAfterSend();
    }

    /**
     * Aksi untuk satu atau banyak event sekaligus: arsipkan, keluarkan dari arsip,
     * pindah ke Tempat sampah, pulihkan, atau hapus permanen.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['archive', 'unarchive', 'trash', 'restore', 'purge', 'empty-trash'])],
            'ids' => ['array', 'max:200', Rule::requiredIf(fn () => $request->input('action') !== 'empty-trash')],
            'ids.*' => ['integer'],
            'confirm' => ['nullable', 'string'],
        ]);
        $action = $data['action'];
        if (in_array($action, ['purge', 'empty-trash'], true) && Str::upper(trim((string) ($data['confirm'] ?? ''))) !== 'HAPUS') {
            return back()->with('toast', 'Ketik HAPUS untuk mengonfirmasi penghapusan permanen.');
        }

        if ($action === 'trash') {
            $this->exams->closeOverdue();
        }
        $ids = array_map('intval', $data['ids'] ?? []);
        $query = match ($action) {
            'restore', 'purge' => Exam::onlyTrashed()->whereKey($ids),
            'empty-trash' => Exam::onlyTrashed(),
            default => Exam::whereKey($ids),
        };
        $done = 0;
        $skipped = [];
        $titles = [];
        foreach ($query->withCount(['attempts as live_count' => fn ($q) => $q->where('status', 'in_progress')])->get() as $exam) {
            $why = match ($action) {
                'archive' => $exam->isArchived() ? 'sudah di arsip' : ($exam->canArchive() ? null : 'masih '.Str::lower($exam->stateInfo()['label'])),
                'unarchive' => $exam->isArchived() ? null : 'tidak di arsip',
                'trash' => $exam->live_count ? 'ada peserta sedang mengerjakan' : null,
                default => null,
            };
            if ($why) {
                $skipped[] = "{$exam->title} ({$why})";

                continue;
            }
            match ($action) {
                'archive' => $exam->forceFill(['archived_at' => now()])->save(),
                'unarchive' => $exam->forceFill(['archived_at' => null])->save(),
                'trash' => $this->moveToTrash($exam),
                'restore' => $this->restoreFromTrash($exam),
                'purge', 'empty-trash' => $exam->forceDelete(),
            };
            $titles[] = $exam->title;
            $done++;
        }

        $verb = ['archive' => 'diarsipkan', 'unarchive' => 'dikeluarkan dari arsip', 'trash' => 'dipindah ke Tempat sampah',
            'restore' => 'dipulihkan', 'purge' => 'dihapus permanen', 'empty-trash' => 'dihapus permanen'][$action];
        if ($done) {
            ActivityLog::record('exam.'.$action, "{$done} event ujian {$verb}: ".implode(', ', array_slice($titles, 0, 5)).($done > 5 ? ', …' : ''));
        }
        $msg = $done ? "{$done} event {$verb}." : 'Tidak ada event yang diproses.';
        if ($action === 'trash' && $done) {
            $msg .= ' Bisa dipulihkan dari Tempat sampah selama '.Exam::TRASH_DAYS.' hari.';
        }
        if ($skipped) {
            $msg .= ' Dilewati: '.implode('; ', array_slice($skipped, 0, 3)).(count($skipped) > 3 ? ' dan '.(count($skipped) - 3).' lainnya' : '').'.';
        }

        return back()->with('toast', $msg);
    }

    /** Saringan daftar dari query string. */
    private function filters(Request $request): array
    {
        $sort = (string) $request->query('urut', 'baru');

        return [
            'q' => mb_substr(trim((string) $request->query('q')), 0, 80),
            'category' => (string) $request->query('kategori', ''),
            'year' => preg_match('/^\d{4}$/', (string) $request->query('tahun')) ? (int) $request->query('tahun') : null,
            'sort' => in_array($sort, ['baru', 'jadwal', 'judul', 'peserta', 'nilai'], true) ? $sort : 'baru',
            'check' => array_key_exists((string) $request->query('cek'), self::CHECKS) ? (string) $request->query('cek') : null,
        ];
    }

    private function filtered($q, array $f)
    {
        if ($f['q'] !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $f['q']).'%';
            $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('code', 'like', '%'.Exam::normalizeCode($f['q']).'%')->orWhere('category', 'like', $term));
        }
        if ($f['category'] === '-') {
            $q->whereNull('category');
        } elseif ($f['category'] !== '') {
            $q->where('category', $f['category']);
        }
        if ($f['year']) {
            $from = Carbon::create($f['year'])->startOfYear();
            $to = $from->copy()->endOfYear();
            $q->where(fn ($w) => $w->whereBetween('opens_at', [$from, $to])->orWhere(fn ($n) => $n->whereNull('opens_at')->whereBetween('created_at', [$from, $to])));
        }
        match ($f['check']) {
            'tanpa-soal' => $q->where('is_published', true)->where(fn ($w) => $w->whereNull('closes_at')->orWhere('closes_at', '>', now()))->doesntHave('sets'),
            'tanpa-peserta' => $q->where('is_published', true)->where('audience', 'assigned')->where(fn ($w) => $w->whereNull('closes_at')->orWhere('closes_at', '>', now()))->doesntHave('participants'),
            'draf-lama' => $q->where('is_published', false)->where('updated_at', '<', now()->subDays(14))->doesntHave('attempts'),
            'siap-arsip' => $q->whereNull('archived_at')->inState('closed')->where('closes_at', '<', now()->subDays(30)),
            default => null,
        };

        return $q;
    }

    private function listQuery(string $view, array $filters)
    {
        $q = match ($view) {
            'sampah' => Exam::onlyTrashed()->with('deleter:id,name'),
            'arsip' => Exam::archived(),
            '' => Exam::active(),
            default => Exam::active()->inState($view),
        };
        $this->filtered($q, $filters)->withCount([
            'participants', 'attempts',
            'attempts as done_count' => fn ($a) => $a->where('status', 'submitted'),
            'attempts as live_count' => fn ($a) => $a->where('status', 'in_progress'),
            'attempts as dq_count' => fn ($a) => $a->where('status', 'disqualified'),
            'attempts as pass_count' => fn ($a) => $a->where('status', 'submitted')->whereColumn('exam_attempts.score', '>=', 'exams.pass_score'),
        ])->withAvg(['attempts as avg_score' => fn ($a) => $a->where('status', 'submitted')], 'score');

        return match ($filters['sort']) {
            'jadwal' => $q->orderByRaw('opens_at is null')->orderByDesc('opens_at')->orderByDesc('id'),
            'judul' => $q->orderBy('title'),
            'peserta' => $q->orderByDesc('done_count')->orderByDesc('id'),
            'nilai' => $q->orderByDesc('avg_score')->orderByDesc('id'),
            default => $view === 'sampah' ? $q->latest('deleted_at') : $q->latest('id'),
        };
    }

    /** Tahun yang punya event (untuk saringan). */
    private function years(): Collection
    {
        $first = collect([Exam::withTrashed()->min('opens_at'), Exam::withTrashed()->min('created_at')])->filter()->map(fn ($d) => Carbon::parse($d)->year)->min();

        return $first ? collect(range((int) now()->year + 1, (int) $first)) : collect();
    }

    /** Angka ringkasan + grafik + daftar perhatian untuk bagian atas halaman. */
    private function dashboard(string $period): array
    {
        $from = match ($period) {
            '30' => now()->subDays(30), '90' => now()->subDays(90), 'tahun' => now()->startOfYear(), default => null,
        };
        $inPeriod = fn ($q) => $from ? $q->where(fn ($w) => $w->where('opens_at', '>=', $from)->orWhere(fn ($n) => $n->whereNull('opens_at')->where('created_at', '>=', $from))) : $q;

        $events = $inPeriod(Exam::query())->get(['id', 'is_published', 'opens_at', 'closes_at'])->countBy(fn ($e) => $e->state());
        $sheets = ExamAttempt::query()->join('exams', 'exams.id', '=', 'exam_attempts.exam_id')->whereNull('exams.deleted_at')
            ->where('exam_attempts.status', 'submitted')->when($from, fn ($q) => $q->where('exam_attempts.submitted_at', '>=', $from))
            ->selectRaw('count(*) as n, avg(exam_attempts.score) as avg, count(distinct exam_attempts.user_id) as people, sum(case when exam_attempts.score >= exams.pass_score then 1 else 0 end) as pass')
            ->first();
        $dq = ExamAttempt::whereHas('exam')->where('status', 'disqualified')->when($from, fn ($q) => $q->where('submitted_at', '>=', $from))->count();

        // Kehadiran: peserta ditunjuk pada event yang sudah selesai, berapa yang benar-benar mengerjakan
        $closedIds = $inPeriod(Exam::inState('closed')->where('audience', 'assigned'))->pluck('id');
        $assigned = $closedIds->isEmpty() ? 0 : \App\Models\ExamParticipant::whereIn('exam_id', $closedIds)->count();
        $attended = $closedIds->isEmpty() ? 0 : \App\Models\ExamParticipant::whereIn('exam_id', $closedIds)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('exam_attempts')->whereColumn('exam_attempts.exam_id', 'exam_participants.exam_id')->whereColumn('exam_attempts.user_id', 'exam_participants.user_id'))->count();

        // Grafik 12 bulan: lembar selesai + rata-rata per bulan (dikelompokkan di database)
        $month = DB::getDriverName() === 'sqlite' ? "strftime('%%Y-%%m', %s)" : "DATE_FORMAT(%s, '%%Y-%%m')";
        $start = now()->startOfMonth()->subMonths(11);
        $perMonth = ExamAttempt::whereHas('exam')->where('status', 'submitted')->where('submitted_at', '>=', $start)
            ->selectRaw(sprintf($month, 'submitted_at').' as ym, count(*) as n, avg(score) as avg')->groupBy('ym')->get()->keyBy('ym');
        $eventsPerMonth = Exam::where('is_published', true)->whereRaw('coalesce(opens_at, created_at) >= ?', [$start])
            ->selectRaw(sprintf($month, 'coalesce(opens_at, created_at)').' as ym, count(*) as n')->groupBy('ym')->pluck('n', 'ym');
        $series = collect(range(0, 11))->map(function ($i) use ($start, $perMonth, $eventsPerMonth) {
            $m = $start->copy()->addMonths($i);
            $row = $perMonth->get($m->format('Y-m'));

            return ['label' => $m->locale('id')->translatedFormat('M'), 'full' => $m->locale('id')->translatedFormat('F Y'), 'year' => $m->year,
                'sheets' => (int) ($row->n ?? 0), 'avg' => $row ? round((float) $row->avg, 1) : null, 'events' => (int) ($eventsPerMonth[$m->format('Y-m')] ?? 0),
                'current' => $m->isSameMonth(now())];
        });

        // Per kategori (dalam periode)
        $byCategory = $inPeriod(Exam::query())->withCount(['attempts as done_count' => fn ($a) => $a->where('status', 'submitted'),
            'attempts as pass_count' => fn ($a) => $a->where('status', 'submitted')->whereColumn('exam_attempts.score', '>=', 'exams.pass_score')])
            ->withAvg(['attempts as avg_score' => fn ($a) => $a->where('status', 'submitted')], 'score')
            ->get(['id', 'category'])->groupBy(fn ($e) => $e->category ?: 'Tanpa kategori')
            ->map(fn ($g, $name) => ['name' => $name, 'events' => $g->count(), 'sheets' => $g->sum('done_count'), 'pass' => $g->sum('pass_count'),
                'avg' => $g->sum('done_count') ? round($g->sum(fn ($e) => (float) $e->avg_score * $e->done_count) / $g->sum('done_count'), 1) : null])
            ->sortByDesc('sheets')->values();

        $attention = collect(self::CHECKS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label,
            'count' => $this->filtered(Exam::active(), ['q' => '', 'category' => '', 'year' => null, 'check' => $key])->count(),
            'tone' => in_array($key, ['tanpa-soal', 'tanpa-peserta'], true) ? 'alarm' : 'calm'])->filter(fn ($a) => $a['count'] > 0)->values();
        $trash = Exam::onlyTrashed()->count();

        return [
            'events' => ['total' => $events->sum(), 'open' => $events['open'] ?? 0, 'scheduled' => $events['scheduled'] ?? 0, 'closed' => $events['closed'] ?? 0, 'draft' => $events['draft'] ?? 0],
            'sheets' => (int) $sheets->n, 'people' => (int) $sheets->people, 'dq' => $dq,
            'avg' => $sheets->n ? round((float) $sheets->avg, 1) : null,
            'passRate' => $sheets->n ? (int) round($sheets->pass / $sheets->n * 100) : null, 'pass' => (int) $sheets->pass,
            'attendance' => $assigned ? (int) round($attended / $assigned * 100) : null, 'absent' => $assigned - $attended, 'assigned' => $assigned,
            'series' => $series, 'seriesMax' => max(1, $series->max('sheets')),
            'live' => Exam::inState('open')->withCount(['participants', 'attempts as live_count' => fn ($a) => $a->where('status', 'in_progress'), 'attempts as done_count' => fn ($a) => $a->where('status', 'submitted')])->orderBy('closes_at')->limit(6)->get(),
            'next' => Exam::active()->inState('scheduled')->withCount('participants')->orderBy('opens_at')->limit(4)->get(),
            'categories' => $byCategory,
            'attention' => $attention,
            'trash' => $trash,
            'trashSoon' => $trash ? Exam::onlyTrashed()->min('deleted_at') : null,
        ];
    }

    private function restoreFromTrash(Exam $exam): void
    {
        $exam->restore();
        $exam->forceFill(['deleted_by' => null])->save();
    }

    private function moveToTrash(Exam $exam): void
    {
        $exam->forceFill(['deleted_by' => auth()->id()])->save();
        $exam->delete();
    }

    public function create(Request $request): View
    {
        $exam = new Exam([
            'duration_minutes' => 60, 'pass_score' => 70, 'max_attempts' => 1, 'max_violations' => 3, 'audience' => 'assigned',
            'result_mode' => 'hidden', 'shuffle_questions' => true, 'shuffle_options' => true,
            'opens_at' => now()->addDay()->setTime(8, 0), 'closes_at' => now()->addDay()->setTime(12, 0),
        ]);
        if ($from = $request->query('salin') ? Exam::with('sets', 'participants.user')->find($request->query('salin')) : null) {
            $exam->fill($from->only(['category', 'description', 'audience', 'duration_minutes', 'shuffle_questions', 'shuffle_options', 'max_attempts', 'pass_score', 'result_mode', 'max_violations']));
            $exam->title = $from->title.' (salinan)';
        }

        return $this->form($exam, $from);
    }

    public function store(Request $request): RedirectResponse
    {
        $exam = new Exam(['created_by' => $request->user()->id]);
        $this->save($request, $exam);
        ActivityLog::record('exam.create', "Membuat ujian {$exam->title} ({$exam->code})");

        return redirect()->route('admin.exams.show', $exam)->with('toast', $exam->is_published ? 'Ujian diterbitkan. Peserta yang ditunjuk melihat notifikasi saat membuka portal.' : 'Ujian disimpan sebagai draf.');
    }

    public function edit(Exam $exam): View
    {
        return $this->form($exam);
    }

    public function update(Request $request, Exam $exam): RedirectResponse
    {
        $this->save($request, $exam);
        ActivityLog::record('exam.update', "Mengubah ujian {$exam->title}");

        return redirect()->route('admin.exams.show', $exam)->with('toast', 'Perubahan ujian disimpan.');
    }

    /** Pindahkan ke Tempat sampah (bisa dipulihkan selama 30 hari). */
    public function destroy(Exam $exam): RedirectResponse
    {
        $this->exams->closeOverdue($exam);
        if ($why = $exam->trashBlocker()) {
            return back()->with('toast', $why);
        }
        $this->moveToTrash($exam);
        ActivityLog::record('exam.trash', "Memindah ujian {$exam->title} ({$exam->code}) ke Tempat sampah");

        return redirect()->route('admin.exams.index')->with('toast', "\"{$exam->title}\" dipindah ke Tempat sampah. Bisa dipulihkan selama ".Exam::TRASH_DAYS.' hari.');
    }

    /** Arsipkan / keluarkan dari arsip. */
    public function archive(Exam $exam): RedirectResponse
    {
        if (! $exam->isArchived() && ! $exam->canArchive()) {
            return back()->with('toast', 'Hanya draf atau ujian yang sudah selesai yang bisa diarsipkan.');
        }
        $exam->forceFill(['archived_at' => $exam->isArchived() ? null : now()])->save();
        ActivityLog::record($exam->isArchived() ? 'exam.archive' : 'exam.unarchive', ($exam->isArchived() ? 'Mengarsipkan' : 'Mengeluarkan dari arsip').": {$exam->title}");

        return back()->with('toast', $exam->isArchived() ? 'Ujian diarsipkan. Hasil tetap tersimpan dan bisa dibuka dari tab Arsip.' : 'Ujian dikeluarkan dari arsip.');
    }

    /** Terbitkan / tarik kembali tanpa membuka formulir. */
    public function publish(Exam $exam): RedirectResponse
    {
        $exam->update(['is_published' => ! $exam->is_published]);

        return back()->with('toast', $exam->is_published ? 'Ujian diterbitkan.' : 'Ujian ditarik menjadi draf.');
    }

    /** Buka / tutup sekarang (mengubah jadwal). */
    public function toggleOpen(Exam $exam): RedirectResponse
    {
        if ($exam->isOpen()) {
            $exam->update(['closes_at' => now()]);
            $this->exams->closeOverdue($exam);
            $msg = 'Ujian ditutup. Peserta yang sedang mengerjakan tetap bisa menyelesaikan sampai sisa waktunya habis.';
        } else {
            $exam->update(['is_published' => true, 'opens_at' => now(), 'closes_at' => $exam->closes_at && $exam->closes_at->isFuture() ? $exam->closes_at : now()->addMinutes($exam->duration_minutes + 30)]);
            $msg = 'Ujian dibuka sekarang.';
        }

        return back()->with('toast', $msg);
    }

    public function show(Exam $exam): View
    {
        $this->exams->closeOverdue($exam);
        $exam->load('sets');

        return view('admin.exams.show', [
            'exam' => $exam,
            'feed' => $this->feedData($exam),
            'ranking' => $this->exams->ranking($exam),
            'items' => $this->exams->itemAnalysis($exam),
            'count' => $exam->questionCount(),
        ]);
    }

    public function feed(Exam $exam): JsonResponse
    {
        $this->exams->closeOverdue($exam);

        return response()->json($this->feedData($exam));
    }

    /** Layar proyektor untuk ruang ujian: kode besar + penghitung peserta. */
    public function screen(Exam $exam): View
    {
        return view('admin.exams.screen', ['exam' => $exam, 'feed' => $this->feedData($exam)]);
    }

    public function regrade(Exam $exam): RedirectResponse
    {
        $n = $this->exams->regrade($exam);
        ActivityLog::record('exam.regrade', "Menghitung ulang nilai {$exam->title}");

        return back()->with('toast', "Nilai {$n} lembar jawaban dihitung ulang dengan kunci terbaru.");
    }

    public function attempt(Exam $exam, ExamAttempt $attempt): View
    {
        abort_unless($attempt->exam_id === $exam->id, 404);

        return view('admin.exams.attempt', [
            'exam' => $exam,
            'attempt' => $attempt->load('user'),
            'answers' => $attempt->answers()->with('question.set')->get(),
        ]);
    }

    public function attemptAction(Request $request, Exam $exam, ExamAttempt $attempt): RedirectResponse|JsonResponse
    {
        abort_unless($attempt->exam_id === $exam->id, 404);
        $action = $request->validate(['action' => ['required', Rule::in(['extend', 'finish', 'disqualify', 'reset', 'restore'])]])['action'];
        $who = $attempt->user->displayName();
        $msg = match ($action) {
            'extend' => tap('Waktu '.$who.' ditambah '.($m = (int) $request->input('minutes', 10)).' menit.', fn () => $this->exams->extend($attempt, max(1, min(120, $m)))),
            'finish' => tap("Lembar {$who} dikumpulkan.", fn () => $this->exams->finish($attempt, 'admin')),
            'disqualify' => tap("{$who} didiskualifikasi.", fn () => $this->exams->disqualify($attempt)),
            'restore' => tap("Diskualifikasi {$who} dibatalkan.", fn () => $attempt->status === 'disqualified' && $attempt->update(['status' => 'submitted'])),
            'reset' => tap("Percobaan {$who} dihapus; peserta bisa mengerjakan ulang.", fn () => $attempt->delete()),
        };
        ActivityLog::record('exam.'.$action, "{$exam->title}: {$msg}");

        return $request->expectsJson() ? response()->json(['ok' => true, 'message' => $msg]) : back()->with('toast', $msg);
    }

    public function people(Request $request): JsonResponse
    {
        $q = User::where('is_active', true);
        if ($nrps = $request->query('nrps')) {
            $q->whereIn('nrp', array_slice(array_map(fn ($n) => User::normalizeNrp($n), explode(',', $nrps)), 0, 500));
        } elseif ($dept = $request->query('department')) {
            $q->where('department', $dept)->limit(500);
        } elseif ($position = $request->query('position')) {
            $q->where('position', $position)->limit(500);
        } else {
            $term = trim((string) $request->query('q'));
            if (mb_strlen($term) < 2) {
                return response()->json([]);
            }
            $q->where(fn ($w) => $w->where('nrp', 'like', '%'.Str::upper($term).'%')->orWhere('name', 'like', '%'.Str::upper($term).'%'))->limit(12);
        }

        return response()->json($q->orderBy('name')->get(['id', 'nrp', 'name', 'department', 'position'])
            ->map(fn ($u) => ['id' => $u->id, 'nrp' => $u->nrp, 'name' => $u->displayName(), 'department' => $u->department, 'position' => $u->position]));
    }

    public function export(Exam $exam): BinaryFileResponse
    {
        $this->exams->closeOverdue($exam);
        $ranking = $this->exams->ranking($exam);
        $labels = $exam->participants()->pluck('label', 'user_id');
        $questions = $exam->sets()->get()->flatMap(fn ($s) => $s->questions()->get(['id', 'question_set_id']))->values();
        $answers = \App\Models\ExamAnswer::whereIn('exam_attempt_id', $ranking->pluck('id'))->get()->groupBy('exam_attempt_id');

        $rows = [array_merge(['Peringkat', 'NRP', 'Nama', 'Departemen', 'Jabatan', 'Kelompok', 'Benar', 'Jumlah soal', 'Nilai', 'Status', 'Lama mengerjakan', 'Pelanggaran', 'Mulai', 'Selesai', 'Keterangan'],
            $questions->map(fn ($q, $i) => 'S'.($i + 1).' #'.$q->id)->all())];
        foreach ($ranking as $a) {
            $mine = ($answers[$a->id] ?? collect())->keyBy('question_id');
            $rows[] = array_merge([
                $a->rank, $a->user->nrp, $a->user->name, $a->user->department, $a->user->position, $labels[$a->user_id] ?? '',
                $a->correct, $a->total, number_format($a->score, 2, ',', ''), $a->passed() ? 'LULUS' : 'TIDAK LULUS',
                ExamAttempt::formatDuration($a->durationSeconds()), $a->violations,
                $a->started_at->format('d/m/Y H:i:s'), $a->submitted_at?->format('d/m/Y H:i:s'), ExamAttempt::REASONS[$a->finish_reason] ?? '',
            ], $questions->map(fn ($q) => ! isset($mine[$q->id]) ? '' : ($mine[$q->id]->choice === null ? '-' : ($mine[$q->id]->is_correct ? 1 : 0)))->all());
        }
        foreach ($exam->attempts()->where('status', 'disqualified')->with('user')->get() as $a) {
            $rows[] = ['DQ', $a->user->nrp, $a->user->name, $a->user->department, $a->user->position, $labels[$a->user_id] ?? '', $a->correct, $a->total, number_format((float) $a->score, 2, ',', ''), 'DISKUALIFIKASI', ExamAttempt::formatDuration($a->durationSeconds()), $a->violations, $a->started_at->format('d/m/Y H:i:s'), $a->submitted_at?->format('d/m/Y H:i:s'), ''];
        }

        $path = storage_path('app/ujian-'.$exam->id.'-'.Str::random(6).'.xlsx');
        Spreadsheet::writeXlsx($path, $rows, 'Hasil', [10, 12, 30, 22, 18, 20, 8, 11, 8, 14, 16, 11, 18, 18, 24]);

        return response()->download($path, 'Hasil '.Str::slug($exam->title).' '.now()->format('Ymd-Hi').'.xlsx')->deleteFileAfterSend();
    }

    /** Data papan pantau: semua peserta ditunjuk + yang masuk lewat kode (ujian terbuka). */
    private function feedData(Exam $exam): array
    {
        $participants = $exam->participants()->with('user:id,nrp,name,department,position')->get()->keyBy('user_id');
        $attempts = $exam->attempts()->with('user:id,nrp,name,department,position')->orderBy('attempt_no')->get()->groupBy('user_id');
        $userIds = $participants->keys()->merge($attempts->keys())->unique();

        $rows = $userIds->map(function ($uid) use ($participants, $attempts) {
            $tries = $attempts->get($uid, collect());
            $a = $tries->firstWhere('status', 'in_progress') ?? $tries->sortByDesc(fn ($t) => [$t->status === 'submitted', $t->score])->first();
            $user = $participants->get($uid)?->user ?? $a?->user;
            if (! $user) {
                return null;
            }
            $status = $a?->status ?? 'waiting';
            $online = $a && $a->isActive() && $a->last_seen_at && $a->last_seen_at->gt(now()->subSeconds(75));

            return [
                'user_id' => $uid, 'name' => $user->displayName(), 'nrp' => $user->nrp, 'department' => $user->department,
                'label' => $participants->get($uid)?->label, 'assigned' => $participants->has($uid),
                'status' => $status, 'attempt' => $a?->id, 'tries' => $tries->count(),
                'answered' => $a?->answered ?? 0, 'total' => $a?->total ?? 0,
                'score' => $a && $a->score !== null ? round($a->score, 2) : null, 'correct' => $a?->correct,
                'left' => $a && $a->isActive() ? $a->secondsLeft() : null,
                'duration' => $a ? ExamAttempt::formatDuration($a->durationSeconds()) : null,
                'violations' => $a?->violations ?? 0, 'online' => $online,
                'seen' => $a?->last_seen_at?->locale('id')->diffForHumans(short: true),
                'reason' => $a?->finish_reason,
            ];
        })->filter()->sortBy([fn ($r) => ['in_progress' => 0, 'waiting' => 1, 'submitted' => 2, 'disqualified' => 3][$r['status']], fn ($r) => $r['name']])->values();

        $scores = $rows->where('status', 'submitted')->pluck('score')->filter(fn ($s) => $s !== null);

        return [
            'state' => $exam->state(),
            'rows' => $rows->all(),
            'stats' => [
                'participants' => $rows->count(),
                'waiting' => $rows->where('status', 'waiting')->count(),
                'live' => $rows->where('status', 'in_progress')->count(),
                'done' => $rows->where('status', 'submitted')->count(),
                'dq' => $rows->where('status', 'disqualified')->count(),
                'avg' => $scores->isNotEmpty() ? round($scores->avg(), 1) : null,
                'max' => $scores->max(),
                'min' => $scores->min(),
                'pass' => $scores->filter(fn ($s) => $s >= $exam->pass_score)->count(),
                'histogram' => collect(range(0, 9))->map(fn ($b) => $scores->filter(fn ($s) => min(9, intdiv((int) floor($s), 10)) === $b)->count())->all(),
            ],
            'at' => now()->format('H:i:s'),
        ];
    }

    private function form(Exam $exam, ?Exam $copyFrom = null): View
    {
        $source = $copyFrom ?? $exam;
        $chosen = $source->exists ? $source->sets()->get()->mapWithKeys(fn ($s) => [$s->id => $s->pivot->take]) : collect();
        $participants = $source->exists ? $source->participants()->with('user')->get()->filter->user->map(fn ($p) => [
            'id' => $p->user_id, 'nrp' => $p->user->nrp, 'name' => $p->user->displayName(), 'department' => $p->user->department, 'label' => (string) $p->label,
        ])->values() : collect();

        return view('admin.exams.form', [
            'exam' => $exam,
            'sets' => QuestionSet::withCount(['questions as active_count' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get()
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'count' => $s->active_count, 'on' => $chosen->has($s->id), 'take' => $chosen->get($s->id)])->values(),
            'participants' => $participants,
            'departments' => User::where('is_active', true)->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'positions' => User::where('is_active', true)->whereNotNull('position')->distinct()->orderBy('position')->pluck('position'),
            'suggested' => $exam->code ?: Exam::suggestCode((string) $exam->title),
            'categories' => collect(Exam::CATEGORY_SUGGESTIONS)->merge(Exam::withTrashed()->whereNotNull('category')->distinct()->pluck('category'))->unique()->sort()->values(),
            'locked' => $exam->exists && $exam->attempts()->exists(),
        ]);
    }

    private function save(Request $request, Exam $exam): void
    {
        $request->merge(['code' => Exam::normalizeCode($request->input('code'))]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'code' => ['required', 'string', 'min:3', 'max:30', Rule::unique('exams', 'code')->ignore($exam->id)],
            'audience' => ['required', Rule::in(['assigned', 'open'])],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after:opens_at'],
            'pass_score' => ['required', 'integer', 'min:0', 'max:100'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'max_violations' => ['required', 'integer', 'min:0', 'max:50'],
            'result_mode' => ['required', Rule::in(array_keys(Exam::RESULT_MODES))],
            'sets' => ['array'],
            'sets.*.id' => ['required', 'integer', 'exists:question_sets,id'],
            'sets.*.take' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'participants' => ['array', 'max:2000'],
            'participants.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'participants.*.label' => ['nullable', 'string', 'max:60'],
        ], [
            'code.unique' => 'Kode ini sudah dipakai ujian lain (termasuk yang ada di Arsip atau Tempat sampah).',
            'closes_at.after' => 'Waktu tutup harus setelah waktu buka.',
        ]);

        DB::transaction(function () use ($request, $exam, $data) {
            $exam->fill(collect($data)->except(['sets', 'participants'])->all() + [
                'is_published' => $request->input('publish') === '1',
                'shuffle_questions' => $request->boolean('shuffle_questions'),
                'shuffle_options' => $request->boolean('shuffle_options'),
            ])->save();

            $exam->sets()->sync(collect($data['sets'] ?? [])->mapWithKeys(fn ($s) => [$s['id'] => ['take' => $s['take'] ?? null]])->all());

            $wanted = collect($data['participants'] ?? [])->keyBy('user_id');
            $exam->participants()->whereNotIn('user_id', $wanted->keys())->delete();
            foreach ($wanted as $uid => $p) {
                $exam->participants()->updateOrCreate(['user_id' => $uid], ['label' => $p['label'] ?? null]);
            }
        });
    }
}
