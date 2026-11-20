<?php

namespace App\Http\Controllers\Admin\Points;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PointGrant;
use App\Models\Setting;
use App\Models\User;
use App\Services\Points\PointException;
use App\Services\Points\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Pemberian poin event. Alur: draf → diajukan → disetujui Administrator → poin terkirim.
 * Pengajuan Administrator ≤ batas otomatis langsung terkirim.
 */
class GrantController extends Controller
{
    public function index(Request $request): View
    {
        $status = array_key_exists($request->query('status'), PointGrant::STATUSES) ? $request->query('status') : null;

        return view('admin.points.grants', [
            'grants' => PointGrant::with('creator', 'reviewer')->withCount('recipients')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'draft' THEN 1 WHEN 'rejected' THEN 2 ELSE 3 END")->latest('id')->paginate(20)->withQueryString(),
            'status' => $status,
            'counts' => PointGrant::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'threshold' => self::threshold(),
        ]);
    }

    public function create(): View
    {
        return view('admin.points.grant-form', $this->formData(new PointGrant(['status' => 'draft'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $grant = new PointGrant;
        $grant->created_by = $request->user()->id;

        return $this->save($request, $grant);
    }

    public function show(PointGrant $grant): View
    {
        return view('admin.points.grant-form', $this->formData($grant));
    }

    public function update(Request $request, PointGrant $grant): RedirectResponse
    {
        abort_unless($grant->isEditable(), 403, 'Pemberian poin ini sudah diajukan.');

        return $this->save($request, $grant);
    }

    public function approve(Request $request, PointGrant $grant, PointService $points): RedirectResponse
    {
        $user = $request->user();
        if ($reason = self::cannotApprove($grant, $user)) {
            return back()->withErrors(['review' => $reason]);
        }
        try {
            $points->approveGrant($grant, $user, $request->input('note'));
        } catch (PointException $e) {
            return back()->withErrors(['review' => $e->getMessage()]);
        }

        return redirect()->route('admin.points.grants.show', $grant)->with('toast', "Disetujui. {$grant->total_points} poin terkirim ke {$grant->recipients()->count()} karyawan.");
    }

    public function reject(Request $request, PointGrant $grant): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() && $grant->status === 'pending', 403);
        $note = $request->validate(['note' => ['required', 'string', 'max:200']], ['note.required' => 'Tulis alasan penolakan.'])['note'];
        $grant->forceFill(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
        ActivityLog::record('poin', "Menolak pemberian poin \"{$grant->title}\": {$note}");

        return redirect()->route('admin.points.grants.show', $grant)->with('toast', 'Pemberian poin ditolak.');
    }

    public function destroy(PointGrant $grant): RedirectResponse
    {
        abort_unless($grant->isEditable(), 403);
        $grant->delete();

        return redirect()->route('admin.points.grants.index')->with('toast', 'Draf dihapus.');
    }

    /** Cari karyawan: ?q= (nama/NRP), ?department=, atau ?nrps=KT1,KT2 */
    public function people(Request $request): JsonResponse
    {
        $q = User::where('is_active', true);
        if ($nrps = $request->query('nrps')) {
            $q->whereIn('nrp', array_slice(array_map(fn ($n) => Str::upper(trim($n)), explode(',', $nrps)), 0, 500));
        } elseif ($dept = $request->query('department')) {
            $q->where('department', $dept)->limit(500);
        } else {
            $term = trim((string) $request->query('q'));
            if (mb_strlen($term) < 2) {
                return response()->json([]);
            }
            $q->where(fn ($w) => $w->where('nrp', 'like', '%'.Str::upper($term).'%')->orWhere('name', 'like', '%'.Str::upper($term).'%'))->limit(10);
        }

        return response()->json($q->orderBy('name')->get(['id', 'nrp', 'name', 'department'])
            ->map(fn ($u) => ['id' => $u->id, 'nrp' => $u->nrp, 'name' => $u->displayName(), 'department' => $u->department]));
    }

    public static function threshold(): int
    {
        return max(0, (int) Setting::get('points_approval_threshold', '500'));
    }

    /** Alasan user tidak boleh menyetujui (null = boleh). */
    public static function cannotApprove(PointGrant $grant, User $user): ?string
    {
        if (! $user->isAdmin()) {
            return 'Hanya Administrator yang bisa menyetujui pemberian poin.';
        }
        if ($grant->status !== 'pending') {
            return 'Pemberian poin ini tidak sedang menunggu persetujuan.';
        }
        $otherAdmins = User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $user->id)->exists();
        if ($grant->created_by === $user->id && $otherAdmins) {
            return 'Pengajuan Anda sendiri harus disetujui Administrator lain.';
        }

        return null;
    }

    private function save(Request $request, PointGrant $grant): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'recipients' => ['array', 'max:1000'],
            'recipients.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'recipients.*.points' => ['required', 'integer', 'min:1', 'max:100000'],
            'recipients.*.note' => ['nullable', 'string', 'max:120'],
            'action' => ['required', 'in:draft,submit'],
        ], ['title.required' => 'Beri judul, mis. "Juara Safety Week".', 'recipients.*.points.min' => 'Poin minimal 1.'], ['title' => 'judul']);

