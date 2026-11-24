<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MentariFaq;
use App\Models\MentariLog;
use App\Models\Setting;
use App\Services\Mentari\Ai\Providers;
use App\Services\Mentari\KnowledgeBase;
use App\Services\Mentari\Language;
use App\Services\Mentari\MentariBrain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MentariFaqController extends Controller
{
    public function index(Providers $providers, KnowledgeBase $kb): View
    {
        $since = now()->subDays(7);
        $week = MentariLog::where('created_at', '>=', $since);
        $total = (clone $week)->count();

        return view('admin.mentari.index', [
            'faqs' => MentariFaq::orderByDesc('hits')->orderBy('question')->get(),
            'aiConnected' => (bool) config('services.mentari.endpoint'),
            'providers' => $providers->status(),
            'aiEnabled' => Setting::get('mentari_ai_enabled', '1') === '1',
            'aiConfigured' => $providers->configured() !== [],
            'perUser' => $providers->perUserLimit(),
            'kbCount' => $kb->documentCount(),
            'week' => [
                'total' => $total,
                'answered' => $total ? (int) round((clone $week)->where('answered', true)->count() / $total * 100) : null,
                'ai' => (clone $week)->where('source', 'ai')->count(),
                'kb' => (clone $week)->where('source', 'knowledge')->count(),
                'up' => (clone $week)->where('helpful', true)->count(),
                'down' => (clone $week)->where('helpful', false)->count(),
                'users' => (clone $week)->whereNotNull('user_id')->distinct()->count('user_id'),
            ],
            'unanswered' => MentariLog::where('answered', false)->whereNull('resolved_at')->where('created_at', '>=', now()->subDays(30))
                ->groupBy('normalized')
                ->select('normalized', DB::raw('count(*) as total'), DB::raw('max(created_at) as last_at'), DB::raw('max(question) as sample'))
                ->orderByDesc('total')->orderByDesc('last_at')->limit(30)->get()
                ->each(fn ($row) => $row->keywords = implode(', ', array_slice(array_unique(app(Language::class)->terms($row->normalized, false)), 0, 6))),
            'unhelpful' => MentariLog::where('helpful', false)->whereNull('resolved_at')->latest('id')->limit(15)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        MentariFaq::create($this->validated($request));
        $this->resolveFromForm($request);
        $this->forgetKnowledge();

        return back()->with('toast', 'Jawaban Mentari ditambahkan. Mentari langsung memakainya.');
    }

    public function update(Request $request, MentariFaq $faq): RedirectResponse
    {
        $faq->update($this->validated($request));
        $this->forgetKnowledge();

        return back()->with('toast', 'Jawaban Mentari disimpan.');
    }

    public function destroy(MentariFaq $faq): RedirectResponse
    {
        $faq->delete();
        $this->forgetKnowledge();

        return back()->with('toast', 'Jawaban Mentari dihapus.');
    }

    /** Uji jawaban dari panel admin — alur yang sama dengan karyawan (tanpa dicatat). */
    public function test(Request $request, MentariBrain $brain): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:500'], 'ai' => ['nullable', 'boolean']]);

        return response()->json($brain->respond($data['message'], [], $request->user(), 'admin', null, log: false, useAi: $request->boolean('ai')));
    }

    /** Hidup/matikan AI dan batas pertanyaan AI per karyawan per hari. */
    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['per_user' => ['required', 'integer', 'min:0', 'max:500']]);
        Setting::put(['mentari_ai_enabled' => $request->boolean('enabled') ? '1' : '0', 'mentari_ai_per_user' => (string) $data['per_user']]);

        return back()->with('toast', $request->boolean('enabled') ? 'AI Mentari aktif.' : 'AI Mentari dimatikan — Mentari memakai otak bawaan saja.');
    }

    /** Tandai pertanyaan belum terjawab / jawaban kurang membantu sebagai sudah ditangani. */
    public function resolve(Request $request): RedirectResponse
    {
        $data = $request->validate(['normalized' => ['nullable', 'string', 'max:300'], 'log_id' => ['nullable', 'integer']]);
        if (filled($data['normalized'] ?? null)) {
            MentariLog::where('normalized', $data['normalized'])->where('answered', false)->update(['resolved_at' => now()]);
        }
        if (filled($data['log_id'] ?? null)) {
            MentariLog::whereKey($data['log_id'])->update(['resolved_at' => now()]);
        }

        return back()->with('toast', 'Ditandai sudah ditangani.');
    }

    private function resolveFromForm(Request $request): void
    {
        if ($request->filled('resolve_normalized')) {
            MentariLog::where('normalized', (string) $request->input('resolve_normalized'))->where('answered', false)->update(['resolved_at' => now()]);
        }
    }

    private function forgetKnowledge(): void
    {
        Cache::forget('mentari:kb');
        Cache::forget('mentari:vocab');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:160'],
            'keywords' => ['required', 'string', 'max:300'],
            'answer' => ['required', 'string', 'max:2000'],
            'link_label' => ['nullable', 'string', 'max:40'],
            'link_url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/(?!/))#'],
        ], [], ['question' => 'pertanyaan', 'keywords' => 'kata kunci', 'answer' => 'jawaban']);

        return $data + ['is_active' => $request->boolean('is_active')];
    }
}
