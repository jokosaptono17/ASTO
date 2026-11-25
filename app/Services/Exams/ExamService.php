<?php

namespace App\Services\Exams;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Inti Ujian Event. Aturan yang dijaga:
 * - kunci jawaban tidak pernah keluar dari server; peserta hanya mengirim pilihan,
 * - nilai dihitung di server dari lembar jawaban yang tersimpan,
 * - batas waktu dipegang server (deadline_at), jam HP hanya untuk tampilan,
 * - jawaban tersimpan per soal sehingga ujian bisa dilanjutkan bila HP mati / halaman ter-refresh.
 */
class ExamService
{
    /** Toleransi jaringan setelah waktu habis untuk jawaban yang sedang terkirim. */
    public const GRACE_SECONDS = 20;

    /** Alasan user tidak bisa memulai ujian; null = boleh. */
    public function cannotStart(Exam $exam, User $user): ?string
    {
        if (! $exam->is_published) {
            return 'Ujian ini belum diterbitkan panitia.';
        }
        if ($exam->audience === 'assigned' && ! $exam->isAssigned($user)) {
            return 'Anda tidak terdaftar sebagai peserta ujian ini. Hubungi panitia bila seharusnya terdaftar.';
        }
        if ($exam->state() === 'scheduled') {
            return 'Ujian baru dibuka '.$exam->opens_at->locale('id')->translatedFormat('l, j F Y \p\u\k\u\l H:i').'.';
        }
        if ($exam->state() === 'closed') {
            return 'Ujian ini sudah ditutup.';
        }
        $used = $exam->attempts()->where('user_id', $user->id)->count();
        if ($used >= $exam->max_attempts) {
            return $exam->max_attempts > 1 ? "Kesempatan mengerjakan sudah habis ({$exam->max_attempts}×)." : 'Anda sudah mengerjakan ujian ini.';
        }
        if ($exam->questionCount() === 0) {
            return 'Soal ujian belum disiapkan panitia.';
        }

        return null;
    }

    public function activeAttempt(Exam $exam, User $user): ?ExamAttempt
    {
        return $exam->attempts()->where('user_id', $user->id)->where('status', 'in_progress')->latest('id')->first();
    }

    /** Mulai (atau lanjutkan) ujian: susun lembar soal acak khusus peserta ini. */
    public function start(Exam $exam, User $user, ?string $ip = null, ?string $agent = null): ExamAttempt
    {
        $this->closeOverdue($exam);
        if ($active = $this->activeAttempt($exam, $user)) {
            return $active;
        }
        if ($reason = $this->cannotStart($exam, $user)) {
            throw new ExamException($reason);
        }

        $paper = $this->buildPaper($exam);
        $deadline = now()->addMinutes($exam->duration_minutes);
        if ($exam->closes_at && $exam->closes_at->lt($deadline)) {
            $deadline = $exam->closes_at->copy();
        }

        try {
            return DB::transaction(function () use ($exam, $user, $paper, $deadline, $ip, $agent) {
                $attempt = ExamAttempt::create([
                    'exam_id' => $exam->id, 'user_id' => $user->id,
                    'attempt_no' => $exam->attempts()->where('user_id', $user->id)->max('attempt_no') + 1,
                    'status' => 'in_progress', 'violations' => 0, 'answered' => 0,
                    'started_at' => now(), 'deadline_at' => $deadline, 'last_seen_at' => now(),
                    'total' => count($paper), 'ip' => $ip, 'user_agent' => mb_substr((string) $agent, 0, 255),
                ]);
                ExamAnswer::insert(collect($paper)->values()->map(fn ($p, $i) => [
                    'exam_attempt_id' => $attempt->id, 'question_id' => $p['id'], 'position' => $i + 1, 'option_order' => $p['order'],
                ])->all());

                return $attempt;
            });
        } catch (QueryException $e) {
            // Dua ketukan "Mulai" bersamaan: pakai percobaan yang sudah terbuat
            if ($active = $this->activeAttempt($exam, $user)) {
                return $active;
            }
            throw $e;
        }
    }

