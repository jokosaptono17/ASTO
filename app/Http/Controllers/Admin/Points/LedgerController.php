<?php

namespace App\Http\Controllers\Admin\Points;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\Points\PointService;
use App\Services\Spreadsheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Buku besar poin, ekspor, penyesuaian manual, dan aturan poin otomatis. */
class LedgerController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.points.ledger', [
            'transactions' => $this->query($request)->with('user', 'creator')->latest('id')->paginate(30)->withQueryString(),
            'filters' => $request->only(['q', 'kind', 'from', 'to']),
            'sum' => [
                'in' => (int) $this->query($request)->where('amount', '>', 0)->sum('amount'),
                'out' => (int) -$this->query($request)->where('amount', '<', 0)->sum('amount'),
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $rows = [['TANGGAL', 'JAM', 'NRP', 'NAMA', 'DEPARTEMEN', 'JENIS', 'KETERANGAN', 'POIN', 'SALDO SETELAH', 'OLEH']];
        $this->query($request)->with('user', 'creator')->orderBy('id')->chunk(500, function ($chunk) use (&$rows) {
            foreach ($chunk as $t) {
                $rows[] = [$t->created_at->format('d/m/Y'), $t->created_at->format('H:i'), $t->user?->nrp, $t->user?->name, $t->user?->department,
                    PointTransaction::KINDS[$t->kind]['label'] ?? $t->kind, $t->description, $t->amount, $t->balance_after, $t->creator?->displayName() ?? 'Sistem'];
            }
        });
        $path = tempnam(sys_get_temp_dir(), 'poin').'.xlsx';
        Spreadsheet::writeXlsx($path, $rows, 'Poin HEBAT', [12, 8, 12, 30, 22, 14, 44, 9, 13, 20]);

        return response()->download($path, 'poin-hebat-'.now()->format('Ymd-Hi').'.xlsx')->deleteFileAfterSend();
    }

    /** Penyesuaian manual (Administrator) — wajib alasan, tercatat di buku besar. */
    public function adjust(Request $request, PointService $points): RedirectResponse
    {
        $data = $request->validate([
            'nrp' => ['required', 'string'],
            'amount' => ['required', 'integer', 'not_in:0', 'between:-100000,100000'],
            'reason' => ['required', 'string', 'max:150'],
        ], ['amount.not_in' => 'Jumlah tidak boleh 0.', 'reason.required' => 'Tulis alasan penyesuaian.'], ['amount' => 'jumlah']);
        $user = User::where('nrp', Str::upper(trim($data['nrp'])))->first();
        if (! $user) {
            return back()->withInput()->withErrors(['nrp' => 'NRP tidak ditemukan.']);
        }
        $tx = $points->post($user, (int) $data['amount'], 'adjust', 'Penyesuaian: '.$data['reason'], null, null, null, $request->user());
        ActivityLog::record('poin', "Penyesuaian {$data['amount']} poin untuk {$user->displayName()} ({$user->nrp}): {$data['reason']}");

        return back()->with('toast', $tx ? "Saldo {$user->displayName()} sekarang ".number_format($tx->balance_after, 0, ',', '.').' poin.' : 'Tidak ada perubahan (saldo tidak bisa minus).');
    }

    public function rules(): View
    {
        return view('admin.points.rules', [
            'rules' => PointRule::orderBy('sort_order')->get(),
            'threshold' => GrantController::threshold(),
            'levels' => PointService::LEVELS,
        ]);
    }

    public function saveRules(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rules' => ['array'],
            'rules.*.points' => ['required', 'integer', 'min:0', 'max:10000'],
            'threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);
        foreach (PointRule::all() as $rule) {
            $in = $data['rules'][$rule->key] ?? null;
            if ($in !== null) {
                $rule->update(['points' => $in['points'], 'is_active' => $request->boolean("rules.{$rule->key}.active")]);
            }
        }
        Setting::put(['points_approval_threshold' => (string) $data['threshold']]);
        ActivityLog::record('poin', 'Mengubah aturan poin otomatis & batas persetujuan');

        return back()->with('toast', 'Aturan poin disimpan.');
    }

    private function query(Request $request): Builder
    {
        $q = trim((string) $request->query('q'));

        return PointTransaction::query()
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('description', 'like', "%$q%")
                ->orWhereHas('user', fn ($u) => $u->where('nrp', 'like', '%'.Str::upper($q).'%')->orWhere('name', 'like', '%'.Str::upper($q).'%'))))
            ->when(array_key_exists($request->query('kind'), PointTransaction::KINDS), fn ($w) => $w->where('kind', $request->query('kind')))
            ->when($request->query('from'), fn ($w, $d) => $w->where('created_at', '>=', $d))
            ->when($request->query('to'), fn ($w, $d) => $w->where('created_at', '<', \Illuminate\Support\Carbon::parse($d)->addDay()));
    }
}
