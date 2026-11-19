<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AppLink;
use App\Models\Category;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppLinkController extends Controller
{
    public function index(\Illuminate\Http\Request $request): View
    {
        return view('admin.apps.index', [
            'apps' => AppLink::with('category')
                ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('url', 'like', "%$s%")->orWhere('tagline', 'like', "%$s%")))
                ->orderByRaw("CASE placement WHEN 'featured' THEN 0 WHEN 'grid' THEN 1 ELSE 2 END")->ordered()->get(),
            'categories' => Category::orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.apps.form', ['app' => new AppLink(['color' => '#3BFF8A', 'icon' => 'ri-apps-2-fill', 'placement' => 'list', 'new_tab' => true, 'is_active' => true, 'health_check' => true]), 'categories' => Category::orderBy('sort_order')->get()]);
    }

    public function store(Request $request, ImageService $images): RedirectResponse
    {
        $app = new AppLink;
        $this->save($request, $app, $images);
        ActivityLog::record('aplikasi', "Menambah aplikasi {$app->name}");

        return redirect()->route('admin.apps.index')->with('toast', "Aplikasi {$app->name} ditambahkan.");
    }

    public function edit(AppLink $app): View
    {
        return view('admin.apps.form', ['app' => $app, 'categories' => Category::orderBy('sort_order')->get()]);
    }

    public function update(Request $request, AppLink $app, ImageService $images): RedirectResponse
    {
        $this->save($request, $app, $images);
        ActivityLog::record('aplikasi', "Mengubah aplikasi {$app->name}");

        return redirect()->route('admin.apps.index')->with('toast', "Aplikasi {$app->name} disimpan.");
    }

    public function destroy(AppLink $app, ImageService $images): RedirectResponse
    {
        if ($app->icon_type === 'image') {
            $images->delete($app->icon);
        }
        $app->delete();
        Cache::forget('mentari:portal');
        ActivityLog::record('aplikasi', "Menghapus aplikasi {$app->name}");

        return redirect()->route('admin.apps.index')->with('toast', "Aplikasi {$app->name} dihapus.");
    }

    /** Geser urutan dalam kelompok penempatan yang sama. */
    public function move(Request $request, AppLink $app): RedirectResponse
    {
        $direction = $request->input('direction') === 'up' ? 'up' : 'down';
        $siblings = AppLink::where('placement', $app->placement)->ordered()->get()->values();
        $index = $siblings->search(fn ($a) => $a->id === $app->id);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swap >= 0 && $swap < $siblings->count()) {
            $siblings->splice($swap, 0, [$siblings->pull($index)]);
            $siblings->values()->each(fn ($a, $i) => $a->updateQuietly(['sort_order' => ($i + 1) * 10]));
        }
        Cache::forget('mentari:portal');

        return back();
    }

    private function save(Request $request, AppLink $app, ImageService $images): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:3000'],
            'howto' => ['nullable', 'string', 'max:3000'],
            'pic_name' => ['nullable', 'string', 'max:100'],
            'pic_phone' => ['nullable', 'regex:/^[0-9+\s-]{9,20}$/'],
            'url' => ['required', 'url:http,https', 'max:500'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')],
            'placement' => ['required', Rule::in(array_keys(AppLink::PLACEMENTS))],
            'icon_type' => ['required', Rule::in(['remix', 'image'])],
            'icon' => ['nullable', 'required_if:icon_type,remix', 'string', 'max:60', 'regex:/^ri-[a-z0-9-]+$/'],
            'icon_file' => [Rule::requiredIf(fn () => $request->input('icon_type') === 'image' && $app->icon_type !== 'image'), 'nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'badge' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], ['icon.regex' => 'Kode ikon harus berupa nama ikon Remix, contoh ri-tools-fill.', 'pic_phone.regex' => 'Nomor WhatsApp PIC tidak valid.'], [
            'name' => 'nama', 'url' => 'alamat URL', 'icon_file' => 'file ikon',
        ]);

        $oldImage = $app->icon_type === 'image' ? $app->icon : null;
        $app->fill([
            'name' => $data['name'],
            'tagline' => $data['tagline'] ?? null,
            'description' => $data['description'] ?? null,
            'howto' => $data['howto'] ?? null,
            'pic_name' => $data['pic_name'] ?? null,
            'pic_phone' => $data['pic_phone'] ?? null,
            'url' => $data['url'],
            'category_id' => $data['category_id'] ?? null,
            'placement' => $data['placement'],
            'color' => strtoupper($data['color']),
            'badge' => $data['badge'] ?? null,
            'new_tab' => $request->boolean('new_tab'),
            'is_active' => $request->boolean('is_active'),
            'health_check' => $request->boolean('health_check'),
            'sort_order' => $data['sort_order'] ?? ($app->sort_order ?: (AppLink::max('sort_order') + 10)),
        ]);

        if ($data['icon_type'] === 'image') {
            if ($request->hasFile('icon_file')) {
                $app->icon = $images->storeIcon($request->file('icon_file'), 'icons');
                $images->delete($oldImage);
            }
            $app->icon_type = 'image';
        } else {
            $app->icon_type = 'remix';
            $app->icon = $data['icon'];
            $images->delete($oldImage);
        }

        if ($app->isDirty('url')) {
            $app->health_status = 'unknown';
        }
        $app->save();
        Cache::forget('mentari:portal');
    }
}
