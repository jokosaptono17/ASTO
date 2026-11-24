<?php

namespace App\Services\Mentari\Ai;

use Illuminate\Support\Facades\Http;
use Throwable;

/** Klien chat-completions yang kompatibel OpenAI (Groq, Gemini, OpenRouter memakai format yang sama). */
class LlmClient
{
    /**
     * @return array{content:?string, tool_calls:array}
     *
     * @throws LlmError
     */
    public function chat(array $provider, array $messages, array $tools = [], bool $reasoning = true): array
    {
        $body = ['model' => $provider['model'], 'messages' => $messages, 'temperature' => 0.3, 'max_tokens' => 900];
        if ($tools) {
            $body['tools'] = $tools;
            $body['tool_choice'] = 'auto';
        }
        if ($reasoning && ! empty($provider['reasoning'])) {
            $body['reasoning_effort'] = $provider['reasoning'];
        }

        try {
            $request = Http::withToken((string) $provider['key'])->acceptJson()->asJson()
                ->connectTimeout(6)->timeout((int) config('services.mentari.ai.timeout', 20));
            if (($provider['name'] ?? '') === 'openrouter') {
                $request = $request->withHeaders(['HTTP-Referer' => (string) config('app.url'), 'X-Title' => 'AstoOne Mentari']);
            }
            $response = $request->post(rtrim($provider['base'], '/').'/chat/completions', $body);
        } catch (Throwable $e) {
            throw new LlmError(0, 'Tidak bisa terhubung: '.$e->getMessage());
        }

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? $response->json('0.error.message') ?? mb_substr($response->body(), 0, 200);
            throw new LlmError($response->status(), (string) $message, (int) ($response->header('Retry-After') ?: 0));
        }

        $msg = $response->json('choices.0.message') ?? [];

        return ['content' => is_string($msg['content'] ?? null) ? $msg['content'] : null, 'tool_calls' => $msg['tool_calls'] ?? []];
    }
}
