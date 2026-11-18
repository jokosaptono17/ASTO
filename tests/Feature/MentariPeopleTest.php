<?php

namespace Tests\Feature;

use App\Models\AppLink;
use App\Models\User;
use App\Services\Spreadsheet;
use App\Services\UserImporter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MentariPeopleTest extends TestCase
{
    use RefreshDatabase;

    private User $gl;

    private User $mech;

    private User $other;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->gl = User::factory()->activated()->create(['nrp' => 'KT16018', 'name' => 'ARIF SETIAWAN', 'position' => 'PLANT GL']);
        $this->mech = User::factory()->activated()->create(['nrp' => 'KT16042', 'name' => 'IKHSANUDIN APRIYANTO', 'department' => 'SERVICE REPAIR RM', 'grade' => 'M9', 'supervisor' => 'ARIF SETIYAWAN', 'location' => 'WS25(SRM)', 'program' => 'IDP', 'phone' => '081200000000']);
        $this->mech->resolveSupervisor();
        $this->mech->save();
        $this->other = User::factory()->activated()->create(['nrp' => 'KT17163', 'name' => 'ABD ROHMAN YAHYA']);
        $this->admin = User::factory()->admin()->activated()->create(['nrp' => 'KT16026', 'name' => 'JOKO SAPTONO']);
    }

    private function ask(User $as, string $message)
    {
        return $this->actingAs($as)->postJson('/mentari/chat', ['message' => $message])->assertOk();
    }

    public function test_supervisor_name_is_matched_despite_spelling(): void
    {
        $this->assertSame('KT16018', $this->mech->supervisor_nrp);
    }

    public function test_nrp_lookup_shows_basic_card_to_colleagues(): void
    {
        $res = $this->ask($this->other, 'KT16042');
        $card = $res->json('cards.0');
        $this->assertSame('person', $card['type']);
        $this->assertSame('KT16042', $card['nrp']);
        $this->assertContains('M9', array_column($card['fields'], 'value'));
        $this->assertContains('Arif Setiyawan', array_column($card['fields'], 'value'));
        $this->assertArrayNotHasKey('admin', $card, 'No HP & status akun hanya untuk admin');
        $this->assertStringNotContainsString('0812', json_encode($res->json()));

        $this->ask($this->other, 'siapa KT99999')->assertJsonPath('cards', [])
            ->assertJson(fn ($j) => $j->where('reply', fn ($r) => str_contains($r, 'tidak ditemukan'))->etc());
    }

    public function test_admin_sees_account_details_and_actions(): void
    {
        $res = $this->ask($this->admin, 'data kt16042');
        $this->assertSame('aktif', $res->json('cards.0.status'));
        $this->assertContains('081200000000', array_column($res->json('cards.0.admin'), 'value'));
        $this->assertContains('Kelola akun', array_column($res->json('actions'), 'label'));
    }

    public function test_search_by_name_and_self_and_mentees(): void
    {
        $this->ask($this->other, 'siapa ikhsanudin')->assertJsonPath('cards.0.nrp', 'KT16042');
        $this->ask($this->mech, 'siapa gl wali saya')->assertJson(fn ($j) => $j->where('reply', fn ($r) => str_contains($r, 'Arif Setiyawan'))->etc());
        $this->ask($this->gl, 'mekanik asuh saya')->assertJsonPath('cards.0.items.0.ask', 'KT16042');
        $this->ask($this->other, 'anak asuh KT16018')->assertJsonPath('cards', []);
    }

    public function test_app_knowledge(): void
    {
        AppLink::where('name', 'Monata')->update(['howto' => '1. Buka Monata 2. Login dengan NRP', 'pic_name' => 'Fawzi', 'pic_phone' => '081311112222']);

        $what = $this->ask($this->other, 'apa itu monata?');
        $this->assertStringContainsString('tyre', $what->json('reply'));
        $this->assertSame('Cara pakai', $what->json('actions.1.label'));

        $this->ask($this->other, 'cara login monata')->assertJson(fn ($j) => $j->where('reply', fn ($r) => str_contains($r, 'Login dengan NRP'))->etc());
        $pic = $this->ask($this->other, 'siapa admin monata');
        $this->assertStringContainsString('Fawzi', $pic->json('reply'));
        $this->assertStringContainsString('wa.me/6281311112222', $pic->json('actions.0.href'));
    }

    public function test_admin_stats_are_admin_only(): void
    {
        User::factory()->create(['nrp' => 'KT50001', 'department' => 'BUSER']);
        $res = $this->ask($this->admin, 'siapa yang belum aktivasi di buser?');
        $this->assertSame('stats', $res->json('cards.0.type'));
        $this->assertSame('KT50001', $res->json('cards.1.items.0.ask'));

        $this->ask($this->other, 'siapa yang belum aktivasi')->assertJsonPath('cards', []);
        $this->ask($this->admin, 'statistik portal')->assertJsonPath('cards.0.type', 'stats');
    }

    public function test_training_history_from_lms_with_permissions(): void
    {
        config([
            'database.connections.lms' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'username' => 'ro'],
            'database.connections.sertifikat' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'username' => 'ro'],
        ]);
        $lms = Schema::connection('lms');
        $lms->create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('nrp')->nullable(); $t->string('grade')->nullable(); $t->unsignedInteger('points')->default(0); $t->string('section')->nullable(); });
        $lms->create('training_names', function (Blueprint $t) { $t->id(); $t->string('code'); $t->string('name'); });
        $lms->create('trainings', function (Blueprint $t) { $t->id(); $t->string('title')->nullable(); $t->unsignedBigInteger('training_name_id')->nullable(); $t->dateTime('start_date')->nullable(); });
        $lms->create('training_registrations', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('training_id'); $t->unsignedBigInteger('user_id'); $t->string('status'); $t->boolean('is_passed')->nullable(); });
        $lms->create('user_historical_trainings', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('user_id'); $t->unsignedBigInteger('training_name_id'); $t->date('training_date'); });
        $lms->create('achievements', function (Blueprint $t) { $t->id(); $t->string('name'); });
        $lms->create('achievement_user', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('user_id'); $t->unsignedBigInteger('achievement_id'); });
        Schema::connection('sertifikat')->create('sertifikat', function (Blueprint $t) { $t->id(); $t->string('no_seri'); $t->string('nrp'); $t->string('nama_training'); $t->dateTime('issued_at')->nullable(); $t->boolean('is_valid')->default(true); });

        $db = DB::connection('lms');
        $db->table('users')->insert(['id' => 7, 'name' => 'IKHSANUDIN', 'nrp' => 'KT16042', 'grade' => 'M9', 'points' => 1250]);
        $db->table('training_names')->insert([['id' => 1, 'code' => 'COH', 'name' => 'COH Differential Scania'], ['id' => 2, 'code' => 'BAS', 'name' => 'Basic Hydraulic']]);
        $db->table('trainings')->insert(['id' => 3, 'title' => 'COH DIFF', 'training_name_id' => 1, 'start_date' => '2026-10-01 08:00:00']);
        $db->table('training_registrations')->insert(['training_id' => 3, 'user_id' => 7, 'status' => 'attended', 'is_passed' => true]);
        $db->table('user_historical_trainings')->insert(['user_id' => 7, 'training_name_id' => 2, 'training_date' => '2024-03-05']);
        $db->table('achievements')->insert(['id' => 1, 'name' => 'Mekanik Teladan']);
        $db->table('achievement_user')->insert(['user_id' => 7, 'achievement_id' => 1]);
        DB::connection('sertifikat')->table('sertifikat')->insert(['no_seri' => 'ASTO/2026/001', 'nrp' => 'KT16042', 'nama_training' => 'COH Differential Scania', 'issued_at' => '2026-10-03']);

        // Diri sendiri
        $res = $this->ask($this->mech, 'training saya');
        $this->assertSame([1250, 2, 1], array_column($res->json('cards.0.items'), 'value'));
        $this->assertSame('COH Differential Scania', $res->json('cards.1.items.0.title'));
        $this->assertSame('ASTO/2026/001 · 3 Okt 2026', $res->json('cards.2.items.0.subtitle'));

        // GL wali boleh, rekan biasa tidak
        $this->ask($this->gl, 'training KT16042')->assertJsonPath('cards.0.type', 'stats');
        $this->ask($this->other, 'training KT16042')->assertJsonPath('cards.0.type', 'person')
            ->assertJson(fn ($j) => $j->where('reply', fn ($r) => str_contains($r, 'hanya bisa dilihat'))->etc());

        // Kartu orang menampilkan ringkasan LMS bagi yang berhak
        $this->ask($this->gl, 'KT16042')->assertJsonPath('cards.0.lms.points', 1250);
        $this->assertNull($this->ask($this->other, 'KT16042')->json('cards.0.lms'));

        $this->actingAs($this->mech)->get('/profil')->assertSee('Poin LMS')->assertSee('1.250');
    }

    public function test_training_without_lms_is_explained(): void
    {
        $this->ask($this->mech, 'training saya')->assertJson(fn ($j) => $j->where('reply', fn ($r) => str_contains($r, 'belum tersambung'))->etc());
    }

    public function test_import_reads_detail_columns(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        Spreadsheet::writeXlsx($path, [
            ['NRP', 'NAMA', 'DEPARTEMEN', 'GRADE26', 'GL WALI NEW', 'LOKASI', 'PROGRAM'],
            ['KT17164', 'ANDI RISTIAWAN', 'REPAIR HAULING', '6', 'ARIF SETIYAWAN', 'WS25(RH)', 'IDP'],
        ]);
        $result = app(UserImporter::class)->import(Spreadsheet::read($path, 'xlsx'));
        $this->assertSame(1, $result['created']);
        $u = User::firstWhere('nrp', 'KT17164');
        $this->assertSame(['M6', 'ARIF SETIYAWAN', 'KT16018', 'WS25(RH)', 'IDP'], [$u->grade, $u->supervisor, $u->supervisor_nrp, $u->location, $u->program]);
    }
}
