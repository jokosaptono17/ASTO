<?php

namespace Tests\Feature;

use App\Models\AppLink;
use App\Models\Campaign;
use App\Models\Setting;
use App\Models\User;
use App\Services\Spreadsheet;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('public');
    }

    public function test_access_by_role(): void
    {
        $user = User::factory()->activated()->create();
        $she = User::factory()->she()->activated()->create();
        $admin = User::factory()->admin()->activated()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();

        $this->actingAs($she)->get('/admin')->assertOk();
        $this->actingAs($she)->get('/admin/kampanye')->assertOk();
        $this->actingAs($she)->get('/admin/pengumuman')->assertOk();
        $this->actingAs($she)->get('/admin/aplikasi')->assertForbidden();
        $this->actingAs($she)->get('/admin/pengguna')->assertForbidden();

        foreach (['/admin', '/admin/kampanye/create', '/admin/pengumuman/create', '/admin/aplikasi', '/admin/aplikasi/create', '/admin/pengguna', '/admin/pengguna/create', '/admin/mentari', '/admin/pengaturan'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($admin)->get('/admin/aplikasi/'.AppLink::first()->id.'/edit')->assertOk();
    }

    public function test_she_admin_creates_campaign_with_cropped_image(): void
    {
        $she = User::factory()->she()->activated()->create();
        $this->actingAs($she)->post('/admin/kampanye', [
            'title' => 'Bulan K3',
            'format' => 'card',
            'image' => UploadedFile::fake()->image('poster.jpg', 2000, 1200),
            'link_url' => 'https://she-landing-page.vercel.app/',
            'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addWeek()->format('Y-m-d'),
            'is_active' => 1,
        ])->assertRedirect('/admin/kampanye')->assertSessionHasNoErrors();

        $c = Campaign::firstWhere('title', 'Bulan K3');
        Storage::disk('public')->assertExists($c->image_path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($c->image_path));
        $this->assertEqualsWithDelta(0.8, $w / $h, 0.01);
        $this->assertSame('tayang', $c->status());
        $this->assertSame($she->id, $c->created_by);
        $this->assertTrue($c->ends_at->isSameDay(now()->addWeek()) && $c->ends_at->format('H:i') === '23:59');
    }

    public function test_admin_manages_apps_order_and_icon_upload(): void
    {
        $admin = User::factory()->admin()->activated()->create();
        $this->actingAs($admin)->post('/admin/aplikasi', [
            'name' => 'Tool Crib', 'url' => 'https://tools.example.com', 'placement' => 'grid', 'icon_type' => 'image',
            'icon_file' => UploadedFile::fake()->image('logo.png', 400, 400), 'color' => '#3bff8a', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $app = AppLink::firstWhere('name', 'Tool Crib');
        $this->assertSame('image', $app->icon_type);
        Storage::disk('public')->assertExists($app->icon);

        $this->actingAs($admin)->post('/admin/aplikasi', ['name' => 'X', 'url' => 'javascript:alert(1)', 'placement' => 'grid', 'icon_type' => 'remix', 'icon' => 'ri-tools-fill', 'color' => '#ffffff'])
            ->assertSessionHasErrors('url');

        $first = AppLink::where('placement', 'featured')->ordered()->first();
        $this->actingAs($admin)->post("/admin/aplikasi/{$first->id}/geser", ['direction' => 'down']);
        $this->assertNotEquals($first->id, AppLink::where('placement', 'featured')->ordered()->first()->id);
    }

    public function test_user_import_and_pin_reset(): void
    {
        $admin = User::factory()->admin()->activated()->create();
        $existing = User::factory()->activated('482915')->create(['nrp' => 'KT100', 'name' => 'Lama']);

        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        Spreadsheet::writeXlsx($path, [
            ['NRP', 'NAMA', 'DEPARTEMEN', 'JABATAN'],
            ['kt100', 'Nama Baru', 'PLANT', 'MEKANIK'],
            ['KT200', 'Budi Santoso', 'PLANT', 'TYREMAN'],
            ['', 'Tanpa NRP', '', ''],
        ]);
        $this->actingAs($admin)->post('/admin/pengguna/impor', [
            'file' => new UploadedFile($path, 'karyawan.xlsx', null, null, true),
        ])->assertSessionHas('import', fn ($r) => $r['created'] === 1 && $r['updated'] === 1 && count($r['skipped']) === 1);

        $this->assertSame('NAMA BARU', $existing->fresh()->name);
        $this->assertTrue($existing->fresh()->isActivated(), 'PIN tidak boleh hilang saat impor');
        $this->assertDatabaseHas('users', ['nrp' => 'KT200', 'position' => 'TYREMAN']);

        $this->actingAs($admin)->post("/admin/pengguna/{$existing->id}/reset-pin");
        $this->assertFalse($existing->fresh()->isActivated());

        $this->actingAs($admin)->get('/admin/pengguna/template')->assertOk();
        $this->actingAs($admin)->get('/admin/pengguna/ekspor')->assertOk();
    }

    public function test_admin_cannot_demote_self_and_settings_update(): void
    {
        $admin = User::factory()->admin()->activated()->create();
        $this->actingAs($admin)->put("/admin/pengguna/{$admin->id}", ['nrp' => $admin->nrp, 'name' => $admin->name, 'role' => 'user', 'is_active' => 1])
            ->assertSessionHasErrors('role');

        $this->actingAs($admin)->put('/admin/pengaturan', [
            'site_name' => 'AstoOne', 'site_tagline' => 'Portal', 'slogan' => 'KITA SATU', 'help_whatsapp' => '0812 3456 7890',
            'help_message' => 'Halo', 'mentari_greeting' => 'Hai', 'campaign_section_title' => 'Kampanye',
        ])->assertSessionHasNoErrors();
        $this->assertSame('6281234567890', Setting::whatsappNumber());
    }

    public function test_hero_photo_can_be_replaced_and_reset(): void
    {
        $admin = User::factory()->admin()->activated()->create();
        $base = ['site_name' => 'AstoOne', 'site_tagline' => 'Portal', 'slogan' => 'KITA SATU', 'help_whatsapp' => '081313333315',
            'help_message' => 'Halo', 'mentari_greeting' => 'Hai', 'campaign_section_title' => 'Kampanye'];

        $this->assertSame('/images/hero-default.webp', Setting::hero()['src']);
        $this->get('/masuk')->assertSee('/images/hero-default.webp', false);

        $this->actingAs($admin)->put('/admin/pengaturan', $base + ['hero' => UploadedFile::fake()->image('site.jpg', 2600, 1600)])->assertSessionHasNoErrors();
        $path = Setting::get('hero_image');
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString($path, Setting::hero()['src']);
        $this->actingAs($admin)->get('/')->assertSee($path, false)->assertSee('Mau buka apa hari ini?');

        $this->actingAs($admin)->put('/admin/pengaturan', $base + ['hero_reset' => 1])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($path);
        $this->assertSame('/images/hero-default.webp', Setting::hero()['src']);
    }

    public function test_desktop_admin_shell_and_dashboard_widgets(): void
    {
        $admin = User::factory()->admin()->activated()->create();
        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('Absensi hari ini')->assertSee('Aktivasi per seksi')->assertSee('Status aplikasi')
            ->assertSee('Cari menu, pengguna, aplikasi')->assertSee('Jadwal rutin');
        $gl = User::factory()->activated()->create(['role' => 'gl']);
        $this->actingAs($gl)->get('/admin/absensi')->assertOk()->assertSee('Sesi & event')->assertDontSee('Pengaturan');
        $this->actingAs($admin)->get('/admin/aplikasi?q=monata')->assertOk()->assertSee('Monata');
    }

    public function test_announcement_crud_by_she(): void
    {
        $she = User::factory()->she()->activated()->create();
        $this->actingAs($she)->post('/admin/pengumuman', ['title' => 'Stand down', 'body' => 'Jumat 07.00', 'level' => 'penting', 'is_active' => 1, 'is_pinned' => 1])
            ->assertRedirect('/admin/pengumuman');
        $this->actingAs(User::factory()->activated()->create())->get('/')->assertSee('Stand down');
    }

    public function test_campaign_link_rejects_protocol_relative_url(): void
    {
        $admin = User::factory()->admin()->activated()->create();
        $this->actingAs($admin)->post('/admin/kampanye', [
            'title' => 'X', 'format' => 'card', 'image' => UploadedFile::fake()->image('p.jpg', 800, 1000),
            'link_url' => '//evil.example.com', 'is_active' => 1,
        ])->assertSessionHasErrors('link_url');
    }

    public function test_make_admin_command(): void
    {
        $this->artisan('astoone:admin', ['nrp' => 'kt777', 'name' => 'Joko'])->assertSuccessful();
        $this->assertTrue(User::firstWhere('nrp', 'KT777')->isAdmin());
    }
}
