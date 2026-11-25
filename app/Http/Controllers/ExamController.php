<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Services\Exams\ExamException;
use App\Services\Exams\ExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ujian Event untuk peserta: masuk dengan kode, kerjakan, lihat hasil. */
class ExamController extends Controller
{
    public function __construct(private ExamService $exams) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->exams->closeOverdue();

        return view('portal.exams.index', [
            'pending' => $this->exams->pendingFor($user),
            'history' => ExamAttempt::where('user_id', $user->id)->where('status', '!=', 'in_progress')->whereHas('exam')
                ->with('exam')->latest('submitted_at')->limit(30)->get(),
        ]);
    }

    /** Lobi ujian: aturan + kolom kode. */
    public function show(Request $request, Exam $exam): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($exam->is_published && ($exam->audience === 'open' || $exam->isAssigned($user) || $user->can('manage-exams')), 404);
        $this->exams->closeOverdue($exam);

        return view('portal.exams.lobby', [
            'exam' => $exam,
            'active' => $this->exams->activeAttempt($exam, $user),
            'blocked' => $this->exams->cannotStart($exam, $user),
            'tries' => $exam->attempts()->where('user_id', $user->id)->where('status', '!=', 'in_progress')->latest('id')->get(),
            'count' => $exam->questionCount(),
            'label' => $exam->participants()->where('user_id', $user->id)->value('label'),
        ]);
    }

    /** Masuk dari lobi: kode wajib cocok dengan event ini. */
    public function start(Request $request, Exam $exam): RedirectResponse
    {
        $user = $request->user();
        if ($active = $this->exams->activeAttempt($exam, $user)) {
            return redirect()->route('exams.room', $active);
        }
        $request->validate(['code' => ['required', 'string', 'max:40']], ['code.required' => 'Masukkan kode ujian dari pengawas.']);
        if (! hash_equals($exam->code, Exam::normalizeCode($request->input('code')))) {
            return back()->withErrors(['code' => 'Kode ujian salah. Periksa lagi kode yang diumumkan pengawas.'])->withInput();
        }

        return $this->begin($request, $exam);
    }

    /** Masuk hanya dengan kode (dari halaman Ujian saya). */
    public function enterCode(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:40']], ['code.required' => 'Masukkan kode ujian.']);
        $exam = Exam::where('code', Exam::normalizeCode($request->input('code')))->where('is_published', true)->first();
        if (! $exam) {
            return back()->withErrors(['code' => 'Kode ujian tidak ditemukan.'])->withInput();
        }
        if ($active = $this->exams->activeAttempt($exam, $request->user())) {
            return redirect()->route('exams.room', $active);
        }

        return $this->begin($request, $exam);
    }

    private function begin(Request $request, Exam $exam): RedirectResponse
    {
        try {
            $attempt = $this->exams->start($exam, $request->user(), $request->ip(), $request->userAgent());
        } catch (ExamException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        return redirect()->route('exams.room', $attempt);
    }

    /** Ruang ujian. */
    public function room(Request $request, ExamAttempt $attempt): View|RedirectResponse
    {
        $this->own($request, $attempt);
        if ($attempt->isActive() && $attempt->deadline_at->copy()->addSeconds(ExamService::GRACE_SECONDS)->isPast()) {
            $this->exams->finish($attempt, 'timeout');
        }
        if ($attempt->refresh()->isFinished()) {
            return redirect()->route('exams.result', $attempt);
        }
        $attempt->update(['last_seen_at' => now()]);

        return view('portal.exams.room', [
            'attempt' => $attempt,
            'exam' => $attempt->exam,
            'paper' => $this->exams->paper($attempt),
        ]);
    }

    public function answer(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->own($request, $attempt);
        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'choice' => ['nullable', 'string', 'size:1'],
            'flagged' => ['nullable', 'boolean'],
        ]);

        return $this->guard(function () use ($attempt, $data, $request) {
            $this->exams->answer($attempt, (int) $data['question_id'], isset($data['choice']) ? strtoupper($data['choice']) : null, $request->has('flagged') ? (bool) $data['flagged'] : null);

            return $this->status($attempt->refresh());
        }, $attempt);
    }

    /** Sinkron banyak jawaban (setelah offline) sekaligus detak jantung: waktu tambahan dari admin ikut terbawa. */
    public function sync(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->own($request, $attempt);
        $answers = (array) $request->input('answers', []);
        $flags = (array) $request->input('flags', []);

        return $this->guard(function () use ($attempt, $answers, $flags) {
            if ($answers || $flags) {
                $this->exams->answerMany($attempt, $answers, $flags);
            } elseif ($attempt->deadline_at->copy()->addSeconds(ExamService::GRACE_SECONDS)->isPast()) {
                $this->exams->finish($attempt, 'timeout');
            } else {
                $attempt->update(['last_seen_at' => now()]);
            }

            return $this->status($attempt->refresh());
        }, $attempt);
    }

    public function violation(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->own($request, $attempt);
        $attempt = $this->exams->violation($attempt, (string) $request->input('type', 'tab'));

        return $this->status($attempt);
    }

    public function submit(Request $request, ExamAttempt $attempt): JsonResponse|RedirectResponse
    {
        $this->own($request, $attempt);
        if ($attempt->isActive()) {
            try {
                $this->exams->answerMany($attempt, (array) $request->input('answers', []), (array) $request->input('flags', []));
            } catch (ExamException) {
                // waktu habis: jawaban yang sudah tersimpan tetap dinilai
            }
            $this->exams->finish($attempt->refresh(), 'user');
        }

        return $request->expectsJson()
            ? response()->json(['finished' => true, 'redirect' => route('exams.result', $attempt)])
            : redirect()->route('exams.result', $attempt);
    }

    public function result(Request $request, ExamAttempt $attempt): View|RedirectResponse
    {
        $this->own($request, $attempt);
        if ($attempt->isActive()) {
            return redirect()->route('exams.room', $attempt);
        }
        $exam = $attempt->exam;

        return view('portal.exams.result', [
            'attempt' => $attempt,
            'exam' => $exam,
            'review' => $exam->showsReview() ? $attempt->answers()->with('question')->get() : null,
            'canRetry' => ! $this->exams->cannotStart($exam, $request->user()),
        ]);
    }

    private function own(Request $request, ExamAttempt $attempt): void
    {
        // Ujian yang dipindah ke Tempat sampah tidak lagi terlihat oleh peserta
        abort_unless($attempt->user_id === $request->user()->id && $attempt->exam, 404);
    }

    private function status(ExamAttempt $attempt): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'finished' => $attempt->isFinished(),
            'redirect' => $attempt->isFinished() ? route('exams.result', $attempt) : null,
            'left' => $attempt->secondsLeft(),
            'answered' => $attempt->answered,
            'violations' => $attempt->violations,
        ]);
    }

    private function guard(callable $fn, ExamAttempt $attempt): JsonResponse
    {
        try {
            return $fn();
        } catch (ExamException $e) {
            return response()->json([
                'ok' => false, 'message' => $e->getMessage(), 'finished' => $e->finished,
                'redirect' => $e->finished ? route('exams.result', $attempt) : null,
            ], $e->finished ? 409 : 422);
        }
    }
}
