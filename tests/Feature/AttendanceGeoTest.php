<?php

namespace Tests\Feature;

use App\Models\AttendanceLocation;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\Geo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AttendanceGeoTest extends TestCase
{
    use RefreshDatabase;

    /** Kotak ±100 × 100 m */
    private const SQUARE = [[-3.7500, 114.8000], [-3.7500, 114.8009], [-3.7509, 114.8009], [-3.7509, 114.8000]];

    private const INSIDE = ['lat' => -3.75045, 'lng' => 114.80045, 'acc' => 8];

    private User $mech;

    private User $other;

    private AttendanceLocation $ws;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mech = User::factory()->activated()->create(['nrp' => 'KT16042', 'name' => 'IKHSANUDIN APRIYANTO', 'department' => 'SERVICE REPAIR RM']);
        $this->other = User::factory()->activated()->create(['nrp' => 'KT17163', 'name' => 'ABD ROHMAN YAHYA', 'department' => 'SERVICE REPAIR RM']);
        $this->ws = AttendanceLocation::firstWhere('name', 'WS 25');
        $this->ws->setPolygon(self::SQUARE);
        $this->ws->save();
    }

    private function goTo(string $at): void
    {
        $this->travelTo(now()->parse($at));
        Cache::flush();
    }

    /** Sesi P5M rutin yang sedang dibuka (Sabtu 06:10). */
    private function p5m(): AttendanceSession
    {
        $this->goTo('2026-11-21 06:10');
        AttendanceSchedule::ensureSessions();

        return AttendanceSession::whereHas('type', fn ($q) => $q->where('name', 'P5M'))->where('shift', '1')->firstOrFail();
    }

    public function test_geofence_math(): void
    {
        $this->assertTrue(Geo::contains(-3.75045, 114.80045, self::SQUARE));
        $this->assertFalse(Geo::contains(-3.7545, 114.80045, self::SQUARE));
        $this->assertEqualsWithDelta(400, Geo::distanceToPolygon(-3.7545, 114.80045, self::SQUARE), 5);
        $this->assertEqualsWithDelta(10000, Geo::area(self::SQUARE), 300);
        $this->assertSame(0.0, Geo::distanceToPolygon(-3.75045, 114.80045, self::SQUARE));
    }

    public function test_routine_sessions_open_automatically_by_schedule(): void
    {
        $this->goTo('2026-11-20 06:10');   // Jumat
        AttendanceSchedule::ensureSessions();
        $this->assertSame(['P5M'], AttendanceSession::with('type')->get()->map(fn ($s) => $s->type->name)->unique()->values()->all());
        $this->assertSame(2, AttendanceSession::count());   // shift 1 (dibuka) + shift 2 (terjadwal)

        $this->goTo('2026-11-21 06:10');   // Sabtu: + Safety Talk
        $this->actingAs($this->mech)->get('/absen')->assertOk()->assertSee('Safety Talk')->assertSee('P5M');
        $this->assertSame(5, AttendanceSession::count());

        $st = AttendanceSession::whereHas('type', fn ($q) => $q->where('name', 'Safety Talk'))->first();
        $this->assertSame('06:00', $st->opens_at->format('H:i'));
        $this->assertSame('08:00', $st->closes_at->format('H:i'));
        $this->assertSame('gps', $st->checkin_mode);
        $this->assertNull($st->late_after);

        // Dijalankan lagi tidak membuat duplikat
        AttendanceSchedule::ensureSessions(true);
        $this->assertSame(5, AttendanceSession::count());
    }

    public function test_check_in_inside_area_is_verified(): void
    {
        $s = $this->p5m();
        $this->actingAs($this->mech)->get("/absen/{$s->code}")->assertOk()->assertSee('Mengecek lokasi');
        $this->actingAs($this->mech)->post("/absen/{$s->code}", ['role' => 'audience', 'sleep_at' => '21:30', 'wake_at' => '04:00'] + self::INSIDE)
            ->assertSessionHasNoErrors();
        $r = AttendanceRecord::first();
        $this->assertSame('inside', $r->geo_status);
        $this->assertSame($this->ws->id, $r->attendance_location_id);
        $this->assertSame('gps', $r->method);
        $this->assertFalse($r->is_late);
        $this->actingAs($this->mech)->get("/absen/{$s->code}")->assertSee('WS 25');
    }

    public function test_check_in_outside_area_is_rejected(): void
    {
        $s = $this->p5m();
        $this->actingAs($this->mech)->from("/absen/{$s->code}")
            ->post("/absen/{$s->code}", ['role' => 'audience', 'sleep_at' => '21:30', 'wake_at' => '04:00', 'lat' => -3.7545, 'lng' => 114.80045, 'acc' => 10])
            ->assertSessionHasErrors('geo');
        // GPS kasar tapi jelas jauh → tetap ditolak
        $this->actingAs($this->mech)->post("/absen/{$s->code}", ['role' => 'audience', 'sleep_at' => '21:30', 'wake_at' => '04:00', 'lat' => -3.7545, 'lng' => 114.80045, 'acc' => 200])
            ->assertSessionHasErrors('geo');
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_missing_or_weak_gps_is_allowed_but_flagged(): void
    {
        $s = $this->p5m();
        $this->actingAs($this->mech)->post("/absen/{$s->code}", ['role' => 'audience', 'sleep_at' => '21:30', 'wake_at' => '04:00'])->assertSessionHasNoErrors();
        $this->assertSame('unverified', AttendanceRecord::firstWhere('nrp', 'KT16042')->geo_status);

        // ±60 m dari area dengan akurasi ±120 m → belum pasti
        $this->actingAs($this->other)->post("/absen/{$s->code}", ['role' => 'audience', 'sleep_at' => '21:30', 'wake_at' => '04:00', 'lat' => -3.74946, 'lng' => 114.80045, 'acc' => 120]);
        $r = AttendanceRecord::firstWhere('nrp', 'KT17163');
        $this->assertSame('unverified', $r->geo_status);
        $this->assertEqualsWithDelta(60, $r->distance_m, 3);

        $feed = $this->actingAs(User::factory()->admin()->activated()->create())->getJson("/admin/absensi/{$s->id}/feed");
        $this->assertSame(2, $feed->json('counts.unverified'));
    }

    public function test_one_phone_cannot_check_in_two_employees(): void
    {
        $s = $this->p5m();
        $phone = '6f1c1d2e-3b4a-4c5d-8e9f-0a1b2c3d4e5f';
        $this->withCookie('astoone_device', $phone)->actingAs($this->mech)
            ->post("/absen/{$s->code}", ['role' => 'audience', 'sleep_at' => '21:30', 'wake_at' => '04:00'] + self::INSIDE)->assertSessionHasNoErrors();
        $this->withCookie('astoone_device', $phone)->actingAs($this->other)
            ->post("/absen/{$s->code}", ['role' => 'audience', 'sleep_at' => '21:30', 'wake_at' => '04:00'] + self::INSIDE)
            ->assertSessionHasErrors('device');
        $this->assertSame(1, AttendanceRecord::count());
        $this->assertSame($phone, AttendanceRecord::first()->device_id);
    }

    public function test_locate_endpoint_reports_area_and_sessions(): void
    {
        $s = $this->p5m();
        $in = $this->actingAs($this->mech)->getJson('/absen/lokasi?'.http_build_query(self::INSIDE))->assertOk();
        $this->assertSame('inside', $in->json('status'));
        $this->assertSame('WS 25', $in->json('location'));
        $this->assertContains($s->code, collect($in->json('sessions'))->pluck('code')->all());

        $out = $this->actingAs($this->mech)->getJson("/absen/lokasi?code={$s->code}&lat=-3.7545&lng=114.80045&acc=10");
        $this->assertSame('outside', $out->json('status'));
        $this->assertStringContainsString('di luar area WS 25', $out->json('message'));
    }

    public function test_admin_maps_area_by_walking_and_gl_cannot(): void
    {
        $admin = User::factory()->admin()->activated()->create();
        $gl = User::factory()->activated()->create(['role' => 'gl']);
        $quarry = AttendanceLocation::firstWhere('name', 'Quarry');

        $this->actingAs($gl)->get('/admin/absensi/lokasi')->assertForbidden();
        $this->actingAs($admin)->get('/admin/absensi/lokasi')->assertOk()->assertSee('Belum dipetakan');
        $this->actingAs($admin)->get("/admin/absensi/lokasi/{$quarry->id}")->assertOk()->assertSee('Tandai titik di sini');
        $this->actingAs($admin)->put("/admin/absensi/lokasi/{$quarry->id}", ['name' => 'Quarry', 'buffer_m' => 30, 'is_active' => 1, 'polygon' => json_encode([[-3.76, 114.81], [-3.76, 114.811]])])
            ->assertSessionHasErrors('polygon');
        $this->actingAs($admin)->put("/admin/absensi/lokasi/{$quarry->id}", ['name' => 'Quarry', 'buffer_m' => 30, 'is_active' => 1, 'polygon' => json_encode([[-3.76, 114.81], [-3.76, 114.811], [-3.761, 114.811]])])
            ->assertRedirect('/admin/absensi/lokasi');
        $quarry->refresh();
        $this->assertTrue($quarry->isMapped());
        $this->assertSame(30, $quarry->buffer_m);
        $this->assertGreaterThan(5000, $quarry->area_m2);
        $this->assertSame($admin->id, $quarry->mapped_by);
    }

    public function test_schedule_admin_and_gl_monitor_routine_session(): void
    {
        $s = $this->p5m();
        $admin = User::factory()->admin()->activated()->create();
        $gl = User::factory()->activated()->create(['role' => 'gl']);

        $this->actingAs($admin)->get('/admin/absensi/jadwal')->assertOk()->assertSee('Shift 1')->assertSee('Rutin Sabtu');
        $this->actingAs($gl)->get('/admin/absensi/jadwal')->assertForbidden();
        $this->actingAs($admin)->put("/admin/absensi/jadwal/{$s->attendance_schedule_id}", [
            'attendance_type_id' => $s->attendance_type_id, 'title' => 'Shift 1', 'shift' => '1', 'days' => [0, 1, 2, 3, 4, 5, 6],
            'open_time' => '05:30', 'close_time' => '07:30', 'late_after' => 30, 'location_ids' => [$this->ws->id], 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $s->refresh();
        $this->assertSame('07:30', $s->closes_at->format('H:i'));
        $this->assertSame(30, $s->late_after);
        $this->assertSame([$this->ws->id], $s->location_ids);

        // GL memantau sesi rutin, mencatat manual, tapi tidak mengubah/menghapus
        $this->actingAs($gl)->get('/admin/absensi')->assertOk()->assertSee('P5M');
        $this->actingAs($gl)->get("/admin/absensi/{$s->id}")->assertOk();
        $this->actingAs($gl)->postJson("/admin/absensi/{$s->id}/hadir", ['nrp' => 'KT17163'])->assertOk();
        $this->actingAs($gl)->get("/admin/absensi/{$s->id}/ubah")->assertRedirect("/admin/absensi/{$s->id}");
        $this->actingAs($gl)->delete("/admin/absensi/{$s->id}")->assertForbidden();

        // Jadwal dinonaktifkan: sesi hari ini yang belum ada pesertanya ikut hilang
        $shift2 = AttendanceSession::where('shift', '2')->whereNotNull('attendance_schedule_id')->first();
        $this->actingAs($admin)->delete("/admin/absensi/jadwal/{$shift2->attendance_schedule_id}");
        $this->assertNull($shift2->fresh());
    }

    public function test_poster_and_permissions_policy(): void
    {
        $gl = User::factory()->activated()->create(['role' => 'gl']);
        $this->actingAs($gl)->get('/admin/absensi/poster-qr?lokasi=WS+25')->assertOk()->assertSee('ABSENSI')->assertSee('WS 25');
        $this->actingAs($this->mech)->get('/absen')->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self)');
    }
}
