<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.announcements.index', ['announcements' => Announcement::with('creator')->orderByDesc('is_pinned')->latest('id')->get()]);
    }

    public function create(): View
    {
        return view('admin.announcements.form', ['announcement' => new Announcement(['level' => 'info', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $announcement = new Announcement($this->validated($request));
        $announcement->created_by = $request->user()->id;
        $announcement->save();
        Cache::forget('mentari:portal');
        ActivityLog::record('pengumuman', "Menerbitkan pengumuman \"{$announcement->title}\"");

        return redirect()->route('admin.announcements.index')->with('toast', 'Pengumuman diterbitkan.');
    }

    public function edit(Announcement $announcement): View
    {
        return view('admin.announcements.form', ['announcement' => $announcement]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->validated($request));
        Cache::forget('mentari:portal');
        ActivityLog::record('pengumuman', "Mengubah pengumuman \"{$announcement->title}\"");

        return redirect()->route('admin.announcements.index')->with('toast', 'Pengumuman disimpan.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();
        Cache::forget('mentari:portal');
        ActivityLog::record('pengumuman', "Menghapus pengumuman \"{$announcement->title}\"");

        return redirect()->route('admin.announcements.index')->with('toast', 'Pengumuman dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:5000'],
            'level' => ['required', Rule::in(array_keys(Announcement::LEVELS))],
            'link_url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/(?!/))#'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ], ['link_url.regex' => 'Tautan harus diawali https:// atau /.'], ['title' => 'judul', 'body' => 'isi']);

        return $data + ['is_pinned' => $request->boolean('is_pinned'), 'is_active' => $request->boolean('is_active')];
    }
}
