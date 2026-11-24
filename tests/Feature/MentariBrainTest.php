<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\MentariFaq;
use App\Models\MentariLog;
use App\Models\Question;
use App\Models\QuestionSet;
use App\Models\Setting;
use App\Models\User;
use App\Services\Mentari\Ai\Providers;
use App\Services\Mentari\MentariBrain;
use App\Services\Mentari\Memory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Mentari cerdas: bahasa sehari-hari, ingatan, pengetahuan, ujian, log, dan AI gratis (disimulasikan). */
class MentariBrainTest extends TestCase
{
    use RefreshDatabase;

    private User $mech;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->mech = User::factory()->activated()->create(['nrp' => 'KT16042', 'name' => 'IKHSANUDIN APRIYANTO', 'department' => 'SERVICE REPAIR RM', 'position' => 'MEKANIK', 'supervisor' => 'ARIF SETIAWAN', 'phone' => '081200000000']);
        $this->admin = User::factory()->admin()->activated()->create(['nrp' => 'KT16026', 'name' => 'JOKO SAPTONO']);
        config([
            'services.mentari.endpoint' => null,
            'services.mentari.ai.providers.groq.key' => null,
            'services.mentari.ai.providers.gemini.key' => null,
            'services.mentari.ai.providers.openrouter.key' => null,
        ]);
    }

    private function withGroq(): void
    {
        config(['services.mentari.ai.providers.groq.key' => 'uji-groq', 'services.mentari.ai.providers.groq.base' => 'https://groq.test/v1']);
    }

    private function withGemini(): void
    {
        config(['services.mentari.ai.providers.gemini.key' => 'uji-gemini', 'services.mentari.ai.providers.gemini.base' => 'https://gemini.test/v1']);
    }

    private function ask(User $user, string $message, array $history = [])
    {
        return $this->actingAs($user)->postJson('/mentari/chat', ['message' => $message, 'history' => $history])->assertOk();
    }

    private static function completion(?string $content, array $toolCalls = []): array
    {
        return ['choices' => [['message' => array_filter(['role' => 'assistant', 'content' => $content, 'tool_calls' => $toolCalls ?: null])]]];
    }

    /* ---------------------------------------------------------- Lapis 1 */

    public function test_chat_slang_and_typos_still_reach_real_data(): void
    {
        $this->ask($this->mech, 'brp poin sy')->assertJsonPath('intent', 'points')->assertJsonPath('source', 'guide');
        $this->ask($this->mech, 'abesn sy bln ini')->assertJsonPath('intent', 'attendance');
        $this->ask($this->mech, 'makasih min')->assertJsonPath('intent', 'thanks');
    }

    public function test_follow_up_questions_use_conversation_memory(): void
    {
        $brain = app(MentariBrain::class);
        $memory = new Memory(new Store('uji', new ArraySessionHandler(10)));

        $first = $brain->respond('KT16042', [], $this->admin, null, $memory, log: false);
        $this->assertSame('KT16042', $first['cards'][0]['nrp']);

        $follow = $brain->respond('gl walinya siapa?', [], $this->admin, null, $memory, log: false);
        $this->assertSame('KT16042', $follow['cards'][0]['nrp'] ?? null);
        $this->assertSame('GL wali Ikhsanudin Apriyanto: Arif Setiawan.', $follow['reply']);
        $this->assertStringContainsString('Arif', collect($follow['cards'][0]['fields'])->firstWhere('label', 'GL wali')['value']);
    }

    public function test_exam_questions_never_reveal_the_code_to_participants(): void
    {
        $set = QuestionSet::create(['name' => 'BEST MEKANIK']);
        Question::create(['question_set_id' => $set->id, 'body' => 'Soal?', 'options' => ['A' => 'a', 'B' => 'b'], 'answer' => 'A']);
        $exam = Exam::create(['title' => 'Seleksi Best Mekanik', 'code' => 'BM-RAHASIA', 'is_published' => true, 'opens_at' => now()->subMinute(), 'closes_at' => now()->addHour(), 'duration_minutes' => 30]);
        $exam->sets()->attach($set->id);
        $exam->participants()->create(['user_id' => $this->mech->id, 'label' => 'Kandidat Best Mekanik']);

        $this->ask($this->mech, 'ujian sy kpn?')->assertJsonPath('intent', 'exams')->assertJsonPath('cards.0.items.0.title', 'Seleksi Best Mekanik');
        $res = $this->ask($this->mech, 'kode ujiannya apa');
        $this->assertStringNotContainsString('BM-RAHASIA', json_encode($res->json()));
        $this->assertStringContainsString('BM-RAHASIA', json_encode($this->ask($this->admin, 'kode ujian')->json()));
    }

    public function test_knowledge_base_answers_how_to_questions(): void
    {
        $this->ask($this->mech, 'tidur 4 jam boleh kerja?')->assertJsonPath('source', 'knowledge');
        $this->ask($this->mech, 'gmn kalau gps sy gk kebaca pas absen')->assertJsonPath('source', 'knowledge')
            ->assertJson(fn ($json) => $json->where('reply', fn ($r) => str_contains($r, 'lokasi belum terverifikasi'))->etc());
    }

    public function test_unanswered_questions_are_logged_and_become_faq(): void
    {
        $this->ask($this->mech, 'bagaimana kalibrasi sensor tekanan oli genset')->assertJsonPath('source', 'fallback');
        $this->ask($this->mech, 'bagaimana kalibrasi sensor tekanan oli genset');
        $this->assertSame(2, MentariLog::where('answered', false)->count());

        $this->actingAs($this->admin)->get(route('admin.mentari.index'))->assertOk()->assertSee('kalibrasi sensor tekanan oli genset')->assertSee('2×');

        $normalized = MentariLog::value('normalized');
        $this->actingAs($this->admin)->post(route('admin.mentari.store'), [
            'question' => 'Kalibrasi sensor tekanan oli genset', 'keywords' => 'kalibrasi, sensor, tekanan oli, genset',
            'answer' => 'Ikuti SOP kalibrasi di shop manual genset bab sensor.', 'is_active' => 1, 'resolve_normalized' => $normalized,
        ])->assertRedirect();
        $this->assertSame(0, MentariLog::where('answered', false)->whereNull('resolved_at')->count());

        $this->ask($this->mech, 'bagaimana kalibrasi sensor tekanan oli genset')->assertJsonPath('reply', 'Ikuti SOP kalibrasi di shop manual genset bab sensor.');
    }

    public function test_users_can_rate_only_their_own_answers(): void
    {
        $logId = $this->ask($this->mech, 'brp poin sy')->json('log_id');
        $this->actingAs($this->mech)->postJson(route('mentari.feedback'), ['log_id' => $logId, 'helpful' => false])->assertOk();
        $this->assertFalse(MentariLog::find($logId)->helpful);
        $this->actingAs($this->admin)->postJson(route('mentari.feedback'), ['log_id' => $logId, 'helpful' => true])->assertNotFound();
    }

    /* ---------------------------------------------------------- Lapis 2 */

    public function test_structured_questions_never_spend_ai_quota(): void
    {
        $this->withGroq();
        Http::fake();
        $this->ask($this->mech, 'brp poin sy')->assertJsonPath('source', 'guide');
        $this->ask($this->mech, 'tidur 4 jam boleh kerja?')->assertJsonPath('source', 'knowledge');
        Http::assertNothingSent();
    }

    public function test_ai_answers_free_questions_using_tools_for_real_data(): void
    {
        $this->withGroq();
        Http::fakeSequence('groq.test/*')
            ->push(self::completion(null, [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'poin_saya', 'arguments' => '{}']]]))
            ->push(self::completion("**Saldo** Anda 0 poin.\n- Hadir P5M untuk menambah poin"));

        $res = $this->ask($this->mech, 'apakah saldo sy cukup buat beli sesuatu minggu ini?')
            ->assertJsonPath('source', 'ai')->assertJsonPath('provider_label', 'Groq');
        $this->assertSame("Saldo Anda 0 poin.\n• Hadir P5M untuk menambah poin", $res->json('reply'));
        $this->assertSame('/poin', parse_url($res->json('actions.0.href'), PHP_URL_PATH));

        $sent = Http::recorded()->map(fn ($pair) => $pair[0]);
        $this->assertCount(2, $sent);
        $first = $sent[0]->data();
        $this->assertSame('openai/gpt-oss-120b', $first['model']);
        $this->assertContains('poin_saya', array_column(array_column($first['tools'], 'function'), 'name'));
        $this->assertStringContainsString('Ikhsanudin', $first['messages'][0]['content']);
        $toolMsg = collect($sent[1]->data()['messages'])->firstWhere('role', 'tool');
        $this->assertStringContainsString('Saldo Poin HEBAT Anda 0 poin', $toolMsg['content']);
        $this->assertSame(1, MentariLog::where('source', 'ai')->where('provider', 'groq')->count());
        $this->assertSame(app(Providers::class)->perUserLimit() - 1, app(Providers::class)->userLeft($this->mech));
    }

    public function test_falls_back_to_next_provider_and_never_sends_personal_data_to_non_private_providers(): void
    {
        $this->withGroq();
        $this->withGemini();
        Http::fake([
            'groq.test/*' => Http::response(['error' => ['message' => 'Rate limit reached']], 429),
            'gemini.test/*' => Http::response(self::completion('Silakan cek shop manual dan ikuti LOTO.')),
        ]);

        $this->ask($this->mech, 'apa yg harus dicek kalau rem dump truck bunyi', [['role' => 'user', 'content' => 'NRP sy KT16042 hp 081200000000'], ['role' => 'assistant', 'content' => 'Ini data Ikhsanudin Apriyanto, seksi SERVICE REPAIR RM']])
            ->assertJsonPath('provider_label', 'Gemini')->assertJsonPath('reply', 'Silakan cek shop manual dan ikuti LOTO.');

        $gemini = Http::recorded()->map(fn ($pair) => $pair[0])->first(fn (HttpRequest $r) => str_contains($r->url(), 'gemini.test'))->data();
        $payload = json_encode($gemini);
        $this->assertStringNotContainsString('KT16042', $payload);
        $this->assertStringNotContainsString('081200000000', $payload);
        $this->assertStringNotContainsString('Ikhsanudin', $payload);
        $this->assertStringNotContainsString('SERVICE REPAIR', $payload);
        $this->assertNotContains('poin_saya', array_column(array_column($gemini['tools'], 'function'), 'name'));

        $groq = collect(app(Providers::class)->status())->firstWhere('name', 'groq');
        $this->assertNotNull($groq['cooling']);
        $this->assertStringContainsString('Kuota', $groq['error']['reason']);
    }

    public function test_retries_without_tools_when_model_rejects_them(): void
    {
        $this->withGroq();
        Http::fakeSequence('groq.test/*')
            ->push(['error' => ['message' => 'tool_use_failed']], 400)
            ->push(self::completion('Jawaban tanpa alat.'));

        $this->ask($this->mech, 'apa beda oli mesin sae 15w40 dan 10w30')->assertJsonPath('reply', 'Jawaban tanpa alat.');
        $second = Http::recorded()[1][0]->data();
        $this->assertArrayNotHasKey('tools', $second);
        $this->assertArrayNotHasKey('reasoning_effort', $second);
    }

    public function test_daily_ai_quota_per_user_and_admin_switch(): void
    {
        $this->withGroq();
        Setting::put(['mentari_ai_per_user' => '1']);
        Http::fake(['groq.test/*' => Http::response(self::completion('Jawaban AI.'))]);

        $this->ask($this->mech, 'tips merawat ban dump truck di jalan berlumpur')->assertJsonPath('source', 'ai');
        $this->ask($this->mech, 'tips menghemat solar unit hauling')->assertJsonPath('source', 'fallback')
            ->assertJson(fn ($json) => $json->where('reply', fn ($r) => str_contains($r, 'Kuota tanya AI'))->etc());

        $this->actingAs($this->admin)->post(route('admin.mentari.settings'), ['enabled' => 0, 'per_user' => 10])->assertRedirect();
        $this->ask($this->admin, 'tips merawat aki unit')->assertJsonPath('source', 'fallback');
        Http::assertSentCount(1);
    }

    public function test_guests_never_use_ai(): void
    {
        $this->withGroq();
        Http::fake();
        $this->postJson('/mentari/chat', ['message' => 'jelaskan cara kerja turbocharger'])->assertOk()->assertJsonPath('source', 'fallback');
        Http::assertNothingSent();
    }

    public function test_admin_page_shows_ai_status_and_test_tool(): void
    {
        $this->withGroq();
        MentariFaq::create(['question' => 'Jam operasional toolroom', 'keywords' => 'toolroom, jam', 'answer' => 'Toolroom buka 24 jam.', 'is_active' => true]);
        $this->actingAs($this->admin)->get(route('admin.mentari.index'))->assertOk()
            ->assertSee('Cara Mentari berpikir')->assertSee('Groq')->assertSee('Boleh data karyawan')->assertSee('Tanpa data karyawan')
            ->assertDontSee('uji-groq');
        $this->actingAs($this->admin)->postJson(route('admin.mentari.test'), ['message' => 'jam buka toolroom'])
            ->assertOk()->assertJsonPath('reply', 'Toolroom buka 24 jam.');
        $this->assertSame(0, MentariLog::count());
    }
}
