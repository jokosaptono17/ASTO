<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AppLink;
use App\Models\Campaign;
use App\Models\Setting;
use App\Http\Controllers\AttendanceController;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $apps = AppLink::active()->ordered()->with('category')->get();

        $recentIds = $user->visits()->latest('created_at')->limit(40)->pluck('app_id')->unique()->take(6);
        $recent = $recentIds->map(fn ($id) => $apps->firstWhere('id', $id))->filter()->values();

        $campaigns = Campaign::live()->get();
        $announcements = Announcement::live()->take(10)->get();

        return view('portal.home', [
            'user' => $user,
            'featured' => $apps->where('placement', 'featured')->take(5)->values(),
            'shortcuts' => $apps->where('placement', 'grid')->take(4)->values(),
            'moreCount' => max(0, $apps->count() - min(5, $apps->where('placement', 'featured')->count()) - min(4, $apps->where('placement', 'grid')->count())),
            'recent' => $recent,
            'favorites' => $user->favorites()->active()->ordered()->get(),
            'banners' => $campaigns->where('format', 'banner')->values(),
            'cards' => $campaigns->where('format', 'card')->values(),
            'ticker' => $announcements->first(fn ($a) => $a->level !== 'info' || $a->is_pinned),
            'unread' => $this->unread($user, $announcements),
            'searchIndex' => $apps->map->toClient()->values(),
            'campaignTitle' => Setting::get('campaign_section_title'),
            'attendance' => AttendanceController::todayFor($user),
            'categories' => $apps->pluck('category')->filter()->unique('id')->sortBy('sort_order')->values(),
            'announcements' => $announcements->take(4),
            'hero' => Setting::hero(),
            'points' => [
                'balance' => (int) $user->points_balance,
                'level' => \App\Services\Points\PointService::level($user),
                'monthIn' => (int) \App\Models\PointTransaction::where('user_id', $user->id)->where('amount', '>', 0)->whereIn('kind', ['earn', 'grant', 'adjust'])->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
                'coupons' => \App\Models\Redemption::where('user_id', $user->id)->where('status', 'pending')->count(),
            ],
            // Pop-up kampanye; admin bisa pratinjau dengan ?pratinjau-popup={id}
            'popup' => ($preview = $request->query('pratinjau-popup')) && $user->canManageContent()
                ? Campaign::where('format', 'popup')->find($preview)
                : Campaign::popupFor($user),
            'popupPreview' => (bool) ($preview ?? false),
        ]);
    }

    public static function unread($user, $announcements = null): int
    {
        $announcements ??= Announcement::live()->take(20)->get();

        return $announcements->filter(fn ($a) => ! $user->announcements_seen_at || $a->publishedAt()->gt($user->announcements_seen_at))->count();
    }
}
