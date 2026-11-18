<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\Mentari\MentariContext;
use App\Services\Mentari\MentariGuide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Chat Mentari.
 * 1. MentariGuide menjawab pertanyaan berstruktur dari data asli (kartu karyawan,
 *    aplikasi, training LMS, statistik).
 * 2. Obrolan bebas diteruskan ke layanan AI bila MENTARI_ENDPOINT diatur
 *    (kontrak sama dengan MekanikPintar, lihat docs/mentari.md); bila tidak, aturan bawaan.
 */
class MentariController extends Controller
{
    public function chat(Request $request, MentariContext $contexts, MentariGuide $guide): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:12'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
            'page' => ['nullable', 'string', 'max:60'],
        ]);

        $user = $request->user();
        $context = $contexts->for($user);
        $context['page'] = $data['page'] ?? null;

        // Pertanyaan berstruktur (NRP, data saya, aplikasi, statistik) dijawab dari data asli.
        // Hanya obrolan bebas yang diteruskan ke AI bila tersambung.
        $answer = $guide->answer($data['message'], $context, $user);
        if (in_array($answer['intent'], MentariGuide::AI_INTENTS, true) && ($endpoint = config('services.mentari.endpoint'))) {
            if ($ai = $this->askAi($endpoint, $data, $context)) {
                return response()->json($ai + ['cards' => [], 'source' => 'ai']);
            }
        }

        return response()->json($answer + ['source' => 'guide']);
    }

    private function askAi(string $endpoint, array $data, array $context): ?array
    {
        try {
            $response = Http::withToken((string) config('services.mentari.token'))
                ->acceptJson()->timeout((int) config('services.mentari.timeout', 25))
                ->post($endpoint, [
                    'message' => $data['message'],
                    'history' => $data['history'] ?? [],
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
            Log::warning('Mentari AI gagal: '.$e->getMessage());

            return null;
        }
    }
}
