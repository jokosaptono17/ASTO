<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Impor data karyawan dari Excel/CSV.
 * Kolom wajib: NRP, NAMA. Opsional: DEPARTEMEN, JABATAN, NO HP.
 * NRP yang sudah ada diperbarui (PIN tidak disentuh), NRP baru dibuat.
 */
class UserImporter
{
    private const ALIASES = [
        'nrp' => ['nrp', 'no nrp', 'nik', 'id karyawan'],
        'name' => ['nama', 'name', 'nama lengkap', 'nama karyawan'],
        'department' => ['departemen', 'department', 'dept', 'section', 'seksi', 'bagian'],
        'position' => ['jabatan', 'position', 'posisi', 'grade'],
        'phone' => ['no hp', 'hp', 'telepon', 'no telepon', 'phone', 'whatsapp', 'no wa'],
        'grade' => ['grade', 'grade26', 'grade 2026', 'golongan'],
        'supervisor' => ['gl wali', 'gl wali new', 'wali', 'atasan', 'gl'],
        'location' => ['lokasi', 'location', 'area', 'lokasi kerja'],
        'program' => ['program', 'status program', 'idp map'],
    ];

    /**
     * @param  list<list<string>>  $rows
     * @return array{created:int, updated:int, skipped:list<string>}
     */
    public function import(array $rows, bool $deactivateMissing = false): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => []];

        // Cari baris judul di 5 baris pertama
        $map = null;
        foreach (array_slice($rows, 0, 5, true) as $i => $row) {
            $candidate = $this->mapHeader($row);
            if (isset($candidate['nrp'], $candidate['name'])) {
                $map = $candidate;
                $rows = array_slice($rows, $i + 1);
                break;
            }
        }
        if (! $map) {
            $result['skipped'][] = 'Judul kolom NRP dan NAMA tidak ditemukan. Gunakan template.';

            return $result;
        }

        $seen = [];
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $nrp = User::normalizeNrp($row[$map['nrp']] ?? '');
            $name = trim($row[$map['name']] ?? '');
            if ($nrp === '' && $name === '') {
                continue;
            }
            if ($nrp === '' || $name === '') {
                $result['skipped'][] = "Baris $line: NRP atau nama kosong.";

                continue;
            }
            if (strlen($nrp) > 30) {
                $result['skipped'][] = "Baris $line: NRP terlalu panjang.";

                continue;
            }

            $data = ['name' => $name, 'is_active' => true];
            foreach (['department', 'position', 'phone', 'grade', 'supervisor', 'location', 'program'] as $field) {
                if (isset($map[$field]) && ($value = trim($row[$map[$field]] ?? '')) !== '') {
                    // Grade angka dari Excel (7) disimpan sebagai M7
                    $data[$field] = $field === 'grade' && ctype_digit($value) ? 'M'.$value : $value;
                }
            }

            $user = User::firstWhere('nrp', $nrp);
            if ($user) {
                $user->fill($data)->save();
                $result['updated']++;
            } else {
                User::create(['nrp' => $nrp, 'role' => 'user'] + $data);
                $result['created']++;
            }
            $seen[] = $nrp;
        }

        // Cocokkan GL wali dengan NRP setelah semua karyawan masuk
        if ($seen && isset($map['supervisor'])) {
            $candidates = User::where('is_active', true)->get(['nrp', 'name']);
            User::whereIn('nrp', $seen)->whereNotNull('supervisor')->get()
                ->each(function (User $u) use ($candidates) {
                    $u->resolveSupervisor($candidates);
                    $u->saveQuietly();
                });
        }

        if ($deactivateMissing && $seen) {
            User::where('role', 'user')->whereNotIn('nrp', $seen)->update(['is_active' => false]);
        }

        return $result;
    }

    private function mapHeader(array $row): array
    {
        $map = [];
        foreach ($row as $i => $cell) {
            $label = Str::of($cell)->lower()->replaceMatches('/[^a-z0-9 ]/', ' ')->squish()->toString();
            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($map[$field]) && in_array($label, $aliases, true)) {
                    $map[$field] = $i;
                }
            }
        }

        return $map;
    }
}
