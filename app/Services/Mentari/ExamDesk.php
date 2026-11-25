<?php

namespace App\Services\Mentari;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\Exams\ExamService;

/**
 * Mentari soal Ujian Event: "ujian saya", "ada ujian?", "nilai ujian saya", "kode ujian".
 * Kode ujian TIDAK pernah diberikan ke peserta (diumumkan pengawas); hanya admin ujian yang bisa melihatnya.
 */
class ExamDesk
{
    public function __construct(private ExamService $exams) {}

    public function answer(string $q, User $viewer): ?array
    {
        if (! preg_match('/\b(ujian|exam|test|tes|soal|kompetisi|lomba|best mekanik|best gl|pre ?test|post ?test)\b/u', $q)) {
            return null;
        }
        // "tes", "test 123" = mencoba Mentari, bukan bertanya soal ujian
        if (preg_match('/^(tes|test)(\s+(tes|test|\d+))*$/u', $q)) {
            return null;
        }
        // "cara bikin soal", "bank soal" untuk admin → biar ditangani bagian lain/pengetahuan
        if (preg_match('/\b(bank soal|buat soal|bikin soal|impor soal|import soal)\b/u', $q)) {
            return null;
        }

        if (preg_match('/\b(cara|bagaimana|kenapa|mengapa|aturan|kalau|keluar halaman|pelanggaran|waktu habis)\b/u', $q) && ! preg_match('/\bkode\b/u', $q)) {
            return null;
        }
        if (preg_match('/\bkode\b/u', $q)) {
            return $this->code($viewer);
        }
        if (preg_match('/\b(nilai|hasil|skor|score|lulus|peringkat|ranking|juara)\b/u', $q)) {
            return $this->results($viewer);
        }

        return $this->pending($viewer);
    }

    private function pending(User $viewer): array
    {
        $items = $this->exams->pendingFor($viewer);
        if ($items->isEmpty()) {
            $last = ExamAttempt::where('user_id', $viewer->id)->where('status', '!=', 'in_progress')->whereHas('exam')->latest('id')->first();

            return $this->reply('Saat ini tidak ada ujian yang ditunjuk untuk Anda.'.($last ? ' Ujian terakhir Anda: '.$last->exam->title.'.' : '')
                .' Bila pengawas memberi kode, masukkan di halaman Ujian saya.', [], [['label' => 'Ujian saya', 'href' => route('exams.index')]]);
        }

        $rows = $items->map(function ($i) {
            $exam = $i->exam;
            $status = match (true) {
                (bool) $i->attempt => 'Sedang dikerjakan · sisa '.ExamAttempt::formatDuration($i->attempt->secondsLeft()),
                $i->state === 'open' => 'Dibuka'.($exam->closes_at ? ' sampai '.$exam->closes_at->format('H:i') : ''),
                default => 'Dibuka '.$exam->opens_at->locale('id')->translatedFormat('l j M, H:i'),
            };

            return [
                'title' => $exam->title, 'subtitle' => $status.' · '.$exam->questionCount().' soal · '.$exam->duration_minutes.' menit',
                'meta' => $i->label, 'tone' => $i->attempt ? 'warn' : null,
            ];
        })->all();
        $first = $items->first();
        $action = $first->attempt
            ? ['label' => 'Lanjutkan ujian', 'href' => route('exams.room', $first->attempt)]
            : ['label' => $first->state === 'open' ? 'Kerjakan sekarang' : 'Lihat ujian', 'href' => route('exams.show', $first->exam)];

        return $this->reply(
            $items->count() === 1 ? 'Ada 1 ujian untuk Anda:' : "Ada {$items->count()} ujian untuk Anda:",
            [['type' => 'list', 'title' => null, 'items' => $rows]],
            [$action, ['label' => 'Ujian saya', 'href' => route('exams.index')]],
        );
    }

    private function results(User $viewer): array
    {
        $tries = ExamAttempt::where('user_id', $viewer->id)->where('status', '!=', 'in_progress')->whereHas('exam')->with('exam')->latest('submitted_at')->limit(5)->get();
        if ($tries->isEmpty()) {
            return $this->reply('Anda belum punya hasil ujian.', [], [['label' => 'Ujian saya', 'href' => route('exams.index')]]);
        }
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');

        return $this->reply('Hasil ujian terakhir Anda:', [[
            'type' => 'list', 'title' => null,
            'items' => $tries->map(fn ($a) => [
                'title' => $a->exam->title,
                'subtitle' => $a->submitted_at?->locale('id')->translatedFormat('j M Y, H:i'),
                'meta' => match (true) {
                    $a->status === 'disqualified' => 'Diskualifikasi',
                    $a->exam->showsScore() => $fmt($a->score).($a->passed() ? ' · Lulus' : ''),
                    default => 'Menunggu panitia',
                },
                'tone' => $a->status === 'disqualified' ? 'alarm' : null,
            ])->all(),
        ]], [['label' => 'Ujian saya', 'href' => route('exams.index')]]);
    }

    private function code(User $viewer): array
    {
        if ($viewer->can('manage-exams')) {
            $open = Exam::openNow()->latest('id')->limit(5)->get();

            return $this->reply($open->isEmpty() ? 'Tidak ada ujian yang sedang dibuka.' : 'Kode ujian yang sedang dibuka (khusus admin):', $open->isEmpty() ? [] : [[
                'type' => 'list', 'title' => null,
                'items' => $open->map(fn ($e) => ['title' => $e->title, 'subtitle' => 'Kode '.$e->code, 'meta' => null])->all(),
            ]], [['label' => 'Event ujian', 'href' => route('admin.exams.index')]]);
        }

        return $this->reply('Kode ujian dibacakan atau ditampilkan pengawas di ruang ujian saat ujian dimulai, jadi saya tidak bisa memberikannya. Bila Anda ditunjuk sebagai peserta, ujiannya muncul di beranda — cukup masukkan kode dari pengawas.', [], [
            ['label' => 'Ujian saya', 'href' => route('exams.index')],
        ]);
    }

    private function reply(string $text, array $cards = [], array $actions = []): array
    {
        return ['reply' => $text, 'cards' => $cards, 'actions' => $actions, 'intent' => 'exams'];
    }
}