    /** @return list<array{id:int, order:string}> */
    private function buildPaper(Exam $exam): array
    {
        $paper = [];
        foreach ($exam->sets()->get() as $set) {
            $questions = $set->activeQuestions()->get(['id', 'options']);
            if ($set->pivot->take && $set->pivot->take < $questions->count()) {
                $questions = $questions->shuffle()->take($set->pivot->take)->sortBy(fn ($q) => $q->id);
            }
            foreach ($questions as $q) {
                $letters = array_keys($q->filledOptions());
                if ($exam->shuffle_options) {
                    shuffle($letters);
                }
                $paper[] = ['id' => $q->id, 'order' => implode('', $letters)];
            }
        }
        if ($exam->shuffle_questions) {
            shuffle($paper);
        }

        return $paper;
    }

    /** Lembar soal untuk HP peserta — TANPA kunci jawaban. */
    public function paper(ExamAttempt $attempt): array
    {
        $answers = $attempt->answers()->with('question:id,body,image_path,options')->get();

        return $answers->map(function (ExamAnswer $a) {
            $opts = $a->question->options;

            return [
                'id' => $a->question_id,
                'no' => $a->position,
                'body' => $a->question->body,
                'image' => $a->question->imageUrl(),
                'options' => collect(str_split($a->option_order))->values()->map(fn ($key, $i) => [
                    'key' => $key, 'label' => Question::LETTERS[$i], 'text' => (string) ($opts[$key] ?? ''),
                ])->all(),
                'choice' => $a->choice,
                'flagged' => $a->flagged,
            ];
        })->all();
    }

    /** Simpan satu jawaban (autosave). */
    public function answer(ExamAttempt $attempt, int $questionId, ?string $choice, ?bool $flagged = null): ExamAttempt
    {
        $this->assertWritable($attempt);
        $row = $attempt->answers()->where('question_id', $questionId)->first();
        if (! $row) {
            throw new ExamException('Soal tidak ada di lembar ujian Anda.');
        }
        if ($choice !== null && ! str_contains($row->option_order, $choice)) {
            throw new ExamException('Pilihan jawaban tidak valid.');
        }
        $data = ['choice' => $choice, 'answered_at' => $choice ? now() : null];
        if ($flagged !== null) {
            $data['flagged'] = $flagged;
        }
        $row->update($data);
        $attempt->update(['answered' => $attempt->answers()->whereNotNull('choice')->count(), 'last_seen_at' => now()]);

        return $attempt;
    }

    /** Terapkan banyak jawaban sekaligus (dipakai saat kumpul / sinkron setelah offline). */
    public function answerMany(ExamAttempt $attempt, array $choices, array $flags = []): void
    {
        $this->assertWritable($attempt);
        $rows = $attempt->answers()->get()->keyBy('question_id');
        DB::transaction(function () use ($rows, $choices, $flags) {
            foreach ($choices as $qid => $choice) {
                $row = $rows->get((int) $qid);
                $choice = $choice === null || $choice === '' ? null : strtoupper((string) $choice);
                if (! $row || ($choice !== null && ! str_contains($row->option_order, $choice))) {
                    continue;
                }
                if ($row->choice !== $choice) {
                    $row->update(['choice' => $choice, 'answered_at' => $choice ? now() : null]);
                }
            }
            foreach ($flags as $qid => $flag) {
                $rows->get((int) $qid)?->update(['flagged' => (bool) $flag]);
            }
        });
        $attempt->update(['answered' => $attempt->answers()->whereNotNull('choice')->count(), 'last_seen_at' => now()]);
    }

    private function assertWritable(ExamAttempt $attempt): void
    {
        if (! $attempt->isActive()) {
            throw new ExamException('Ujian sudah dikumpulkan.', finished: true);
        }
        if ($attempt->deadline_at->copy()->addSeconds(self::GRACE_SECONDS)->isPast()) {
            $this->finish($attempt, 'timeout');
            throw new ExamException('Waktu ujian sudah habis. Jawaban yang tersimpan sudah dikumpulkan.', finished: true);
        }
    }

