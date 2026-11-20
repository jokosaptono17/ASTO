<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Mencatat pop-up kampanye: dilihat, dikonfirmasi dibaca, atau tidak ingin ditampilkan lagi. */
class PopupController extends Controller
{
    public function seen(Request $request, Campaign $campaign): JsonResponse
    {
        abort_unless($campaign->isPopup(), 404);
        $view = CampaignView::firstOrNew(['campaign_id' => $campaign->id, 'user_id' => $request->user()->id]);
        $view->first_seen_at ??= now();
        $view->last_seen_at = now();
        $view->views = ($view->views ?? 0) + 1;
        $view->save();

        return response()->json(['ok' => true]);
    }

    public function close(Request $request, Campaign $campaign): JsonResponse
    {
        abort_unless($campaign->isPopup(), 404);
        $action = $request->validate(['action' => ['required', 'in:ack,dismiss,close']])['action'];
        $view = CampaignView::firstOrNew(['campaign_id' => $campaign->id, 'user_id' => $request->user()->id]);
        $view->first_seen_at ??= now();
        $view->last_seen_at ??= now();
        $view->views ??= 1;
        if ($action === 'ack') {
            $view->acknowledged_at ??= now();
        } elseif ($action === 'dismiss' && ! $campaign->popup_ack) {
            $view->dismissed_at = now();
        }
        $view->save();

        return response()->json(['ok' => true]);
    }
}
