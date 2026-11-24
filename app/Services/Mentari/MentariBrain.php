<?php

namespace App\Services\Mentari;

use App\Models\MentariLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Mentari\Ai\Agent;
use App\Services\Mentari\Ai\Providers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Alur berpikir Mentari, dari yang paling pasti & gratis ke yang paling luwes:
 *  1. Ingatan percakapan  — "training dia?" → "training KT16042"
 *  2. Otak bawaan         — data asli: kartu karyawan, poin, absensi, ujian, aplikasi, statistik
 *  3. Pengetahuan         — panduan bawaan + FAQ + aplikasi + pengumuman (pencarian BM25)
 *  4. AI gratis           — Groq → Gemini → OpenRouter, mengambil data lewat alat (tool calling)
 *  5. Layanan AI lama     — MENTARI_ENDPOINT (kontrak MekanikPintar), bila diatur (provider "endpoint")
 *  6. Cadangan            — minta maaf + tombol WhatsApp admin; pertanyaan dicatat untuk admin
 */
class MentariBrain
{
    public function __construct(
        private Language $lang,
        private MentariGuide $guide,
        private KnowledgeBase $kb,
        private Agent $agent,
        private Providers $providers,
        private MentariContext $contexts,
    ) {}

    public function respond(string $message, array $history, ?User $viewer, ?string $page = null, ?Memory $memory = null, bool $log = true, bool $useAi = true): array
    {
        $started = microtime(true);
        $context = $this->contexts->for($viewer) + ['page' => $page];
        $normalized = $this->lang->normalize($message);

        $asked = $message;
        if ($viewer && $memory && ($rewrite = $memory->resolve($normalized))) {
            $asked = $rewrite[0];
        }

        $answer = $this->guide->answer($asked, $context, $viewer);
        $source = 'guide';
        $provider = null;
        $note = null;

        if ($answer['intent'] === 'fallback') {
            if ($kb = $this->kb->answer($message)) {
                [$answer, $source] = [$kb, 'knowledge'];
            } elseif ($viewer && $useAi && $this->providers->enabled()) {
                if ($this->providers->userLeft($viewer) <= 0) {
                    $note = 'Kuota tanya AI Anda hari ini sudah habis — besok bisa lagi. Pertanyaan tentang aplikasi, poin, absensi, dan ujian tetap bisa saya jawab.';
                } elseif ($ai = $this->agent->think($message, $history, $viewer, $context)) {
                    $this->providers->chargeUser($viewer);
                    [$answer, $source, $provider] = [$ai, 'ai', $ai['provider']];
                }
            }
        }
        $legacy = $answer['intent'] === 'fallback' || in_array($answer['intent'], ['greeting', 'identity', 'thanks'], true);
        if ($legacy && $source === 'guide' && config('services.mentari.endpoint') && ($ai = $this->askEndpoint($message, $history, $context))) {
            [$answer, $source, $provider] = [$ai + ['cards' => [], 'intent' => $answer['intent']], 'ai', 'endpoint'];
        }
        if ($note && $answer['intent'] === 'fallback') {
            $answer['reply'] = $note."\n\n".$answer['reply'];
        }

        $answered = $answer['intent'] !== 'fallback' || $source !== 'guide';
        if ($viewer && $memory) {
            $memory->remember($answer);
        }

        $logId = null;
        if ($log) {
            $logId = MentariLog::create([
                'user_id' => $viewer?->id,
                'question' => mb_substr(trim($message), 0, 300),
                'normalized' => mb_substr($this->lang->lite($message), 0, 300),
                'intent' => mb_substr((string) $answer['intent'], 0, 30),
                'source' => $answered ? $source : 'fallback',
                'provider' => $provider,
                'answered' => $answered,
                'ms' => (int) round((microtime(true) - $started) * 1000),
            ])->id;
        }

        return [
            'reply' => $answer['reply'],
            'actions' => $answer['actions'] ?? [],
            'cards' => $answer['cards'] ?? [],
            'intent' => $answer['intent'],
            'source' => $answered ? $source : 'fallback',
            'provider' => $provider,
            'provider_label' => match (true) {
                ! $provider => null,
                $provider === 'endpoint' => 'Layanan AI',
                default => config("services.mentari.ai.providers.$provider.label"),
            },
            'log_id' => $logId,
        ];
    }

    /** Layanan AI lama (kontrak sama dengan Mentari MekanikPintar, docs/mentari.md). */
    private function askEndpoint(string $message, array $history, array $context): ?array
    {
        try {
            $response = Http::withToken((string) config('services.mentari.token'))
                ->acceptJson()->timeout((int) config('services.mentari.timeout', 25))
                ->post((string) config('services.mentari.endpoint'), [
                    'message' => $message,
                    'history' => $history,
                    'context' => $context,
                    'help_whatsapp' => Setting::whatsappNumber(),
                    'app_url' => config('app.url'),
                ]);

            $reply = $response->successful() ? trim((string) $response->json('reply')) : '';
            if ($reply === '') {
                return null;
            }

            $base = rtrim((string) config('app.url'), '/').'/';
            $actions = collect($response->json('actions') ?? [])
                ->filter(fn ($a) => is_array($a) && is_string($a['label'] ?? null) && is_string($a['href'] ?? null))
                ->filter(fn ($a) => (str_starts_with($a['href'], '/') && ! str_starts_with($a['href'], '//'))
                    || str_starts_with($a['href'], $base)
                    || str_starts_with($a['href'], 'https://wa.me/'))
                ->take(3)
                ->map(fn ($a) => ['label' => mb_substr($a['label'], 0, 60), 'href' => $a['href'], 'external' => str_starts_with($a['href'], 'https://wa.me/')])
                ->values()->all();

            return ['reply' => mb_substr($reply, 0, 4000), 'actions' => $actions];
        } catch (Throwable $e) {
            Log::warning('Mentari AI gagal: '.Str::limit($e->getMessage(), 200));

            return null;
        }
    }
}
