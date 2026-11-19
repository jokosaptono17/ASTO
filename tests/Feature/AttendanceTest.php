<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AttendanceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $gl;

    private User $mech;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gl = User::factory()->activated()->create(['nrp' => 'KT16018', 'name' => 'ARIF SETIAWAN', 'role' => 'gl', 'department' => 'PLANT']);
        $this->mech = User::factory()->activated()->create(['nrp' => 'KT16042', 'name' => 'IKHSANUDIN APRIYANTO', 'department' => 'SERVICE REPAIR RM', 'position' => 'MEKANIK M9']);
        $this->other = User::factory()->activated()->create(['nrp' => 'KT17163', 'name' => 'ABD ROHMAN YAHYA', 'department' => 'SERVICE REPAIR RM']);
    }

    private function makeSession(array $attrs = []): AttendanceSession
    {
        $s = new AttendanceSession(array_merge([
            'attendance_type_id' => AttendanceType::firstWhere('name', 'P5M')->id,
            'title' => 'Pinch point',
            'date' => today(),
            'starts_at' => now()->subMinutes(2),
            'opens_at' => now()->subMinutes(20),
            'closes_at' => now()->addMinutes(40),
            'late_after' => 10,
            'location' => 'WS 25',
            'company' => 'PT KPP MINING',
            'target_departments' => ['SERVICE REPAIR RM'],
            'checkin_mode' => 'qr',
            'host_id' => $this->gl->id,
        ], $attrs));
        $s->created_by = $this->gl->id;
        $s->save();

        return $s;
    }

    public function test_gl_creates_session_but_cannot_open_other_admin_pages(): void
    {
        $this->actingAs($this->gl)->get('/admin')->assertForbidden();
        $this->actingAs($this->gl)->get('/admin/absensi/baru')->assertOk();
        $this->actingAs($this->gl)->post('/admin/absensi', [
            'attendance_type_id' => AttendanceType::firstWhere('name', 'Safety Talk')->id,
            'title' => 'Bahaya pinch point', 'date' => today()->toDateString(), 'start_time' => now()->addMinutes(10)->format('H:i'),
            'shift' => '1', 'open_before' => 15, 'open_after' => 45, 'late_after' => 10, 'location' => 'WS 25',
            'target_departments' => ['SERVICE REPAIR RM'], 'checkin_mode' => 'qr', 'speaker_nrp' => 'kt16018',
        ])->assertSessionHasNoErrors();
        $s = AttendanceSession::firstWhere('title', 'Bahaya pinch point');
        $this->assertSame('ARIF SETIAWAN', $s->speaker_name);
        $this->assertSame($this->gl->id, $s->host_id);
        $this->assertSame(8, strlen($s->code));
        $this->actingAs($this->gl)->get("/admin/absensi/{$s->id}")->assertOk()->assertSee('Layar QR');
        $this->actingAs($this->gl)->get("/admin/absensi/{$s->id}/layar")->assertOk();
        $this->actingAs($this->mech)->get('/admin/absensi')->assertForbidden();
    }

    public function test_mechanic_checks_in_by_scanning_rotating_qr(): void
    {
        $s = $this->makeSession();
        $this->actingAs($this->mech)->get("/absen/{$s->code}?t={$s->token()}")->assertOk()->assertSee('Cek fatigue')->assertSee('KT16042');
        $this->actingAs($this->mech)->post("/absen/{$s->code}", ['role' => 'audience', 'sleep_at' => '22:30', 'wake_at' => '03:45'])
            ->assertRedirect("/absen/{$s->code}");

        $r = AttendanceRecord::firstWhere('nrp', 'KT16042');
        $this->assertSame(315, $r->sleep_minutes);
        $this->assertSame('coaching', $r->fatigue);
        $this->assertFalse($r->is_late);
        $this->assertSame('qr', $r->method);
        $this->assertSame('SERVICE REPAIR RM', $r->department);

        // Tidak bisa dobel
        $this->actingAs($this->mech)->post("/absen/{$s->code}", ['role' => 'audience', 'sleep_at' => '22:00', 'wake_at' => '05:00']);
        $this->assertSame(1, AttendanceRecord::count());
        $this->actingAs($this->mech)->get("/absen/{$s->code}")->assertSee('Anda sudah absen')->assertSee('Coaching Atasan');
    }

    public function test_expired_or_missing_token_requires_new_scan(): void
    {
        $s = $this->makeSession();
        $old = $s->token(intdiv(now()->timestamp, 30) - 10);
        $this->actingAs($this->mech)->get("/absen/{$s->code}?t={$old}")->assertSee('Scan QR di lokasi');
        $this->actingAs($this->mech)->post("/absen/{$s->code}", ['role' => 'audience', 'sleep_at' => '22:00', 'wake_at' => '05:00'])
            ->assertSessionHasErrors('token');
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_typing_screen_code_works_and_late_is_flagged(): void
    {
        $s = $this->makeSession(['starts_at' => now()->subMinutes(30)]);
        $this->actingAs($this->mech)->post('/absen/kode', ['token' => '000000'])->assertSessionHasErrors('token');
        $this->actingAs($this->mech)->post('/absen/kode', ['token' => $s->token()])->assertRedirect("/absen/{$s->code}");
        $this->actingAs($this->mech)->post("/absen/{$s->code}", ['role' => 'pemateri', 'sleep_at' => '21:00', 'wake_at' => '01:30']);
        $r = AttendanceRecord::first();
        $this->assertTrue($r->is_late);
        $this->assertSame('risk', $r->fatigue);
        $this->assertSame('kode', $r->method);
        $this->assertSame('pemateri', $r->role);
    }

    public function test_guest_scan_is_remembered_through_login(): void
    {
        $s = $this->makeSession();
        $this->get("/absen/{$s->code}?t={$s->token()}")->assertRedirect('/masuk');
        $this->actingAs($this->mech)->get("/absen/{$s->code}")->assertSee('Cek fatigue');
    }

    public function test_closed_scheduled_and_open_mode_sessions(): void
    {
        $closed = $this->makeSession(['closes_at' => now()->subMinute()]);
        $this->actingAs($this->mech)->get("/absen/{$closed->code}?t={$closed->token()}")->assertSee('sudah ditutup');
        $later = $this->makeSession(['opens_at' => now()->addHour(), 'starts_at' => now()->addHours(2), 'closes_at' => now()->addHours(3)]);
        $this->actingAs($this->mech)->get("/absen/{$later->code}")->assertSee('belum dibuka');

        $open = $this->makeSession(['checkin_mode' => 'open', 'title' => 'Briefing terbuka']);
        $this->actingAs($this->mech)->get('/')->assertSee('Briefing terbuka')->assertSee('Absen');
        $this->actingAs($this->mech)->post("/absen/{$open->code}", ['role' => 'audience', 'sleep_at' => '21:00', 'wake_at' => '05:00'])->assertSessionHasNoErrors();
        $this->assertSame('open', AttendanceRecord::first()->method);
        $this->assertSame('fit', AttendanceRecord::first()->fatigue);
    }

    public function test_host_live_feed_manual_add_remove_and_export(): void
    {
        $s = $this->makeSession();
        $feed = $this->actingAs($this->gl)->getJson("/admin/absensi/{$s->id}/feed")->assertOk();
        $this->assertSame(2, $feed->json('counts.expected'));
        $this->assertSame(6, strlen($feed->json('token')));

        $this->actingAs($this->gl)->postJson("/admin/absensi/{$s->id}/hadir", ['nrp' => 'kt17163'])->assertOk();
        $this->actingAs($this->gl)->postJson("/admin/absensi/{$s->id}/hadir", ['nrp' => 'XX00000'])->assertStatus(422);
        $feed = $this->actingAs($this->gl)->getJson("/admin/absensi/{$s->id}/feed");
        $this->assertSame(1, $feed->json('counts.present'));
        $this->assertSame('KT16042', $feed->json('missing.0.nrp'));
        $this->assertSame('Dicatat host', $feed->json('records.0.method'));

        $this->actingAs($this->gl)->get("/admin/absensi/{$s->id}/ekspor")->assertOk();
        $rec = AttendanceRecord::first();
        $this->actingAs($this->gl)->delete("/admin/absensi/{$s->id}/hadir/{$rec->id}")->assertRedirect();
        $this->assertSame(0, AttendanceRecord::count());

        $this->actingAs($this->gl)->post("/admin/absensi/{$s->id}/tutup");
        $this->assertSame('selesai', $s->fresh()->status());
        $this->actingAs($this->gl)->post("/admin/absensi/{$s->id}/tutup", ['reopen' => 1]);
        $this->assertTrue($s->fresh()->isOpen());
    }

    public function test_gl_cannot_manage_other_hosts_session_but_admin_can(): void
    {
        $s = $this->makeSession();
        $gl2 = User::factory()->activated()->create(['role' => 'gl']);
        $this->actingAs($gl2)->get("/admin/absensi/{$s->id}")->assertForbidden();
        $admin = User::factory()->admin()->activated()->create();
        $this->actingAs($admin)->get("/admin/absensi/{$s->id}")->assertOk();
        $this->actingAs($gl2)->get('/admin/absensi/jenis')->assertForbidden();
        $this->actingAs($admin)->get('/admin/absensi/jenis')->assertOk();
    }

    public function test_recap_history_and_mentari(): void
    {
        $s = $this->makeSession();
        $this->actingAs($this->mech)->get("/absen/{$s->code}?t={$s->token()}");
        $this->actingAs($this->mech)->post("/absen/{$s->code}", ['role' => 'audience', 'sleep_at' => '23:30', 'wake_at' => '04:00']);

        $this->actingAs($this->gl)->get('/admin/absensi/rekap?fatigue=risk')->assertOk()->assertSee('Ikhsanudin Apriyanto');
        $this->actingAs($this->gl)->get('/admin/absensi/rekap/ekspor')->assertOk();
        $this->actingAs($this->mech)->get('/absensi-saya')->assertOk()->assertSee('Pinch point');

        $mine = $this->actingAs($this->mech)->postJson('/mentari/chat', ['message' => 'absensi saya'])->assertOk();
        $this->assertSame('attendance', $mine->json('intent'));
        $this->assertSame(1, $mine->json('cards.0.items.0.value'));

        $missing = $this->actingAs($this->gl)->postJson('/mentari/chat', ['message' => 'siapa yang belum absen?'])->assertOk();
        $this->assertSame('KT17163', $missing->json('cards.0.items.0.ask'));
    }

    public function test_sleep_classification(): void
    {
        $this->assertSame([360, 'fit'], AttendanceRecord::classifySleep('23:00', '05:00'));
        $this->assertSame([300, 'coaching'], AttendanceRecord::classifySleep('00:00', '05:00'));
        $this->assertSame([299, 'risk'], AttendanceRecord::classifySleep('00:01', '05:00'));
        $this->assertNull(AttendanceRecord::classifySleep(null, '05:00'));
    }
}
