<?php

namespace App\Models;

use App\Services\Geo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Area absensi (WS 25, Quarry, Crusher, …) berupa poligon GPS.
 * Poligon direkam admin dengan berjalan di batas area, bukan dari citra peta
 * yang sering tidak mutakhir di area tambang.
 */
class AttendanceLocation extends Model
{
    /** Akurasi GPS terburuk (meter) yang masih dianggap bisa dipercaya. */
    public const MAX_ACCURACY = 50;

    protected $fillable = ['name', 'polygon', 'buffer_m', 'is_active'];

    protected function casts(): array
    {
        return ['polygon' => 'array', 'is_active' => 'boolean', 'mapped_at' => 'datetime', 'center_lat' => 'float', 'center_lng' => 'float'];
    }

    public function isMapped(): bool
    {
        return is_array($this->polygon) && count($this->polygon) >= 3;
    }

    public function scopeMapped(Builder $q): Builder
    {
        return $q->where('is_active', true)->whereNotNull('polygon');
    }

    public function distanceFrom(float $lat, float $lng): float
    {
        return Geo::distanceToPolygon($lat, $lng, $this->polygon ?? []);
    }

    /** Simpan poligon baru beserta titik tengah & luas. */
    public function setPolygon(array $points, ?User $by = null): void
    {
        $points = array_values(array_map(fn ($p) => [round((float) $p[0], 7), round((float) $p[1], 7)], $points));
        $this->polygon = $points;
        [$this->center_lat, $this->center_lng] = Geo::centroid($points);
        $this->area_m2 = (int) round(Geo::area($points));
        $this->mapped_by = $by?->id;
        $this->mapped_at = now();
    }

    /** Bentuk poligon sebagai path SVG (viewBox 0 0 100 100) untuk pratinjau. */
    public function svgPath(): ?string
    {
        if (! $this->isMapped()) {
            return null;
        }
        $k = cos(deg2rad($this->center_lat ?? $this->polygon[0][0]));
        $xs = array_map(fn ($p) => $p[1] * $k, $this->polygon);
        $ys = array_map(fn ($p) => -$p[0], $this->polygon);
        $w = max(max($xs) - min($xs), 1e-9);
        $h = max(max($ys) - min($ys), 1e-9);
        $scale = 84 / max($w, $h);
        $ox = (100 - $w * $scale) / 2;
        $oy = (100 - $h * $scale) / 2;

        return collect($xs)->map(fn ($x, $i) => ($i ? 'L' : 'M').round(($x - min($xs)) * $scale + $ox, 1).' '.round(($ys[$i] - min($ys)) * $scale + $oy, 1))->join(' ').' Z';
    }

    public function areaLabel(): string
    {
        $a = (int) $this->area_m2;

        return $a >= 10000 ? number_format($a / 10000, 2, ',', '.').' ha' : number_format($a, 0, ',', '.').' m²';
    }

    /**
     * Tentukan posisi karyawan terhadap area yang sah.
     *
     * - inside      : di dalam area (+ toleransi) dan akurasi GPS baik
     * - outside     : pasti di luar (jarak melebihi toleransi + akurasi) → ditolak
     * - unverified  : GPS tidak tersedia / akurasi buruk di dekat area → boleh, ditandai
     * - none        : belum ada area yang dipetakan → tidak dicek
     *
     * @param  array<int>|null  $ids  batasi ke area tertentu (null = semua area)
     * @return array{status: string, location: ?self, distance: ?int, accuracy: ?int}
     */
    public static function locate(?float $lat, ?float $lng, ?float $accuracy, ?array $ids = null): array
    {
        $candidates = static::mapped()->when($ids, fn ($q) => $q->whereIn('id', $ids))->get();
        $acc = $accuracy !== null ? (int) round($accuracy) : null;
        if ($candidates->isEmpty()) {
            return ['status' => 'none', 'location' => null, 'distance' => null, 'accuracy' => $acc];
        }
        if ($lat === null || $lng === null) {
            return ['status' => 'unverified', 'location' => null, 'distance' => null, 'accuracy' => $acc];
        }

        /** @var self $nearest */
        $nearest = $candidates->sortBy(fn (self $l) => $l->distanceFrom($lat, $lng) - $l->buffer_m)->first();
        $d = $nearest->distanceFrom($lat, $lng);
        $accM = $acc ?? 9999;
        $status = match (true) {
            $d <= $nearest->buffer_m && $accM <= self::MAX_ACCURACY => 'inside',
            $d > $nearest->buffer_m + $accM => 'outside',
            default => 'unverified',
        };

        return ['status' => $status, 'location' => $nearest, 'distance' => (int) round($d), 'accuracy' => $acc];
    }

    /** @return Collection<int, self> */
    public static function options(): Collection
    {
        return static::where('is_active', true)->orderBy('name')->get();
    }

    public function mapper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mapped_by');
    }
}
