<?php

namespace App\Http\Controllers\Admin\Points;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Reward;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Katalog hadiah (disediakan tim admin). */
class RewardController extends Controller
{
    public function index(): View
    {
        return view('admin.points.rewards', [
            'rewards' => Reward::withCount([
                'redemptions as taken' => fn ($q) => $q->where('status', '!=', 'cancelled'),
                'redemptions as waiting' => fn ($q) => $q->where('status', 'pending'),
            ])->orderByDesc('is_active')->orderBy('sort_order')->orderBy('cost')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.points.reward-form', ['reward' => new Reward(['category' => 'merchandise', 'is_active' => true, 'cost' => 100])]);
    }

    public function store(Request $request, ImageService $images): RedirectResponse
    {
        $reward = new Reward;
        $reward->created_by = $request->user()->id;
        $this->save($request, $reward, $images);
        ActivityLog::record('poin', "Menambah hadiah \"{$reward->name}\" ({$reward->cost} poin)");

        return redirect()->route('admin.points.rewards.index')->with('toast', 'Hadiah ditambahkan ke katalog.');
    }

    public function edit(Reward $reward): View
    {
        return view('admin.points.reward-form', ['reward' => $reward]);
    }

    public function update(Request $request, Reward $reward, ImageService $images): RedirectResponse
    {
        $this->save($request, $reward, $images);
        ActivityLog::record('poin', "Mengubah hadiah \"{$reward->name}\"");

        return redirect()->route('admin.points.rewards.index')->with('toast', 'Hadiah disimpan.');
    }

    public function destroy(Reward $reward, ImageService $images): RedirectResponse
    {
        if ($reward->redemptions()->exists()) {
            $reward->update(['is_active' => false]);
            $msg = 'Hadiah dinonaktifkan (sudah pernah ditukar, riwayat tetap tersimpan).';
        } else {
            $images->delete($reward->image_path);
            $reward->delete();
            $msg = 'Hadiah dihapus.';
        }
        ActivityLog::record('poin', $msg.' — '.$reward->name);

        return redirect()->route('admin.points.rewards.index')->with('toast', $msg);
    }

    private function save(Request $request, Reward $reward, ImageService $images): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', Rule::in(array_keys(Reward::CATEGORIES))],
            'cost' => ['required', 'integer', 'min:1', 'max:1000000'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'per_user_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'pickup_note' => ['nullable', 'string', 'max:200'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], ['name.required' => 'Beri nama hadiah.', 'cost.min' => 'Harga minimal 1 poin.'], ['name' => 'nama', 'cost' => 'harga', 'stock' => 'stok']);

        $reward->fill([
            'name' => $data['name'], 'description' => $data['description'] ?? null, 'category' => $data['category'],
            'cost' => $data['cost'], 'stock' => $data['stock'] ?? null, 'per_user_limit' => $data['per_user_limit'] ?? null,
            'pickup_note' => $data['pickup_note'] ?? null, 'sort_order' => $data['sort_order'] ?? 0, 'is_active' => $request->boolean('is_active'),
        ]);
        if ($request->hasFile('image')) {
            try {
                $path = $images->storeCover($request->file('image'), 'rewards', 800, 800);
            } catch (RuntimeException $e) {
                back()->withInput()->withErrors(['image' => $e->getMessage()])->throwResponse();
            }
            $images->delete($reward->image_path);
            $reward->image_path = $path;
        }
        $reward->save();
    }
}
