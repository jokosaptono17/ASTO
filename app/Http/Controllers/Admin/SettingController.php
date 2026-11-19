<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', [
            'values' => collect(Setting::DEFAULTS)->mapWithKeys(fn ($v, $k) => [$k => Setting::get($k)]),
            'hero' => Setting::hero(),
            'heroCustom' => (bool) Setting::get('hero_image'),
        ]);
    }

    public function update(Request $request, ImageService $images): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:40'],
            'site_tagline' => ['required', 'string', 'max:80'],
            'slogan' => ['required', 'string', 'max:80'],
            'help_whatsapp' => ['required', 'regex:/^[0-9+\s-]{9,20}$/'],
            'help_message' => ['required', 'string', 'max:200'],
            'mentari_greeting' => ['required', 'string', 'max:300'],
            'campaign_section_title' => ['required', 'string', 'max:40'],
            'hero' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'hero_reset' => ['nullable', 'boolean'],
        ], ['help_whatsapp.regex' => 'Nomor WhatsApp tidak valid.', 'hero.max' => 'Foto maksimal 8 MB.'], ['hero' => 'foto latar']);

        // Foto latar hero: unggah baru atau kembalikan ke bawaan
        $old = Setting::get('hero_image');
        if ($request->hasFile('hero')) {
            $images->delete($old);
            $data['hero_image'] = $images->storeCover($request->file('hero'), 'hero', 2400, 1200);
        } elseif ($request->boolean('hero_reset') && $old) {
            $images->delete($old);
            $data['hero_image'] = null;
        }
        unset($data['hero'], $data['hero_reset']);

        Setting::put($data);
        ActivityLog::record('pengaturan', 'Mengubah pengaturan portal');

        return back()->with('toast', 'Pengaturan disimpan.');
    }
}
