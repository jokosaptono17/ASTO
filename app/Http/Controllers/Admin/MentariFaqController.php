<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MentariFaq;
use App\Services\Mentari\MentariContext;
use App\Services\Mentari\MentariGuide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MentariFaqController extends Controller
{
    public function index(): View
    {
        return view('admin.mentari.index', [
            'faqs' => MentariFaq::orderByDesc('hits')->orderBy('question')->get(),
            'aiConnected' => (bool) config('services.mentari.endpoint'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        MentariFaq::create($this->validated($request));

        return back()->with('toast', 'Jawaban Mentari ditambahkan.');
    }

    public function update(Request $request, MentariFaq $faq): RedirectResponse
    {
        $faq->update($this->validated($request));

        return back()->with('toast', 'Jawaban Mentari disimpan.');
    }

    public function destroy(MentariFaq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('toast', 'Jawaban Mentari dihapus.');
    }

    /** Uji jawaban dari panel admin (memakai otak bawaan). */
    public function test(Request $request, MentariContext $contexts, MentariGuide $guide): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:500']]);

        return response()->json($guide->answer($data['message'], $contexts->for($request->user()), $request->user()));
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
