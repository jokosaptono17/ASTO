<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $campaigns = Campaign::with('creator', 'editor')->orderBy('sort_order')->latest('id')->get()
            ->when($status, fn ($c) => $c->filter(fn ($x) => $x->status() === $status)->values());

        return view('admin.campaigns.index', ['campaigns' => $campaigns, 'status' => $status]);
    }

    public function create(): View
    {
        return view('admin.campaigns.form', ['campaign' => new Campaign(['format' => 'card', 'is_active' => true, 'starts_at' => now()->startOfDay()])]);
    }

    public function store(Request $request, ImageService $images): RedirectResponse
    {
        $campaign = new Campaign;
        $campaign->created_by = $request->user()->id;
        $this->save($request, $campaign, $images);
        ActivityLog::record('kampanye', "Menambah kampanye \"{$campaign->title}\"");

        return redirect()->route('admin.campaigns.index')->with('toast', 'Kampanye disimpan.');
    }

    public function edit(Campaign $campaign): View
    {
        return view('admin.campaigns.form', ['campaign' => $campaign]);
    }

    public function update(Request $request, Campaign $campaign, ImageService $images): RedirectResponse
    {
        $this->save($request, $campaign, $images);
        ActivityLog::record('kampanye', "Mengubah kampanye \"{$campaign->title}\"");

        return redirect()->route('admin.campaigns.index')->with('toast', 'Kampanye disimpan.');
    }

    public function toggle(Campaign $campaign): RedirectResponse
    {
        $campaign->update(['is_active' => ! $campaign->is_active]);
        Cache::forget('mentari:portal');
        ActivityLog::record('kampanye', ($campaign->is_active ? 'Mengaktifkan' : 'Menonaktifkan')." kampanye \"{$campaign->title}\"");

        return back()->with('toast', $campaign->is_active ? 'Kampanye diaktifkan.' : 'Kampanye dinonaktifkan.');
    }

    public function destroy(Campaign $campaign, ImageService $images): RedirectResponse
    {
        $images->delete($campaign->image_path);
        $campaign->delete();
        Cache::forget('mentari:portal');
        ActivityLog::record('kampanye', "Menghapus kampanye \"{$campaign->title}\"");

        return redirect()->route('admin.campaigns.index')->with('toast', 'Kampanye dihapus.');
    }

    private function save(Request $request, Campaign $campaign, ImageService $images): void
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'format' => ['required', Rule::in(array_keys(Campaign::FORMATS))],
            'image' => [$campaign->exists ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'link_url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/(?!/))#'],
            'link_label' => ['nullable', 'string', 'max:40'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], ['link_url.regex' => 'Tautan harus diawali https:// atau / (halaman portal).', 'ends_at.after_or_equal' => 'Tanggal selesai harus setelah tanggal mulai.'], [
            'title' => 'judul', 'image' => 'gambar', 'starts_at' => 'tanggal mulai', 'ends_at' => 'tanggal selesai',
        ]);

        $formatChanged = $campaign->exists && $campaign->format !== $data['format'];
        if ($formatChanged && ! $request->hasFile('image')) {
            back()->withInput()->withErrors(['image' => 'Format berubah — unggah ulang gambar agar dipotong sesuai rasio baru.'])->throwResponse();
        }

        $campaign->fill([
            'title' => $data['title'],
            'subtitle' => $data['subtitle'] ?? null,
            'format' => $data['format'],
            'link_url' => $data['link_url'] ?? null,
            'link_label' => $data['link_label'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => isset($data['ends_at']) ? \Illuminate\Support\Carbon::parse($data['ends_at'])->endOfDay() : null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
        $campaign->updated_by = $request->user()->id;

        if ($request->hasFile('image')) {
            $size = Campaign::FORMATS[$data['format']];
            try {
                $path = $images->storeCover($request->file('image'), 'campaigns', $size['width'], $size['height']);
            } catch (RuntimeException $e) {
                back()->withInput()->withErrors(['image' => $e->getMessage()])->throwResponse();
            }
            $images->delete($campaign->image_path);
            $campaign->image_path = $path;
        }

        $campaign->save();
        Cache::forget('mentari:portal');
    }
}
