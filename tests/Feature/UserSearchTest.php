<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pencarian pengguna di /admin/pengguna: NRP, nama (urutan kata bebas), departemen, jabatan. */
class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->activated()->create(['name' => 'ADMIN PLANT', 'nrp' => 'KT00001', 'department' => 'PLANT']);
        User::factory()->create(['name' => 'JOKO SAPTONO', 'nrp' => 'KT16026', 'department' => 'PLANT', 'position' => 'INSTRUKTUR']);
        User::factory()->create(['name' => 'AGUS SUWITO', 'nrp' => 'KT16036', 'department' => 'SERVICE HAULING', 'position' => 'MEKANIK M9']);
        User::factory()->create(['name' => 'ADVEN PAKPAHAN', 'nrp' => 'KL25010', 'department' => 'DAILY HAULING', 'position' => 'MEKANIK M3']);
    }

    private function search(string $q, array $extra = [])
    {
        return $this->actingAs($this->admin)->get(route('admin.users.index', ['q' => $q] + $extra));
    }

    public function test_search_by_name_nrp_department_and_position(): void
    {
        $this->search('joko')->assertOk()->assertSee('Joko Saptono')->assertDontSee('Agus Suwito');
        $this->search('saptono joko')->assertSee('Joko Saptono')->assertDontSee('Adven Pakpahan');
        $this->search('kt16026')->assertSee('Joko Saptono')->assertDontSee('Agus Suwito');
        $this->search('KT 16036')->assertSee('Agus Suwito')->assertDontSee('Joko Saptono');
        $this->search('hauling')->assertSee('Agus Suwito')->assertSee('Adven Pakpahan')->assertDontSee('Joko Saptono');
        $this->search('daily mekanik')->assertSee('Adven Pakpahan')->assertDontSee('Agus Suwito');
        $this->search('tidak-ada-orang')->assertSee('Tidak ada pengguna dengan NRP');
    }

    public function test_live_search_returns_only_results_fragment(): void
    {
        $res = $this->actingAs($this->admin)->get(route('admin.users.index', ['q' => 'joko']), ['X-Fragment' => 'hasil']);
        $res->assertOk()->assertSee('hasil-pengguna', false)->assertSee('Joko Saptono')->assertSee('1</span> pengguna cocok', false)
            ->assertDontSee('<html', false)->assertDontSee('Impor dari Excel');
    }

    public function test_full_page_has_live_search(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()
            ->assertSee('userSearch(', false)->assertSee('Ketik NRP, nama, departemen, atau jabatan');
    }
}
