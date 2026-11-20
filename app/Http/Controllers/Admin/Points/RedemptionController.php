<?php

namespace App\Http\Controllers\Admin\Points;

use App\Http\Controllers\Controller;
use App\Models\Redemption;
use App\Services\Points\PointException;
use App\Services\Points\PointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Penyerahan hadiah: scan / ketik kode kupon → serahkan atau batalkan. */
class RedemptionController extends Controller
{
    public function index(Request $request): View
    {
        $status = array_key_exists($request->query('status'), Redemption::STATUSES) ? $request->query('status') : 'pending';
        $q = trim((string) $request->query('q'));

        return view('admin.points.redemptions', [
            'redemptions' => Redemption::with('user', 'reward', 'handler')->where('status', $status)
                ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('code', 'like', '%'.Str::upper($q).'%')
                    ->orWhereHas('user', fn ($u) => $u->where('nrp', 'like', '%'.Str::upper($q).'%')->orWhere('name', 'like', '%'.Str::upper($q).'%'))))
                ->latest($status === 'pending' ? 'created_at' : 'updated_at')->paginate(25)->withQueryString(),
            'status' => $status,
            'q' => $q,
            'counts' => Redemption::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    /** Ketik kode kupon → buka detailnya. */
    public function lookup(Request $request): RedirectResponse
    {
        $code = Str::upper(trim((string) $request->input('code')));
        $r = Redemption::where('code', $code)->first();

        return $r ? redirect()->route('admin.points.redemptions.show', $r) : back()->withErrors(['code' => "Kupon {$code} tidak ditemukan."])->withInput();
    }

    public function show(Redemption $redemption): View
    {
        return view('admin.points.redemption', ['r' => $redemption->load('user', 'reward', 'handler')]);
    }

    public function fulfill(Request $request, Redemption $redemption, PointService $points): RedirectResponse
    {
        try {
            $points->fulfill($redemption, $request->user());
        } catch (PointException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return back()->with('toast', "{$redemption->reward->name} diserahkan ke {$redemption->user->displayName()}.");
    }

    public function cancel(Request $request, Redemption $redemption, PointService $points): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:200']], ['reason.required' => 'Tulis alasan pembatalan.'])['reason'];
        try {
            $points->cancel($redemption, $request->user(), $reason);
        } catch (PointException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return back()->with('toast', 'Kupon dibatalkan. Poin dikembalikan ke karyawan.');
    }
}
