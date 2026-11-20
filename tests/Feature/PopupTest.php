<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PopupTest extends TestCase
{
    use RefreshDatabase;

    private User $mech;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->mech = User::factory()->activated()->create(['department' => 'SERVICE REPAIR RM']);
    }

    private function popup(array $attrs = []): Campaign
    {
        return Campaign::forceCreate(array_merge([
            'title' => 'Safety alert: tyre burst', 'format' => 'popup', 'image_path' => 'campaigns/x.webp',
            'popup_frequency' => 'once', 'popup_seconds' => 3, 'popup_ack' => false, 'is_active' => true,
        ], $attrs));
    }

    public function test_admin_creates_popup_with_settings(): void
    {
        $admin = User::factory()->admin()->activated()->create();
        $this->actingAs($admin)->get('/admin/kampanye/create')->assertOk()->assertSee('Pengaturan pop-up')->assertSee('Wajib dibaca');
        $this->actingAs($admin)->post('/admin/kampanye', [
            'title' => 'Stop work authority', 'format' => 'popup', 'image' => UploadedFile::fake()->image('p.jpg', 1200, 1600),
            'popup_frequency' => 'daily', 'popup_seconds' => 5, 'popup_ack' => 1, 'target_departments' => ['SERVICE REPAIR RM'], 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $c = Campaign::firstWhere('title', 'Stop work authority');
        $this->assertTrue($c->isPopup());
        $this->assertSame(['daily', 5, true, ['SERVICE REPAIR RM']], [$c->popup_frequency, $c->popup_seconds, $c->popup_ack, $c->target_departments]);
        [$w, $h] = getimagesize(Storage::disk('public')->path($c->image_path));
        $this->assertSame([900, 1200], [$w, $h]);
    }

    public function test_once_popup_shows_until_seen_and_is_not_a_card(): void
    {
        $c = $this->popup();
        $home = $this->actingAs($this->mech)->get('/')->assertOk()->assertSee('campaignPopup', false)->assertSee('Safety alert: tyre burst');
        $this->assertCount(0, $home->viewData('cards'));

        $this->actingAs($this->mech)->postJson("/kampanye/{$c->id}/popup")->assertOk();
        $this->assertSame(1, CampaignView::first()->views);
        $this->actingAs($this->mech)->get('/')->assertDontSee('campaignPopup', false);
    }

    public function test_must_read_popup_repeats_until_acknowledged(): void
    {
        $c = $this->popup(['popup_ack' => true]);
        $this->actingAs($this->mech)->postJson("/kampanye/{$c->id}/popup");
        $this->actingAs($this->mech)->postJson("/kampanye/{$c->id}/popup/tutup", ['action' => 'dismiss']);
        $this->actingAs($this->mech)->get('/')->assertSee('Saya sudah membaca');

        $this->actingAs($this->mech)->postJson("/kampanye/{$c->id}/popup/tutup", ['action' => 'ack'])->assertOk();
        $this->assertNotNull(CampaignView::first()->acknowledged_at);
        $this->actingAs($this->mech)->get('/')->assertDontSee('campaignPopup', false);
    }

    public function test_daily_and_session_frequencies_and_dismiss(): void
    {
        $daily = $this->popup(['popup_frequency' => 'daily', 'title' => 'Harian']);
        $this->actingAs($this->mech)->postJson("/kampanye/{$daily->id}/popup");
        $this->actingAs($this->mech)->get('/')->assertDontSee('Harian');
        $this->travel(1)->days();
        $this->actingAs($this->mech)->get('/')->assertSee('Harian')->assertSee('Jangan tampilkan lagi');
        $this->actingAs($this->mech)->postJson("/kampanye/{$daily->id}/popup/tutup", ['action' => 'dismiss']);
        $this->travel(1)->days();
        $this->actingAs($this->mech)->get('/')->assertDontSee('Harian');

        $session = $this->popup(['popup_frequency' => 'session', 'title' => 'Darurat banjir']);
        $this->actingAs($this->mech)->postJson("/kampanye/{$session->id}/popup");
        $this->actingAs($this->mech)->get('/')->assertSee('Darurat banjir');
    }

    public function test_targeting_preview_and_stats(): void
    {
        $c = $this->popup(['popup_ack' => true, 'target_departments' => ['PLANT']]);
        $this->actingAs($this->mech)->get('/')->assertDontSee('campaignPopup', false);
        $this->actingAs($this->mech)->get("/?pratinjau-popup={$c->id}")->assertDontSee('campaignPopup', false);

        $admin = User::factory()->admin()->activated()->create(['department' => 'ENGINEERING']);
        $this->actingAs($admin)->get("/?pratinjau-popup={$c->id}")->assertSee('Pratinjau admin');

        $plant = User::factory()->activated()->create(['department' => 'PLANT', 'name' => 'DEDI KURNIAWAN']);
        $this->actingAs($plant)->get('/')->assertSee('Wajib dibaca');
        $this->actingAs($admin)->get("/admin/kampanye/{$c->id}/edit")->assertOk()->assertSee('Belum konfirmasi baca')->assertSee('Dedi Kurniawan');
        $this->actingAs($admin)->get('/admin/kampanye')->assertSee('wajib baca');
    }
}
