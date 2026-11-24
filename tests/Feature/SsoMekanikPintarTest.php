<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Masuk dengan AstoOne" untuk MekanikPintar: surat jalan bertanda tangan ke alamat terdaftar saja. */
class SsoMekanikPintarTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'rahasia-sso-uji-0123456789abcdef';

    private const CALLBACK = 'https://mekanikpintar.test/login/astoone/kembali';

    private string $state = 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789ABCDEFGHIJKL';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mekanikpintar.secret' => self::SECRET, 'services.mekanikpintar.callback' => self::CALLBACK]);
    }

    private function url(array $over = []): string
    {
        return route('sso.mekanikpintar', array_merge(['state' => $this->state, 'return' => self::CALLBACK], $over));
    }

    public function test_guest_is_sent_to_login_then_back_to_consent(): void
    {
        $this->get($this->url())->assertRedirect(route('login'));
        $user = User::factory()->activated('246810')->create(['nrp' => 'KT17163', 'name' => 'Agus Salim']);
        $this->post(route('login.check'), ['nrp' => 'kt17163']);
        $to = $this->post(route('login.pin.submit'), ['pin' => '246810'])->headers->get('location');
        $this->assertStringContainsString('/sso/mekanikpintar?', $to);
        $this->assertStringContainsString('state='.$this->state, $to);
        $this->get($this->url())->assertOk()->assertSee('Masuk ke MekanikPintar?')->assertSee('KT17163');
    }

    public function test_approve_issues_signed_short_lived_assertion_to_registered_callback(): void
    {
        $user = User::factory()->activated()->create(['nrp' => 'KT17163', 'name' => 'Agus Salim']);
        $this->actingAs($user)->get($this->url())->assertOk();
        $res = $this->post(route('sso.mekanikpintar.approve'));
        $location = $res->headers->get('location');
        $this->assertStringStartsWith(self::CALLBACK.'?assertion=', $location);

        parse_str(parse_url($location, PHP_URL_QUERY), $q);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $q['assertion'], self::SECRET, true)), '+/', '-_'), '=');
        $this->assertTrue(hash_equals($expected, $q['sig']));
        $data = json_decode(base64_decode(strtr($q['assertion'], '-_', '+/')), true);
        $this->assertSame('mekanikpintar', $data['aud']);
        $this->assertSame('KT17163', $data['nrp']);
        $this->assertSame($this->state, $data['state']);
        $this->assertLessThanOrEqual(90, $data['exp'] - $data['iat']);
        $this->assertNotEmpty($data['nonce']);
        $this->assertArrayNotHasKey('pin', $data);

        // Konfirmasi hanya sekali per permintaan
        $this->post(route('sso.mekanikpintar.approve'))->assertStatus(419);
    }

    public function test_rejects_foreign_return_url_bad_state_and_when_not_configured(): void
    {
        $user = User::factory()->activated()->create();
        $this->actingAs($user)->get($this->url(['return' => 'https://evil.example/curi']))->assertStatus(400);
        $this->get($this->url(['return' => self::CALLBACK.'/../lain']))->assertStatus(400);
        $this->get($this->url(['state' => 'pendek']))->assertStatus(400);

        config(['services.mekanikpintar.secret' => null]);
        $this->get($this->url())->assertNotFound();
    }

    public function test_cancel_and_switch_account(): void
    {
        $user = User::factory()->activated()->create();
        $this->actingAs($user)->get($this->url());
        $this->post(route('sso.mekanikpintar.cancel'))->assertRedirect(self::CALLBACK.'?error=cancelled');

        $this->get($this->url());
        $this->post(route('sso.mekanikpintar.switch'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