    /** Catat pelanggaran (pindah aplikasi/tab). Bila mencapai batas, ujian dikumpulkan otomatis. */
    public function violation(ExamAttempt $attempt, string $type): ExamAttempt
    {
        if (! $attempt->isActive()) {
            return $attempt;
        }
        $log = $attempt->violation_log ?? [];
        $log[] = ['t' => now()->format('H:i:s'), 'type' => mb_substr($type, 0, 20)];
        $attempt->update(['violations' => min(255, $attempt->violations + 1), 'violation_log' => array_slice($log, -60), 'last_seen_at' => now()]);

        $max = $attempt->exam->max_violations;
        if ($max > 0 && $attempt->violations >= $max) {
            $this->finish($attempt, 'violations');
        }

        return $attempt->fresh();
    }

    /** Kumpulkan & nilai. Aman dipanggil berulang. */
    public function finish(ExamAttempt $attempt, string $reason = 'user'): ExamAttempt
    {
        return DB::transaction(function () use ($attempt, $reason) {
            $attempt = ExamAttempt::whereKey($attempt->id)->lockForUpdate()->first();
            if (! $attempt->isActive()) {
                return $attempt;
            }
            $this->grade($attempt);
            $attempt->update([
                'status' => 'submitted', 'finish_reason' => $reason,
                'submitted_at' => $reason === 'timeout' ? min(now(), $attempt->deadline_at) : now(),
            ]);

            return $attempt;
        });
    }

    /** Hitung benar & nilai dari kunci terkini (dipakai juga untuk "Hitung ulang"). */
    public function grade(ExamAttempt $attempt): void
    {
        $answers = $attempt->answers()->with('question:id,answer')->get();
        $correct = 0;
        foreach ($answers as $a) {
            $ok = $a->choice !== null && $a->choice === $a->question->answer;
            $correct += $ok ? 1 : 0;
            if ($a->is_correct !== $ok) {
                $a->update(['is_correct' => $ok]);
            }
        }
        $total = max(1, $answers->count());
        $attempt->forceFill([
            'correct' => $correct, 'total' => $answers->count(),
            'answered' => $answers->whereNotNull('choice')->count(),
            'score' => round($correct / $total * 100, 2),
        ])->save();
    }

    /** Hitung ulang semua nilai event (setelah kunci jawaban diperbaiki). */
    public function regrade(Exam $exam): int
    {
        $n = 0;
        $exam->attempts()->where('status', '!=', 'in_progress')->each(function (ExamAttempt $a) use (&$n) {
            $this->grade($a);
            $n++;
        });

        return $n;
    }

    /** Tutup percobaan yang waktunya sudah lewat (tanpa cron: dipanggil saat halaman dibuka). */
    public function closeOverdue(?Exam $exam = null): int
    {
        $q = ExamAttempt::where('status', 'in_progress')->where('deadline_at', '<', now()->subSeconds(self::GRACE_SECONDS));
        if ($exam) {
            $q->where('exam_id', $exam->id);
        }
        $n = 0;
        $q->each(function (ExamAttempt $a) use (&$n) {
            $this->finish($a, 'timeout');
            $n++;
        });

        return $n;
    }

    public function extend(ExamAttempt $attempt, int $minutes): void
    {
        if ($attempt->isActive()) {
            $attempt->update(['deadline_at' => $attempt->deadline_at->copy()->addMinutes($minutes)]);
        }
    }

    public function disqualify(ExamAttempt $attempt): void
    {
        if ($attempt->isActive()) {
            $this->finish($attempt, 'admin');
        }
        $attempt->refresh()->update(['status' => 'disqualified']);
    }

    /** Hapus permanen isi Tempat sampah yang sudah melewati masa simpan. Dipanggil malas + terjadwal. */
    public function purgeTrash(): int
    {
        $n = 0;
        Exam::onlyTrashed()->where('deleted_at', '<', now()->subDays(Exam::TRASH_DAYS))->each(function (Exam $e) use (&$n) {
            $e->forceDelete();
            $n++;
        });

        return $n;
    }

