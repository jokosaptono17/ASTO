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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExamController extends Controller
{
    public function __construct(private ExamService $exams) {}

    public function index(Request $request): View
    {
        $this->exams->closeOverdue();
        $exams = Exam::withCount(['participants', 'attempts as done_count' => fn ($q) => $q->where('status', 'submitted'),
            'attempts as live_count' => fn ($q) => $q->where('status', 'in_progress')])
            ->withAvg(['attempts as avg_score' => fn ($q) => $q->where('status', 'submitted')], 'score')
            ->latest('id')->get();
        $filter = $request->query('status');

        return view('admin.exams.index', [
            'exams' => $filter ? $exams->filter(fn ($e) => $e->state() === $filter)->values() : $exams,
            'counts' => $exams->countBy(fn ($e) => $e->state()),
            'filter' => $filter,
            'bank' => ['sets' => QuestionSet::count(), 'questions' => \App\Models\Question::where('is_active', true)->count()],
        ]);
    }

    public function create(Request $request): View
    {
        $exam = new Exam([
            'duration_minutes' => 60, 'pass_score' => 70, 'max_attempts' => 1, 'max_violations' => 3, 'audience' => 'assigned',
            'result_mode' => 'hidden', 'shuffle_questions' => true, 'shuffle_options' => true,
            'opens_at' => now()->addDay()->setTime(8, 0), 'closes_at' => now()->addDay()->setTime(12, 0),
        ]);
        if ($from = $request->query('salin') ? Exam::with('sets', 'participants.user')->find($request->query('salin')) : null) {
            $exam->fill($from->only(['description', 'audience', 'duration_minutes', 'shuffle_questions', 'shuffle_options', 'max_attempts', 'pass_score', 'result_mode', 'max_violations']));
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

    public function destroy(Exam $exam): RedirectResponse
    {
        if ($exam->attempts()->exists() && ! request()->boolean('force')) {
            return back()->with('toast', 'Ujian sudah dikerjakan peserta. Gunakan "Hapus beserta hasil" bila yakin.');
        }
        ActivityLog::record('exam.delete', "Menghapus ujian {$exam->title} ({$exam->code})");
        $exam->delete();

        return redirect()->route('admin.exams.index')->with('toast', 'Ujian dihapus.');
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
            'locked' => $exam->exists && $exam->attempts()->exists(),
        ]);
    }

    private function save(Request $request, Exam $exam): void
    {
        $request->merge(['code' => Exam::normalizeCode($request->input('code'))]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
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
            'code.unique' => 'Kode ini sudah dipakai ujian lain.',
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
