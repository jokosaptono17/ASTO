<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AppLink;
use App\Models\Campaign;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->user = User::factory()->activated()->create(['name' => 'Ahmad Fauzi']);
    }

    public function test_home_shows_apps_live_campaigns_and_urgent_announcement(): void
    {
        Campaign::create(['title' => 'Kampanye Tayang', 'format' => 'card', 'image_path' => 'campaigns/a.webp', 'is_active' => true]);
        Campaign::create(['title' => 'Kampanye Nanti', 'format' => 'card', 'image_path' => 'campaigns/b.webp', 'is_active' => true, 'starts_at' => now()->addDay()]);
        Campaign::create(['title' => 'Kampanye Lewat', 'format' => 'card', 'image_path' => 'campaigns/c.webp', 'is_active' => true, 'ends_at' => now()->subDay()]);
        Announcement::create(['title' => 'Evakuasi banjir', 'body' => 'Segera ke titik kumpul.', 'level' => 'darurat', 'is_active' => true]);

        $this->actingAs($this->user)->get('/')
            ->assertOk()
            ->assertSee('Halo, Ahmad')
            ->assertSee('Mekanik Pintar')
            ->assertSee('Kampanye Tayang')
            ->assertDontSee('Kampanye Nanti')
            ->assertDontSee('Kampanye Lewat')
            ->assertSee('Evakuasi banjir');
    }

    public function test_opening_an_app_records_visit_and_shows_in_recent(): void
    {
        $app = AppLink::firstWhere('name', 'Monata');
        $this->actingAs($this->user)->get("/buka/{$app->id}")->assertRedirect($app->url);
        $this->assertDatabaseHas('app_visits', ['app_id' => $app->id, 'user_id' => $this->user->id]);
        $this->actingAs($this->user)->get('/')->assertSee('Terakhir dibuka');

        $app->update(['is_active' => false]);
        $this->actingAs($this->user)->get("/buka/{$app->id}")->assertNotFound();
    }

    public function test_favorite_toggle(): void
    {
        $app = AppLink::first();
        $this->actingAs($this->user)->postJson("/aplikasi/{$app->id}/favorit")->assertJson(['favorite' => true]);
        $this->actingAs($this->user)->get('/')->assertSee('Favorit saya');
        $this->actingAs($this->user)->postJson("/aplikasi/{$app->id}/favorit")->assertJson(['favorite' => false]);
    }

    public function test_other_pages_render(): void
    {
        $a = Announcement::create(['title' => 'Info training', 'body' => 'Isi', 'level' => 'info', 'is_active' => true]);
        foreach (['/aplikasi', '/aplikasi?tab=favorit', '/info', "/info/{$a->id}", '/profil', '/offline'] as $url) {
            $this->actingAs($this->user)->get($url)->assertOk();
        }
        $this->assertNotNull($this->user->fresh()->announcements_seen_at);
    }

    public function test_campaign_click_is_counted(): void
    {
        $c = Campaign::create(['title' => 'K3', 'format' => 'card', 'image_path' => 'x.webp', 'link_url' => 'https://example.com/k3', 'is_active' => true]);
        $this->actingAs($this->user)->get("/kampanye/{$c->id}")->assertRedirect('https://example.com/k3');
        $this->assertSame(1, $c->fresh()->clicks);
    }

    public function test_mentari_finds_apps_and_helps_guests(): void
    {
        $this->postJson('/mentari/chat', ['message' => 'saya lupa pin'])
            ->assertOk()->assertJsonPath('source', 'guide')->assertJsonPath('actions.0.external', true);

        $res = $this->actingAs($this->user)->postJson('/mentari/chat', ['message' => 'tolong bukakan scania'])->assertOk();
        $this->assertStringContainsString('Scania WSS', $res->json('reply'));
        $this->assertStringContainsString('/buka/', $res->json('actions.0.href'));
    }

    public function test_mentari_uses_ai_endpoint_and_filters_links(): void
    {
        config(['services.mentari.endpoint' => 'http://127.0.0.1:8787/chat']);
        Http::fake(['127.0.0.1:8787/*' => Http::response(['reply' => 'Dari AI', 'actions' => [
            ['label' => 'Aman', 'href' => '/aplikasi'],
            ['label' => 'Bahaya', 'href' => 'https://evil.example.com'],
        ]])]);

        $this->actingAs($this->user)->postJson('/mentari/chat', ['message' => 'halo'])
            ->assertJsonPath('source', 'ai')->assertJsonPath('reply', 'Dari AI')->assertJsonCount(1, 'actions');

        Http::assertSent(fn ($r) => $r['context']['nrp'] === $this->user->nrp && count($r['context']['apps']) > 0);
    }

    public function test_health_check_command(): void
    {
        Http::fake(['*mekanikpintar.com*' => Http::response('ok'), '*' => Http::response('down', 503)]);
        $this->artisan('astoone:check-apps')->assertSuccessful();
        $this->assertSame('up', AppLink::firstWhere('name', 'Mekanik Pintar')->health_status);
        $this->assertSame('down', AppLink::firstWhere('name', 'Scania WSS')->health_status);
    }
}
