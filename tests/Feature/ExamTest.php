<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionSet;
use App\Models\User;
use App\Services\Exams\ExamService;
use App\Services\Exams\QuestionImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ExamTest extends TestCase
{
    use RefreshDatabase;

    private User $mech;

    private User $admin;

    private QuestionSet $set;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mech = User::factory()->activated()->create(['nrp' => 'KT16042', 'name' => 'IKHSANUDIN APRIYANTO', 'department' => 'SERVICE REPAIR RM']);
        $this->admin = User::factory()->admin()->activated()->create(['nrp' => 'KT12345']);
        $this->set = QuestionSet::create(['name' => 'BEST MEKANIK 2026']);
        foreach (range(1, 10) as $i) {
            Question::create(['question_set_id' => $this->set->id, 'body' => "Soal nomor {$i}?", 'options' => ['A' => "a{$i}", 'B' => "b{$i}", 'C' => "c{$i}", 'D' => "d{$i}"], 'answer' => 'C', 'sort_order' => $i]);
        }
    }

    private function exam(array $attrs = []): Exam
    {
        $exam = Exam::create($attrs + [
            'title' => 'Seleksi Best Mekanik 2026', 'code' => 'BM-7K2Q', 'audience' => 'assigned', 'duration_minutes' => 30,
            'opens_at' => now()->subHour(), 'closes_at' => now()->addHours(3), 'is_published' => true,
            'pass_score' => 70, 'max_attempts' => 1, 'max_violations' => 3, 'result_mode' => 'score',
        ]);
        $exam->sets()->attach($this->set->id);
        $exam->participants()->create(['user_id' => $this->mech->id, 'label' => 'Kandidat Best Mekanik']);

        return $exam;
    }

    private function begin(Exam $exam): ExamAttempt
    {
        $this->actingAs($this->mech)->post(route('exams.start', $exam), ['code' => 'bm-7k2q'])->assertRedirect();

        return ExamAttempt::where('user_id', $this->mech->id)->latest('id')->firstOrFail();
    }

    public function test_answer_key_never_reaches_the_participant(): void
    {
        $attempt = $this->begin($this->exam());
        $paper = app(ExamService::class)->paper($attempt);
        $this->assertCount(10, $paper);
        foreach ($paper as $item) {
            $this->assertArrayNotHasKey('answer', $item);
            $this->assertArrayNotHasKey('correctKey', $item);
        }
        $html = $this->actingAs($this->mech)->get(route('exams.room', $attempt))->assertOk()->getContent();
        $this->assertStringNotContainsString('"answer"', $html);
        $this->assertStringContainsString('Soal nomor', $html);
    }

    public function test_code_is_required_and_must_match(): void
    {
        $exam = $this->exam();
        $this->actingAs($this->mech)->post(route('exams.start', $exam), ['code' => 'SALAH'])->assertSessionHasErrors('code');
        $this->assertSame(0, ExamAttempt::count());
        $this->actingAs($this->mech)->post(route('exams.code'), ['code' => ' bm-7k2q '])->assertRedirect();
        $this->assertSame(1, ExamAttempt::count());
    }

    public function test_unassigned_user_cannot_join_assigned_exam_but_can_join_open_exam(): void
    {
        $exam = $this->exam();
        $other = User::factory()->activated()->create();
        $this->actingAs($other)->from('/ujian')->post(route('exams.code'), ['code' => 'BM-7K2Q'])->assertSessionHasErrors('code');
        $this->assertSame(0, ExamAttempt::where('user_id', $other->id)->count());

        $exam->update(['audience' => 'open']);
        $this->actingAs($other)->post(route('exams.code'), ['code' => 'BM-7K2Q'])->assertRedirect();
        $this->assertSame(1, ExamAttempt::where('user_id', $other->id)->count());
    }

    public function test_scheduled_and_closed_exams_cannot_be_started(): void
    {
        $exam = $this->exam(['opens_at' => now()->addHour()]);
        $this->actingAs($this->mech)->post(route('exams.start', $exam), ['code' => 'BM-7K2Q'])->assertSessionHasErrors('code');
        $exam->update(['opens_at' => now()->subHours(3), 'closes_at' => now()->subMinute()]);
        $this->actingAs($this->mech)->post(route('exams.start', $exam), ['code' => 'BM-7K2Q'])->assertSessionHasErrors('code');
        $this->assertSame(0, ExamAttempt::count());
    }

    public function test_score_is_computed_on_server_from_saved_choices(): void
    {
        $exam = $this->exam(['shuffle_options' => true]);
        $attempt = $this->begin($exam);
        $ids = $attempt->answers()->pluck('question_id')->all();

        // 7 benar (kunci asli C), 2 salah, 1 kosong
        foreach (array_slice($ids, 0, 7) as $id) {
            $this->actingAs($this->mech)->postJson(route('exams.answer', $attempt), ['question_id' => $id, 'choice' => 'C'])->assertOk()->assertJson(['ok' => true]);
        }
        foreach (array_slice($ids, 7, 2) as $id) {
            $this->actingAs($this->mech)->postJson(route('exams.answer', $attempt), ['question_id' => $id, 'choice' => 'A'])->assertOk();
        }
        // Pilihan di luar opsi ditolak
        $this->actingAs($this->mech)->postJson(route('exams.answer', $attempt), ['question_id' => $ids[9], 'choice' => 'Z'])->assertStatus(422);

        // Data palsu dari HP tidak berpengaruh
        $this->actingAs($this->mech)->postJson(route('exams.submit', $attempt), ['score' => 100, 'correct' => 10, 'benar' => 10])->assertOk()->assertJson(['finished' => true]);
        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame(7, $attempt->correct);
        $this->assertSame(9, $attempt->answered);
        $this->assertEqualsWithDelta(70.0, $attempt->score, 0.01);
        $this->assertTrue($attempt->passed());

        // Tidak bisa menjawab setelah dikumpulkan
        $this->actingAs($this->mech)->postJson(route('exams.answer', $attempt), ['question_id' => $ids[9], 'choice' => 'C'])->assertStatus(409);
        $this->actingAs($this->mech)->get(route('exams.result', $attempt))->assertOk()->assertSee('70')->assertSee('Lulus');
    }

    public function test_offline_answers_are_accepted_in_the_final_submit(): void
    {
        $attempt = $this->begin($this->exam());
        $answers = $attempt->answers()->pluck('question_id')->mapWithKeys(fn ($id) => [$id => 'C'])->all();
        $this->actingAs($this->mech)->postJson(route('exams.submit', $attempt), ['answers' => $answers])->assertOk();
        $this->assertSame(10, $attempt->refresh()->correct);
    }

    public function test_deadline_is_enforced_by_server(): void
    {
        $attempt = $this->begin($this->exam());
        $id = $attempt->answers()->value('question_id');
        $this->travel(31)->minutes();
        $this->actingAs($this->mech)->postJson(route('exams.answer', $attempt), ['question_id' => $id, 'choice' => 'C'])->assertStatus(409)->assertJson(['finished' => true]);
        $attempt->refresh();
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame('timeout', $attempt->finish_reason);
        $this->assertSame(0, $attempt->correct);
        $this->actingAs($this->mech)->get(route('exams.room', $attempt))->assertRedirect(route('exams.result', $attempt));
    }

    public function test_deadline_never_passes_closing_time_and_overdue_attempts_close_without_cron(): void
    {
        $exam = $this->exam(['closes_at' => now()->addMinutes(10)]);
        $attempt = $this->begin($exam);
        $this->assertTrue($attempt->deadline_at->lte($exam->closes_at));
        $this->travel(11)->minutes();
        $this->actingAs($this->admin)->get(route('admin.exams.show', $exam))->assertOk();
        $this->assertSame('timeout', $attempt->refresh()->finish_reason);
    }

    public function test_leaving_the_page_too_often_submits_automatically(): void
    {
        $attempt = $this->begin($this->exam(['max_violations' => 2]));
        $this->actingAs($this->mech)->postJson(route('exams.violation', $attempt), ['type' => 'keluar'])->assertJson(['finished' => false, 'violations' => 1]);
        $this->actingAs($this->mech)->postJson(route('exams.violation', $attempt), ['type' => 'keluar'])->assertJson(['finished' => true]);
        $this->assertSame('violations', $attempt->refresh()->finish_reason);
    }

    public function test_resume_keeps_same_attempt_and_attempt_limit_applies(): void
    {
        $exam = $this->exam();
        $attempt = $this->begin($exam);
        $this->actingAs($this->mech)->post(route('exams.start', $exam), ['code' => 'BM-7K2Q'])->assertRedirect(route('exams.room', $attempt));
        $this->assertSame(1, ExamAttempt::count());

        $this->actingAs($this->mech)->post(route('exams.submit', $attempt))->assertRedirect(route('exams.result', $attempt));
        $this->actingAs($this->mech)->post(route('exams.start', $exam), ['code' => 'BM-7K2Q'])->assertSessionHasErrors('code');
        $this->assertSame(1, ExamAttempt::count());
    }

    public function test_attempts_are_private(): void
    {
        $attempt = $this->begin($this->exam());
        $other = User::factory()->activated()->create();
        $this->actingAs($other)->get(route('exams.room', $attempt))->assertNotFound();
        $this->actingAs($other)->postJson(route('exams.answer', $attempt), ['question_id' => 1, 'choice' => 'A'])->assertNotFound();
    }

    public function test_assigned_participant_gets_notified_once_per_login_on_home(): void
    {
        $this->exam();
        $this->actingAs($this->mech)->get('/')->assertOk()->assertSee('Anda ditunjuk sebagai peserta')->assertSee('Kandidat Best Mekanik')->assertSee('Kerjakan sekarang');
        // Kunjungan berikutnya di sesi yang sama: kartu tetap ada, pemberitahuan tidak muncul lagi
        $this->actingAs($this->mech)->get('/')->assertOk()->assertDontSee('Anda ditunjuk sebagai peserta')->assertSee('Ujian untuk Anda');
        $this->assertNotNull(\App\Models\ExamParticipant::first()->notified_at);

        $other = User::factory()->activated()->create();
        $this->actingAs($other)->get('/')->assertOk()->assertDontSee('Seleksi Best Mekanik 2026');
    }

    public function test_hidden_result_mode_does_not_reveal_score(): void
    {
        $attempt = $this->begin($this->exam(['result_mode' => 'hidden']));
        $this->actingAs($this->mech)->post(route('exams.submit', $attempt));
        $this->actingAs($this->mech)->get(route('exams.result', $attempt))->assertOk()->assertSee('Jawaban terkirim')->assertDontSee('Belum mencapai nilai lulus');
    }

    public function test_review_only_after_exam_closes(): void
    {
        $exam = $this->exam(['result_mode' => 'review']);
        $attempt = $this->begin($exam);
        $this->actingAs($this->mech)->post(route('exams.submit', $attempt));
        $this->actingAs($this->mech)->get(route('exams.result', $attempt))->assertDontSee('c1</span>', false)->assertSee('Pembahasan dibuka setelah ujian ditutup');
        $exam->update(['closes_at' => now()->subMinute()]);
        $this->actingAs($this->mech)->get(route('exams.result', $attempt))->assertSee('Pembahasan')->assertSee('Soal nomor 1?');
    }

    public function test_importer_reads_legacy_google_sheet_layout(): void
    {
        $rows = [
            ['', 'Pertanyaan', 'Gambar', 'Opsi A', 'Opsi B', 'Opsi C', 'Opsi D', 'Jawaban', 'KodeUjian'],
            ['1', 'Apa yang dimaksud IPAL ...', '', 'Instalasi Pengolahan Air Limbah', 'Instalasi Pengelolaan', 'Instrumen', 'Lainnya', 'A', 'BMCMEI2026'],
            ['2', 'Komponen pada gambar?', 'https://github.com/jokosaptono17/kumpulan_gambar_untuk_soal/blob/main/TFT%20scania.png?raw=true', 'TFT', 'ECU', 'BCI', 'APS', 'b', 'PM SCANIA'],
            ['3', 'Tanpa kunci', '', 'x', 'y', '', '', '', 'PM SCANIA'],
            ['4', 'Kunci berupa teks', '', 'Merah', 'Hijau', '', '', 'hijau', 'PM SCANIA'],
            ['5', 'Tanpa paket', '', 'x', 'y', '', '', 'A', ''],
            ['', '', '', '', '', '', '', '', ''],
        ];
        $summary = app(QuestionImporter::class)->import($rows);
        $this->assertSame(3, $summary['created']);
        $this->assertCount(2, $summary['skipped']);
        $this->assertSame(['BMCMEI2026' => 1, 'PM SCANIA' => 2], $summary['sets']);
        $q = Question::where('body', 'Komponen pada gambar?')->first();
        $this->assertSame('B', $q->answer);
        $this->assertSame('https://raw.githubusercontent.com/jokosaptono17/kumpulan_gambar_untuk_soal/main/TFT%20scania.png', $q->image_path);
        $this->assertSame(['A' => 'TFT', 'B' => 'ECU', 'C' => 'BCI', 'D' => 'APS'], $q->options);
        $this->assertSame('B', Question::where('body', 'Kunci berupa teks')->value('answer'));

        // Impor ulang tidak membuat soal ganda
        $again = app(QuestionImporter::class)->import($rows);
        $this->assertSame(0, $again['created']);
        $this->assertSame(3, $again['duplicates']);
    }

    public function test_admin_imports_xlsx_and_downloads_template(): void
    {
        $this->actingAs($this->admin)->get(route('admin.exams.bank.template'))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'soal').'.xlsx';
        \App\Services\Spreadsheet::writeXlsx($path, [
            ['Paket', 'Pertanyaan', 'Gambar', 'A', 'B', 'C', 'D', 'Jawaban', 'Pembahasan'],
            ['BEST GL 2026', 'Langkah pertama sebelum perbaikan?', '', 'LOTO', 'Buka cover', 'Tools', 'Form', 'A', 'Isolasi energi dulu'],
        ], 'Soal');
        $this->actingAs($this->admin)->post(route('admin.exams.bank.import.store'), ['file' => new UploadedFile($path, 'soal.xlsx', null, null, true)])
            ->assertRedirect(route('admin.exams.bank.import'))->assertSessionHas('summary');
        $this->assertSame('Isolasi energi dulu', Question::where('body', 'Langkah pertama sebelum perbaikan?')->value('explanation'));
    }

    public function test_admin_creates_exam_with_participants_and_monitors(): void
    {
        $this->actingAs($this->admin)->get(route('admin.exams.create'))->assertOk()->assertSee('Paket soal');
        $this->actingAs($this->admin)->post(route('admin.exams.store'), [
            'title' => 'Best GL 2026', 'code' => 'bgl 2026', 'audience' => 'assigned', 'duration_minutes' => 45,
            'opens_at' => now()->subMinute()->format('Y-m-d\TH:i'), 'closes_at' => now()->addHours(2)->format('Y-m-d\TH:i'),
            'pass_score' => 75, 'max_attempts' => 1, 'max_violations' => 3, 'result_mode' => 'hidden',
            'shuffle_questions' => '1', 'shuffle_options' => '1', 'publish' => '1',
            'sets' => [['id' => $this->set->id, 'take' => 5]],
            'participants' => [['user_id' => $this->mech->id, 'label' => 'Kandidat Best GL']],
        ])->assertRedirect();
        $exam = Exam::firstWhere('code', 'BGL2026');
        $this->assertNotNull($exam);
        $this->assertTrue($exam->is_published);
        $this->assertSame(5, $exam->questionCount());
        $this->assertSame('Kandidat Best GL', $exam->participants()->value('label'));

        $this->actingAs($this->admin)->getJson(route('admin.exams.feed', $exam))->assertOk()->assertJsonPath('stats.waiting', 1);
        $this->actingAs($this->mech)->post(route('exams.start', $exam), ['code' => 'BGL2026']);
        $attempt = ExamAttempt::firstOrFail();
        $this->assertSame(5, $attempt->total);
        $this->actingAs($this->admin)->getJson(route('admin.exams.feed', $exam))->assertJsonPath('stats.live', 1)->assertJsonPath('rows.0.status', 'in_progress');

        $this->actingAs($this->admin)->postJson(route('admin.exams.attempt.action', [$exam, $attempt]), ['action' => 'extend', 'minutes' => 10])->assertOk();
        $this->assertTrue($attempt->refresh()->deadline_at->gt(now()->addMinutes(50)));
        $this->actingAs($this->admin)->postJson(route('admin.exams.attempt.action', [$exam, $attempt]), ['action' => 'finish'])->assertOk();
        $this->actingAs($this->admin)->get(route('admin.exams.show', $exam))->assertOk()->assertSee('BGL2026');
        $this->actingAs($this->admin)->get(route('admin.exams.attempt', [$exam, $attempt]))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.exams.screen', $exam))->assertOk()->assertSee('BGL2026');
        $this->actingAs($this->admin)->get(route('admin.exams.export', $exam))->assertOk();

        // Karyawan biasa tidak bisa membuka admin ujian
        $this->actingAs($this->mech)->get(route('admin.exams.index'))->assertForbidden();
    }

    public function test_regrade_after_fixing_wrong_key_and_item_analysis_flags_it(): void
    {
        $exam = $this->exam();
        $people = User::factory()->count(3)->activated()->create();
        foreach ($people as $p) {
            $exam->participants()->create(['user_id' => $p->id]);
        }
        $first = Question::where('body', 'Soal nomor 1?')->first();
        $service = app(ExamService::class);
        foreach ($people as $p) {
            $a = $service->start($exam, $p);
            $service->answer($a, $first->id, 'B');   // semua memilih B, kunci tertulis C
            $service->finish($a);
        }
        $item = $service->itemAnalysis($exam)->firstWhere('question.id', $first->id);
        $this->assertTrue($item->suspect);
        $this->assertSame(0, $item->rate);

        $first->update(['answer' => 'B']);
        $this->actingAs($this->admin)->post(route('admin.exams.regrade', $exam))->assertRedirect();
        $this->assertSame([1, 1, 1], ExamAttempt::pluck('correct')->all());
    }

    public function test_ranking_breaks_ties_by_duration(): void
    {
        $exam = $this->exam();
        $fast = User::factory()->activated()->create(['name' => 'CEPAT']);
        $exam->participants()->create(['user_id' => $fast->id]);
        $service = app(ExamService::class);

        $slow = $service->start($exam, $this->mech);
        $this->travel(5)->minutes();
        $quick = $service->start($exam, $fast);
        $this->travel(1)->minutes();
        $service->finish($quick);
        $service->finish($slow);

        $ranking = $service->ranking($exam);
        $this->assertSame('CEPAT', $ranking->first()->user->name);
    }
}
