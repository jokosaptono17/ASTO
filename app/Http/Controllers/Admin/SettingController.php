<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', [
            'values' => collect(Setting::DEFAULTS)->mapWithKeys(fn ($v, $k) => [$k => Setting::get($k)]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:40'],
            'site_tagline' => ['required', 'string', 'max:80'],
            'slogan' => ['required', 'string', 'max:80'],
            'help_whatsapp' => ['required', 'regex:/^[0-9+\s-]{9,20}$/'],
            'help_message' => ['required', 'string', 'max:200'],
            'mentari_greeting' => ['required', 'string', 'max:300'],
            'campaign_section_title' => ['required', 'string', 'max:40'],
        ], ['help_whatsapp.regex' => 'Nomor WhatsApp tidak valid.']);

        Setting::put($data);
        ActivityLog::record('pengaturan', 'Mengubah pengaturan portal');

        return back()->with('toast', 'Pengaturan disimpan.');
    }
}
