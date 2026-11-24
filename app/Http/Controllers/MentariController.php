<?php

namespace App\Http\Controllers;

use App\Models\MentariLog;
use App\Services\Mentari\MentariBrain;
use App\Services\Mentari\Memory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chat Mentari. Seluruh alur berpikir ada di MentariBrain:
 * ingatan percakapan → otak bawaan (data asli) → pengetahuan → AI gratis → layanan AI lama → cadangan.
 */
class MentariController extends Controller
{
    public function chat(Request $request, MentariBrain $brain): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:12'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
            'page' => ['nullable', 'string', 'max:60'],
        ]);

        $user = $request->user();
        $answer = $brain->respond(
            $data['message'],
            $data['history'] ?? [],
            $user,
            $data['page'] ?? null,
            $user ? new Memory($request->session()) : null,
        );

        return response()->json($answer);
    }

    /** 👍 / 👎 dari pengguna untuk satu jawaban. */
    public function feedback(Request $request): JsonResponse
    {
        $data = $request->validate(['log_id' => ['required', 'integer'], 'helpful' => ['required', 'boolean']]);
        $log = MentariLog::whereKey($data['log_id'])->where('user_id', $request->user()->id)->firstOrFail();
        $log->update(['helpful' => (bool) $data['helpful']]);

        return response()->json(['ok' => true]);
    }
}