        $recipients = collect($data['recipients'] ?? [])->keyBy('user_id');
        if ($data['action'] === 'submit' && $recipients->isEmpty()) {
            return back()->withInput()->withErrors(['recipients' => 'Tambahkan minimal satu penerima.']);
        }

        DB::transaction(function () use ($grant, $data, $recipients) {
            $grant->fill(['title' => $data['title'], 'description' => $data['description'] ?? null]);
            $grant->status = 'draft';
            $grant->save();
            $grant->recipients()->delete();
            $grant->recipients()->createMany($recipients->map(fn ($r) => ['user_id' => $r['user_id'], 'points' => $r['points'], 'note' => $r['note'] ?? null])->values()->all());
            $grant->recalc();
        });

        if ($data['action'] === 'draft') {
            return redirect()->route('admin.points.grants.show', $grant)->with('toast', 'Draf disimpan.');
        }

        $grant->forceFill(['status' => 'pending', 'submitted_at' => now(), 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null])->save();
        ActivityLog::record('poin', "Mengajukan pemberian poin \"{$grant->title}\" ({$grant->total_points} poin)");

        // Administrator dengan total ≤ batas: langsung terkirim
        $user = $request->user();
        if ($user->isAdmin() && $grant->total_points <= self::threshold()) {
            app(PointService::class)->approveGrant($grant, $user, 'Otomatis (≤ batas persetujuan)');

            return redirect()->route('admin.points.grants.show', $grant)->with('toast', "{$grant->total_points} poin langsung terkirim.");
        }

        return redirect()->route('admin.points.grants.show', $grant)->with('toast', 'Diajukan. Menunggu persetujuan Administrator.');
    }

    private function formData(PointGrant $grant): array
    {
        $grant->loadMissing('recipients.user', 'creator', 'reviewer');

        return [
            'grant' => $grant,
            'recipients' => old('recipients')
                ? collect(old('recipients'))->map(fn ($r) => ($u = User::find($r['user_id'])) ? ['id' => $u->id, 'nrp' => $u->nrp, 'name' => $u->displayName(), 'department' => $u->department, 'points' => (int) $r['points'], 'note' => $r['note'] ?? ''] : null)->filter()->values()
                : $grant->recipients->map(fn ($r) => ['id' => $r->user_id, 'nrp' => $r->user->nrp, 'name' => $r->user->displayName(), 'department' => $r->user->department, 'points' => $r->points, 'note' => $r->note ?? ''])->values(),
            'departments' => User::where('is_active', true)->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'threshold' => self::threshold(),
            'approveBlock' => $grant->exists ? self::cannotApprove($grant, request()->user()) : null,
        ];
    }
}
