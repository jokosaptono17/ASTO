<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Campaign;
use App\Models\CampaignClick;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InfoController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $seenAt = $user->announcements_seen_at;
        $user->forceFill(['announcements_seen_at' => now()])->save();

        return view('portal.info', [
            'announcements' => Announcement::live()->get(),
            'campaigns' => Campaign::live()->get(),
            'seenAt' => $seenAt,
        ]);
    }

    public function show(Announcement $announcement): View
    {
        abort_unless($announcement->is_active && (! $announcement->starts_at || $announcement->starts_at->isPast()), 404);

        return view('portal.announcement', ['announcement' => $announcement]);
    }

    public function campaign(Request $request, Campaign $campaign): RedirectResponse|View
    {
        abort_unless($campaign->status() === 'tayang', 404);
        $campaign->increment('clicks');
        CampaignClick::create(['campaign_id' => $campaign->id, 'user_id' => $request->user()->id]);

        if ($campaign->link_url) {
            return str_starts_with($campaign->link_url, '/') ? redirect($campaign->link_url) : redirect()->away($campaign->link_url);
        }

        return view('portal.campaign', ['campaign' => $campaign]);
    }
}
