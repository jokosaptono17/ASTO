<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionSet;
use App\Models\User;
use App\Services\Exams\ExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Halaman Admin → Event ujian: dashboard, saringan, arsip, Tempat sampah. */
class ExamAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $mech;

    private QuestionSet $set;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->activated()->create(['nrp' => 'KT12345']);
        $this->mech = User::factory()->activated()->create(['nrp' => 'KT16042', 'name' => 'IKHSANUDIN APRIYANTO']);
        $this->set = QuestionSet::create(['name' => 'BEST MEKANIK 2026']);
        foreach (range(1, 4) as $i) {
            Question::create(['question_set_id' => $this->set->id, 'body' => "Soal {$i}?", 'options' => ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'], 'answer' => 'C', 'sort_order' => $i]);
        }
    }

    private function exam(string $state = 'open', array $attrs = []): Exam
    {
        $times = match ($state) {
            'draft' => ['is_published' => false, 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)],
            'scheduled' => ['is_published' => true, 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)],
            'closed' => ['is_published' => true, 'opens_at' => now()->subDays(3), 'closes_at' => now()->subDays(2)],
            default => ['is_published' => true, 'opens_at' => now()->subHour(), 'closes_at' => now()->addHours(3)],
        };
        $exam = Exam::create($attrs + $times + [
            'title' => 'Ujian '.$state, 'code' => Exam::suggestCode('Ujian '.$state), 'audience' => 'assigned', 'duration_minutes' => 30,
            'pass_score' => 70, 'max_attempts' => 1, 'max_violations' => 3, 'result_mode' => 'score',
        ]);
        $exam->sets()->attach($this->set->id);
        $exam->participants()->create(['user_id' => $this->mech->id]);

        return $exam;
    }

    private function attempt(Exam $exam, string $status = 'submitted', float $score = 80): ExamAttempt
    {
        return ExamAttempt::create([
            'exam_id' => $exam->id, 'user_id' => $this->mech->id, 'attempt_no' => 1, 'status' => $status,
            'started_at' => now()->subMinutes(20), 'deadline_at' => now()->addMinutes(10),
            'submitted_at' => $status === 'in_progress' ? null : now()->subMinutes(5),
            'total' => 4, 'answered' => 4, 'correct' => 3, 'score' => $status === 'in_progress' ? null : $score,
        ]);
    }

    public function test_sql_state_scope_matches_model_state(): void
    {
        foreach (['draft', 'scheduled', 'open', 'closed'] as $s) {
            $this->exam($s);
        }
        foreach (['draft', 'scheduled', 'open', 'closed'] as $s) {
            $found = Exam::inState($s)->get();
            $this->assertCount(1, $found, $s);
            $this->assertSame($s, $found->first()->state());
        }
    }

    public function test_dashboard_and_list_render_with_filters(): void
    {
        $open = $this->exam('open', ['title' => 'Seleksi Best Mekanik', 'category' => 'Best Mekanik']);
        $closed = $this->exam('closed', ['title' => 'Post-test Hydraulic', 'category' => 'Post-test']);
        $this->attempt($closed, 'submitted', 90);
        $this->attempt($open, 'in_progress');

        $this->actingAs($this->admin)->get(route('admin.exams.index'))->assertOk()
            ->assertSee('Sedang berlangsung')->assertSee('Lembar selesai per bulan')->assertSee('Per kategori')
            ->assertSee('Seleksi Best Mekanik')->assertSee('Post-test Hydraulic');

        $this->get(route('admin.exams.index', ['q' => 'hydraulic']))->assertOk()->assertSee('Post-test Hydraulic')->assertSee('1</b> event cocok', false);
        $this->get(route('admin.exams.index', ['kategori' => 'Best Mekanik']))->assertSee('1</b> event cocok', false);
        $this->get(route('admin.exams.index', ['status' => 'closed', 'urut' => 'nilai']))->assertOk()->assertSee('Post-test Hydraulic');
        $this->get(route('admin.exams.index', ['periode' => '30']))->assertOk();
        $this->get(route('admin.exams.export-list', ['status' => 'closed']))->assertOk()->assertDownload();
    }

    public function test_attention_flags_published_exam_without_questions(): void
    {
        $exam = $this->exam('scheduled');
        $exam->sets()->detach();

        $this->actingAs($this->admin)->get(route('admin.exams.index'))->assertSee('Terbit tetapi belum ada paket soal');
        $this->get(route('admin.exams.index', ['cek' => 'tanpa-soal']))->assertSee($exam->title)->assertSee('1</b> event cocok', false);
    }

    public function test_trash_hides_exam_from_participants_and_can_be_restored(): void
    {
        $exam = $this->exam('closed');
        $attempt = $this->attempt($exam);

        $this->actingAs($this->admin)->delete(route('admin.exams.destroy', $exam))->assertRedirect(route('admin.exams.index'));
        $this->assertSoftDeleted($exam);
        $this->assertSame($this->admin->id, Exam::withTrashed()->find($exam->id)->deleted_by);

        // Peserta tidak lagi melihat ujian maupun hasilnya
        $this->flushSession();
        $this->actingAs($this->mech)->get(route('exams.index'))->assertOk()->assertDontSee($exam->title);
        $this->get(route('exams.result', $attempt))->assertNotFound();
        $this->post(route('exams.code'), ['code' => $exam->code])->assertSessionHasErrors();

        // Kode tetap terkunci selama di Tempat sampah
        $this->assertTrue(Exam::withTrashed()->where('code', $exam->code)->exists());

        $this->actingAs($this->admin)->get(route('admin.exams.index', ['status' => 'sampah']))->assertSee($exam->title);
        $this->post(route('admin.exams.bulk'), ['action' => 'restore', 'ids' => [$exam->id]])->assertRedirect();
        $this->assertNotSoftDeleted($exam);
        $this->actingAs($this->mech)->get(route('exams.result', $attempt))->assertOk();
    }

    public function test_exam_with_live_participants_cannot_be_trashed(): void
    {
        $exam = $this->exam('open');
        $this->attempt($exam, 'in_progress');

        $this->actingAs($this->admin)->delete(route('admin.exams.destroy', $exam))->assertSessionHas('toast');
        $this->assertNotSoftDeleted($exam);

        $this->post(route('admin.exams.bulk'), ['action' => 'trash', 'ids' => [$exam->id]]);
        $this->assertNotSoftDeleted($exam);
    }

    public function test_permanent_delete_requires_typed_confirmation(): void
    {
        $exam = $this->exam('closed');
        $this->attempt($exam);
        $exam->delete();

        $this->actingAs($this->admin)->post(route('admin.exams.bulk'), ['action' => 'purge', 'ids' => [$exam->id], 'confirm' => 'ya']);
        $this->assertSoftDeleted($exam);

        $this->post(route('admin.exams.bulk'), ['action' => 'purge', 'ids' => [$exam->id], 'confirm' => 'hapus']);
        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
        $this->assertDatabaseCount('exam_attempts', 0);
        $this->assertDatabaseCount('exam_participants', 0);
    }

    public function test_purge_only_touches_trashed_exams(): void
    {
        $live = $this->exam('closed');
        $this->actingAs($this->admin)->post(route('admin.exams.bulk'), ['action' => 'purge', 'ids' => [$live->id], 'confirm' => 'HAPUS']);
        $this->assertDatabaseHas('exams', ['id' => $live->id]);

        $trashed = $this->exam('draft');
        $trashed->delete();
        $this->post(route('admin.exams.bulk'), ['action' => 'empty-trash', 'confirm' => 'HAPUS']);
        $this->assertDatabaseMissing('exams', ['id' => $trashed->id]);
        $this->assertDatabaseHas('exams', ['id' => $live->id]);
    }

    public function test_bulk_archive_skips_running_exams(): void
    {
        $closed = $this->exam('closed');
        $draft = $this->exam('draft');
        $open = $this->exam('open');

        $this->actingAs($this->admin)->post(route('admin.exams.bulk'), ['action' => 'archive', 'ids' => [$closed->id, $draft->id, $open->id]])
            ->assertSessionHas('toast', fn ($t) => str_contains($t, '2 event diarsipkan') && str_contains($t, 'Dilewati'));
        $this->assertNotNull($closed->fresh()->archived_at);
        $this->assertNull($open->fresh()->archived_at);

        $this->get(route('admin.exams.index'))->assertDontSee($closed->title);
        $this->get(route('admin.exams.index', ['status' => 'arsip']))->assertSee($closed->title);

        $this->post(route('admin.exams.archive', $closed));
        $this->assertNull($closed->fresh()->archived_at);
    }

    public function test_trash_is_purged_after_retention(): void
    {
        $old = $this->exam('draft');
        $old->delete();
        Exam::withTrashed()->whereKey($old->id)->update(['deleted_at' => now()->subDays(Exam::TRASH_DAYS + 1)]);
        $recent = $this->exam('closed');
        $recent->delete();

        $this->assertSame(1, app(ExamService::class)->purgeTrash());
        $this->assertDatabaseMissing('exams', ['id' => $old->id]);
        $this->assertSoftDeleted($recent);
    }

    public function test_category_is_saved_and_code_of_trashed_exam_stays_reserved(): void
    {
        $trashed = $this->exam('draft');
        $trashed->delete();

        $payload = ['title' => 'Pre-test Engine', 'category' => 'Pre-test', 'code' => $trashed->code, 'audience' => 'open', 'duration_minutes' => 30,
            'pass_score' => 70, 'max_attempts' => 1, 'max_violations' => 3, 'result_mode' => 'score', 'sets' => [['id' => $this->set->id]]];
        $this->actingAs($this->admin)->post(route('admin.exams.store'), $payload)->assertSessionHasErrors('code');

        $this->post(route('admin.exams.store'), ['code' => 'PRE-ENG1'] + $payload)->assertRedirect();
        $this->assertSame('Pre-test', Exam::where('code', 'PRE-ENG1')->value('category'));
    }
}