    /** Ujian event untuk user di beranda: yang ditunjuk & masih bisa/perlu dikerjakan. */
    public function pendingFor(User $user): Collection
    {
        $exams = Exam::where('is_published', true)
            ->whereHas('participants', fn ($p) => $p->where('user_id', $user->id))
            ->where(fn ($w) => $w->whereNull('closes_at')->orWhere('closes_at', '>', now()))
            ->with(['attempts' => fn ($a) => $a->where('user_id', $user->id)->orderBy('attempt_no'), 'participants' => fn ($p) => $p->where('user_id', $user->id)])
            ->orderByRaw('opens_at is null')->orderBy('opens_at')->get();

        return $exams->map(function (Exam $exam) {
            $active = $exam->attempts->firstWhere('status', 'in_progress');
            if ($active && $active->deadline_at->copy()->addSeconds(self::GRACE_SECONDS)->isPast()) {
                $this->finish($active, 'timeout');
                $active = null;
                $exam->load(['attempts' => fn ($a) => $a->where('user_id', $exam->participants->first()?->user_id)->orderBy('attempt_no')]);
            }
            $used = $exam->attempts->count();

            return (object) [
                'exam' => $exam,
                'state' => $exam->state(),
                'attempt' => $active,
                'done' => ! $active && $used >= $exam->max_attempts,
                'last' => $exam->attempts->last(),
                'label' => $exam->participants->first()?->label,
            ];
        })->reject(fn ($i) => $i->done)->values();
    }

    /** Peringkat: satu baris per peserta (percobaan terbaik), nilai tertinggi lalu waktu tercepat. */
    public function ranking(Exam $exam): Collection
    {
        return $exam->attempts()->where('status', 'submitted')->with('user:id,nrp,name,department,position')->get()
            ->groupBy('user_id')
            ->map(fn ($tries) => $tries->sort(fn ($a, $b) => [$b->score, $a->durationSeconds()] <=> [$a->score, $b->durationSeconds()])->first())
            ->sort(fn ($a, $b) => [$b->score, $a->durationSeconds(), $a->submitted_at] <=> [$a->score, $b->durationSeconds(), $b->submitted_at])
            ->values()
            ->each(function ($a, $i) {
                $a->rank = $i + 1;
            });
    }

    /** Analisis butir soal: tingkat kesulitan & sebaran pilihan untuk menemukan soal bermasalah. */
    public function itemAnalysis(Exam $exam): Collection
    {
        $rows = ExamAnswer::query()
            ->join('exam_attempts', 'exam_attempts.id', '=', 'exam_answers.exam_attempt_id')
            ->where('exam_attempts.exam_id', $exam->id)->where('exam_attempts.status', '!=', 'in_progress')
            ->groupBy('exam_answers.question_id', 'exam_answers.choice')
            ->selectRaw('exam_answers.question_id, exam_answers.choice, count(*) as n')
            ->get()->groupBy('question_id');
        $questions = Question::whereIn('id', $rows->keys())->with('set:id,name')->get()->keyBy('id');

        return $rows->map(function ($choices, $qid) use ($questions) {
            $q = $questions->get($qid);
            $total = (int) $choices->sum('n');
            $dist = $choices->mapWithKeys(fn ($c) => [$c->choice ?? '-' => (int) $c->n])->all();
            $right = $dist[$q?->answer] ?? 0;
            $top = collect($dist)->except([$q?->answer, '-'])->sortDesc();

            return (object) [
                'question' => $q, 'total' => $total, 'correct' => $right,
                'rate' => $total ? (int) round($right / $total * 100) : 0,
                'blank' => $dist['-'] ?? 0, 'dist' => $dist,
                // Pengecoh yang dipilih lebih banyak dari kunci: kemungkinan kunci salah
                'suspect' => $total >= 3 && $top->isNotEmpty() && $top->first() > $right,
                'topWrong' => $top->keys()->first(),
            ];
        })->filter(fn ($r) => $r->question)->sortBy('rate')->values();
    }
}
