<?php

namespace App\Http\Controllers\Admin;

use App\Models\AttendanceRecord;
use App\Services\Spreadsheet;
use Illuminate\Support\Collection;

/** Ekspor kehadiran ke .xlsx (kolom mengikuti lembar absensi P5M lama). */
class AttendanceExport
{
    /** @param Collection<int, AttendanceRecord> $records */
    public static function write(Collection $records, string $sheet): string
    {
        $rows = [['HARI', 'TANGGAL', 'SHIFT', 'WAKTU MULAI', 'PERUSAHAAN', 'DEPARTEMEN PELAKSANA', 'KEGIATAN', 'JUDUL / TOPIK', 'LOKASI',
            'NAMA', 'NRP', 'JABATAN', 'SECTION', 'PERAN', 'JAM ABSEN', 'TERLAMBAT', 'MULAI TIDUR', 'BANGUN', 'DURASI TIDUR', 'STATUS FATIGUE', 'CARA ABSEN']];
        foreach ($records as $r) {
            $s = $r->session;
            $rows[] = [
                $s->starts_at->locale('id')->translatedFormat('l'),
                $s->starts_at->format('d/m/Y'),
                $s->shift ? 'Shift '.$s->shift : '',
                $s->starts_at->format('H:i'),
                $s->company,
                $s->department,
                $s->type->name,
                $s->title,
                $s->location,
                $r->name,
                $r->nrp,
                ($r->role === 'pemateri' ? '⭐ ' : '').$r->position,
                $r->department,
                $r->role === 'pemateri' ? 'Pemateri' : 'Audience',
                $r->checked_in_at->format('H:i'),
                $r->is_late ? 'YA' : '',
                $r->sleep_at ? substr($r->sleep_at, 0, 5) : '',
                $r->wake_at ? substr($r->wake_at, 0, 5) : '',
                $r->sleepLabel() ?? '',
                $r->fatigue ? strtoupper(AttendanceRecord::FATIGUE[$r->fatigue]['label']) : '',
                AttendanceRecord::METHODS[$r->method] ?? $r->method,
            ];
        }
        $path = storage_path('app/private/absensi-'.uniqid().'.xlsx');
        @mkdir(dirname($path), 0775, true);
        Spreadsheet::writeXlsx($path, $rows, $sheet, [12, 12, 9, 11, 16, 20, 14, 30, 14, 30, 12, 22, 20, 11, 10, 10, 11, 9, 14, 16, 13]);

        return $path;
    }
}
