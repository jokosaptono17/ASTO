<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/masuk');
        $this->get('/masuk')->assertOk()->assertSee('NRP');
    }

    public function test_unregistered_nrp_shows_help_button(): void
    {
        $this->from('/masuk')->post('/masuk', ['nrp' => 'xx999'])->assertRedirect('/masuk');
        $this->get('/masuk')->assertSee('NRP XX999 belum terdaftar')->assertSee('wa.me/6281313333315', false);
    }

    public function test_inactive_user_cannot_continue(): void
    {
        User::factory()->create(['nrp' => 'KT1', 'is_active' => false]);
        $this->post('/masuk', ['nrp' => 'kt1'])->assertSessionHasErrors('nrp');
    }

    public function test_first_login_activates_with_first_name_and_new_pin(): void
    {
        $user = User::factory()->create(['nrp' => 'KT17163', 'name' => 'Ahmad Fauzi']);

        $this->post('/masuk', ['nrp' => ' kt 17163 '])->assertRedirect('/aktivasi');
        $this->get('/aktivasi')->assertOk()->assertSee('A•••• (5 huruf)');

        $this->post('/aktivasi', ['first_name' => 'Budi', 'pin' => '482915', 'pin_confirmation' => '482915'])
            ->assertSessionHasErrors('first_name');
        $this->post('/aktivasi', ['first_name' => 'ahmad', 'pin' => '111111', 'pin_confirmation' => '111111'])
            ->assertSessionHasErrors('pin');

        $this->post('/aktivasi', ['first_name' => 'ahmad', 'pin' => '482915', 'pin_confirmation' => '482915'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->isActivated());
        $this->assertNotEquals('482915', $user->fresh()->pin);
    }

    public function test_login_with_pin_and_lockout(): void
    {
        $user = User::factory()->activated('482915')->create(['nrp' => 'KT2']);

        $this->post('/masuk', ['nrp' => 'KT2'])->assertRedirect('/masuk/pin');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/masuk/pin', ['pin' => '000000'])->assertSessionHasErrors('pin');
        }
        $this->post('/masuk/pin', ['pin' => '482915'])->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_login_with_correct_pin(): void
    {
        $user = User::factory()->activated('482915')->create(['nrp' => 'KT3']);
        $this->post('/masuk', ['nrp' => 'KT3']);
        $this->post('/masuk/pin', ['pin' => '482915', 'remember' => 1])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_change_pin(): void
    {
        $user = User::factory()->activated('482915')->create();
        $this->actingAs($user)->put('/profil/pin', ['current_pin' => '000000', 'pin' => '739164', 'pin_confirmation' => '739164'])
            ->assertSessionHasErrors('current_pin');
        $this->actingAs($user)->put('/profil/pin', ['current_pin' => '482915', 'pin' => '739164', 'pin_confirmation' => '739164'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('739164', $user->fresh()->pin));
    }
}
