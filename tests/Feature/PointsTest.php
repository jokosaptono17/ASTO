<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AttendanceType;
use App\Models\PointGrant;
use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use App\Services\Points\PointException;
use App\Services\Points\PointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PointsTest extends TestCase
{
    use RefreshDatabase;

    private User $mech;

    private User $admin;

    private PointService $points;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->mech = User::factory()->activated('739164')->create(['nrp' => 'KT16042', 'name' => 'IKHSANUDIN APRIYANTO', 'department' => 'SERVICE REPAIR RM']);
        $this->admin = User::factory()->admin()->activated()->create(['nrp' => 'KT12345']);
        $this->points = app(PointService::class);
    }

    private function makeSession(): AttendanceSession
    {
        $s = new AttendanceSession([
            'attendance_type_id' => AttendanceType::firstWhere('name', 'P5M')->id, 'title' => 'Pinch point', 'date' => today(),
            'starts_at' => now()->subMinutes(2), 'opens_at' => now()->subMinutes(20), 'closes_at' => now()->addMinutes(40), 'checkin_mode' => 'open',
        ]);
        $s->save();

        return $s;
    }

    public function test_attendance_awards_points_once_and_host_removal_revokes(): void
    {
        $s = $this->makeSession();
        $this->actingAs($this->mech)->post("/absen/{$s->code}", ['role' => 'pemateri', 'sleep_at' => '22:00', 'wake_at' => '02:00']);
        $this->mech->refresh();
        $this->assertSame(25, $this->mech->points_balance);   // hadir 5 + pemateri 20 (fatigue risk tidak mengurangi poin)
        $this->assertSame(2, PointTransaction::count());

        // Absen ulang ke sesi yang sama tidak menambah poin
        $this->actingAs($this->mech)->post("/absen/{$s->code}", ['role' => 'audience', 'sleep_at' => '22:00', 'wake_at' => '05:00']);
        $this->assertSame(25, $this->mech->fresh()->points_balance);

        $rec = AttendanceRecord::first();
        $this->actingAs($this->admin)->delete("/admin/absensi/{$s->id}/hadir/{$rec->id}");
        $this->assertSame(0, $this->mech->fresh()->points_balance);
        $this->assertSame(25, $this->mech->fresh()->points_earned);   // riwayat seumur hidup tidak dihapus
    }

    public function test_streak_bonus_and_rule_toggle(): void
    {
        PointRule::find('attend')->update(['is_active' => false]);
        for ($d = 6; $d >= 1; $d--) {
            AttendanceRecord::forceCreate(['attendance_session_id' => $this->makeSession()->id, 'user_id' => $this->mech->id, 'nrp' => 'KT16042', 'name' => 'X', 'checked_in_at' => now()->subDays($d), 'role' => 'audience', 'method' => 'open']);
        }
        $s = $this->makeSession();
        $this->actingAs($this->mech)->post("/absen/{$s->code}", ['role' => 'audience', 'sleep_at' => '22:00', 'wake_at' => '05:00']);
        $this->assertSame(25, $this->mech->fresh()->points_balance);
        $this->assertSame('streak7', PointTransaction::first()->rule);
    }

    public function test_redeem_with_pin_creates_coupon_and_admin_fulfils_or_cancels(): void
    {
        $this->points->post($this->mech, 300, 'adjust', 'Saldo awal');
        $reward = Reward::forceCreate(['name' => 'Tumbler ASTO', 'cost' => 120, 'stock' => 2, 'per_user_limit' => 1, 'is_active' => true, 'category' => 'merchandise']);

        $this->actingAs($this->mech)->get('/poin/hadiah')->assertOk()->assertSee('Tumbler ASTO');
        $this->actingAs($this->mech)->post("/poin/hadiah/{$reward->id}/tukar", ['pin' => '000000'])->assertSessionHasErrors('pin');
        $this->assertSame(0, Redemption::count());

        $res = $this->actingAs($this->mech)->post("/poin/hadiah/{$reward->id}/tukar", ["pin" => "739164"]);
        $r = Redemption::first();
        $res->assertRedirect("/poin/kupon/{$r->code}");
        $this->assertSame(180, $this->mech->fresh()->points_balance);
        $this->assertSame(1, $reward->fresh()->stock);
        $this->actingAs($this->mech)->get("/poin/kupon/{$r->code}")->assertOk()->assertSee($r->code)->assertSee('Siap diambil');

        // Batas per karyawan
        $this->actingAs($this->mech)->post("/poin/hadiah/{$reward->id}/tukar", ['pin' => '739164'])->assertSessionHasErrors('pin');

        // Karyawan lain tidak bisa membuka kupon orang lain
        $this->actingAs(User::factory()->activated()->create())->get("/poin/kupon/{$r->code}")->assertNotFound();

        $this->actingAs($this->admin)->post('/admin/poin/kupon/cari', ['code' => strtolower($r->code)])->assertRedirect("/admin/poin/kupon/{$r->code}");
        $this->actingAs($this->admin)->get("/admin/poin/kupon/{$r->code}")->assertSee('Serahkan hadiah');
        $this->actingAs($this->admin)->post("/admin/poin/kupon/{$r->code}/batalkan", ['reason' => 'Salah pilih']);
        $this->assertSame('cancelled', $r->fresh()->status);
        $this->assertSame(300, $this->mech->fresh()->points_balance);
        $this->assertSame(2, $reward->fresh()->stock);

        $r2 = $this->points->redeem($this->mech, $reward);
        $this->actingAs($this->admin)->post("/admin/poin/kupon/{$r2->code}/serahkan");
        $this->assertSame('fulfilled', $r2->fresh()->status);
        $this->actingAs($this->admin)->post("/admin/poin/kupon/{$r2->code}/serahkan")->assertSessionHasErrors('action');
    }

    public function test_cannot_redeem_without_enough_points_or_stock(): void
    {
        $reward = Reward::forceCreate(['name' => 'Helm', 'cost' => 500, 'stock' => 0, 'is_active' => true, 'category' => 'apd']);
        $this->expectException(PointException::class);
        $this->points->redeem($this->mech, $reward);
    }

    public function test_grant_flow_with_threshold_and_four_eyes(): void
    {
        $she = User::factory()->she()->activated()->create();
        $other = User::factory()->activated()->create(['nrp' => 'KT17163', 'department' => 'PLANT']);

        // Admin SHE mengajukan → menunggu
        $this->actingAs($she)->post('/admin/poin/pemberian', ['title' => 'Juara ASTO CUP', 'action' => 'submit', 'recipients' => [
            ['user_id' => $this->mech->id, 'points' => 100, 'note' => 'Juara 1'], ['user_id' => $other->id, 'points' => 60],
        ]])->assertSessionHasNoErrors();
        $g = PointGrant::first();
        $this->assertSame(['pending', 160], [$g->status, $g->total_points]);
        $this->assertSame(0, $this->mech->fresh()->points_balance);
        $this->actingAs($she)->post("/admin/poin/pemberian/{$g->id}/setujui")->assertForbidden();

        $this->actingAs($this->admin)->post("/admin/poin/pemberian/{$g->id}/setujui")->assertSessionHasNoErrors();
        $this->assertSame('approved', $g->fresh()->status);
        $this->assertSame(100, $this->mech->fresh()->points_balance);
        $this->assertSame(60, $other->fresh()->points_balance);
        $this->actingAs($this->mech)->get('/poin')->assertSee('Juara ASTO CUP — Juara 1')->assertSee('100');

        // Admin ≤ batas → langsung terkirim
        $this->actingAs($this->admin)->post('/admin/poin/pemberian', ['title' => 'Temuan hazard', 'action' => 'submit', 'recipients' => [['user_id' => $other->id, 'points' => 40]]]);
        $this->assertSame(100, $other->fresh()->points_balance);

        // Admin > batas → perlu Administrator lain
        $this->actingAs($this->admin)->post('/admin/poin/pemberian', ['title' => 'Bonus besar', 'action' => 'submit', 'recipients' => [['user_id' => $other->id, 'points' => 900]]]);
        $big = PointGrant::firstWhere('title', 'Bonus besar');
        $this->assertSame('pending', $big->status);
        User::factory()->admin()->activated()->create();
        $this->actingAs($this->admin)->post("/admin/poin/pemberian/{$big->id}/setujui")->assertSessionHasErrors('review');
        $this->assertSame(100, $other->fresh()->points_balance);
    }

    public function test_admin_pages_rules_ledger_export_and_adjust(): void
    {
        $this->actingAs($this->admin)->post('/admin/poin/hadiah', ['name' => 'Kupon makan', 'category' => 'makanan', 'cost' => 50, 'stock' => 10, 'is_active' => 1, 'image' => UploadedFile::fake()->image('k.jpg', 1000, 800)])->assertSessionHasNoErrors();
        $this->assertNotNull(Reward::first()->image_path);

        foreach (['/admin/poin', '/admin/poin/pemberian', '/admin/poin/pemberian/baru', '/admin/poin/hadiah', '/admin/poin/kupon', '/admin/poin/riwayat', '/admin/poin/aturan'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
        $this->actingAs($this->admin)->getJson('/admin/poin/cari-karyawan?nrps=KT16042,XX')->assertJsonCount(1)->assertJsonPath('0.nrp', 'KT16042');

        $this->actingAs($this->admin)->put('/admin/poin/aturan', ['rules' => ['attend' => ['points' => 10, 'active' => 1], 'speaker' => ['points' => 20], 'streak7' => ['points' => 25, 'active' => 1]], 'threshold' => 200]);
        $this->assertSame(10, PointRule::value('attend'));
        $this->assertSame(0, PointRule::value('speaker'));

        $this->actingAs($this->admin)->post('/admin/poin/penyesuaian', ['nrp' => 'kt16042', 'amount' => 75, 'reason' => 'Koreksi']);
        $this->actingAs($this->admin)->post('/admin/poin/penyesuaian', ['nrp' => 'KT16042', 'amount' => -500, 'reason' => 'Koreksi']);
        $this->assertSame(0, $this->mech->fresh()->points_balance);   // tidak pernah minus
        $this->actingAs($this->admin)->get('/admin/poin/riwayat/ekspor')->assertOk();

        $gl = User::factory()->activated()->create(['role' => 'gl']);
        $this->actingAs($gl)->get('/admin/poin')->assertForbidden();
        $this->actingAs(User::factory()->she()->activated()->create())->post('/admin/poin/penyesuaian', ['nrp' => 'KT16042', 'amount' => 5, 'reason' => 'x'])->assertForbidden();
    }

    public function test_home_strip_and_mentari(): void
    {
        $this->points->post($this->mech, 260, 'grant', 'Juara');
        $this->actingAs($this->mech)->get('/')->assertSee('Poin HEBAT')->assertSee('260')->assertSee('Skilled');
        $res = $this->actingAs($this->mech)->postJson('/mentari/chat', ['message' => 'poin saya berapa?'])->assertOk();
        $this->assertSame('points', $res->json('intent'));
        $this->assertStringContainsString('260', $res->json('reply'));
    }
}
