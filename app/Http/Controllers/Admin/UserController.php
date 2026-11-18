<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Spreadsheet;
use App\Services\UserImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('nrp', 'like', "%$s%")->orWhere('name', 'like', "%$s%")->orWhere('department', 'like', "%$s%")))
            ->when($request->query('role'), fn ($q, $r) => $q->where('role', $r))
            ->when($request->query('status'), fn ($q, $s) => match ($s) {
                'aktif' => $q->where('is_active', true)->whereNotNull('pin'),
                'belum' => $q->where('is_active', true)->whereNull('pin'),
                'nonaktif' => $q->where('is_active', false),
                default => $q,
            });

        return view('admin.users.index', [
            'users' => $query->orderBy('name')->paginate(25)->withQueryString(),
            'counts' => [
                'all' => User::count(),
                'activated' => User::whereNotNull('pin')->count(),
                'pending' => User::where('is_active', true)->whereNull('pin')->count(),
                'inactive' => User::where('is_active', false)->count(),
            ],
            'departments' => User::whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User(['role' => 'user', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = User::create($this->validated($request));
        ActivityLog::record('pengguna', "Menambah pengguna {$user->name} ({$user->nrp})");

        return redirect()->route('admin.users.index')->with('toast', "{$user->displayName()} ditambahkan. Ia bisa aktivasi dengan NRP {$user->nrp}.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        if ($user->is($request->user()) && ($data['role'] !== 'admin' || ! $data['is_active'])) {
            return back()->withInput()->withErrors(['role' => 'Anda tidak bisa menurunkan peran atau menonaktifkan akun sendiri.']);
        }
        $user->update($data);
        ActivityLog::record('pengguna', "Mengubah data {$user->name} ({$user->nrp})");

        return redirect()->route('admin.users.index')->with('toast', 'Data pengguna disimpan.');
    }

    public function resetPin(User $user): RedirectResponse
    {
        $user->forceFill(['pin' => null, 'activated_at' => null, 'remember_token' => null])->save();
        ActivityLog::record('pengguna', "Mereset PIN {$user->name} ({$user->nrp})");

        return back()->with('toast', "PIN {$user->displayName()} direset. Minta ia masuk dengan NRP lalu membuat PIN baru.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'Tidak bisa menghapus akun sendiri.');
        $user->delete();
        ActivityLog::record('pengguna', "Menghapus pengguna {$user->name} ({$user->nrp})");

        return redirect()->route('admin.users.index')->with('toast', 'Pengguna dihapus.');
    }

    public function import(Request $request, UserImporter $importer): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120']], [], ['file' => 'file']);
        $file = $request->file('file');

        try {
            $rows = Spreadsheet::read($file->getRealPath(), $file->getClientOriginalExtension());
            $result = $importer->import($rows, $request->boolean('deactivate_missing'));
        } catch (Throwable $e) {
            return back()->withErrors(['file' => 'File tidak bisa dibaca: '.$e->getMessage()]);
        }

        ActivityLog::record('pengguna', "Impor data karyawan: {$result['created']} baru, {$result['updated']} diperbarui");

        return back()->with('import', $result)->with('toast', "Impor selesai: {$result['created']} baru, {$result['updated']} diperbarui.");
    }

    public function template(): BinaryFileResponse
    {
        $path = storage_path('app/private/template-karyawan-astoone.xlsx');
        @mkdir(dirname($path), 0775, true);
        Spreadsheet::writeXlsx($path, [
            ['NRP', 'NAMA', 'DEPARTEMEN', 'JABATAN', 'NO HP'],
            ['KT17163', 'AHMAD FAUZI', 'PLANT', 'MEKANIK', '081234567890'],
        ], 'Karyawan', [14, 32, 20, 20, 18]);

        return response()->download($path, 'template-karyawan-astoone.xlsx');
    }

    public function export(): BinaryFileResponse
    {
        $rows = [['NRP', 'NAMA', 'DEPARTEMEN', 'JABATAN', 'NO HP', 'PERAN', 'STATUS', 'LOGIN TERAKHIR']];
        foreach (User::orderBy('name')->get() as $u) {
            $rows[] = [$u->nrp, $u->name, $u->department, $u->position, $u->phone, $u->roleLabel(),
                ! $u->is_active ? 'Nonaktif' : ($u->isActivated() ? 'Aktif' : 'Belum aktivasi'),
                $u->last_login_at?->format('Y-m-d H:i') ?? '-'];
        }
        $path = storage_path('app/private/karyawan-astoone-'.now()->format('Ymd-His').'.xlsx');
        @mkdir(dirname($path), 0775, true);
        Spreadsheet::writeXlsx($path, $rows, 'Karyawan', [14, 32, 20, 20, 18, 16, 16, 18]);

        return response()->download($path)->deleteFileAfterSend();
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['nrp' => User::normalizeNrp($request->input('nrp'))]);
        $data = $request->validate([
            'nrp' => ['required', 'string', 'max:30', Rule::unique('users', 'nrp')->ignore($user?->id)],
            'name' => ['required', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
        ], ['nrp.unique' => 'NRP ini sudah terdaftar.'], ['name' => 'nama', 'role' => 'peran']);

        return $data + ['is_active' => $request->boolean('is_active')];
    }
}
