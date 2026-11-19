<?php

namespace App\Services;

/**
 * Hitungan geofence sederhana untuk area kecil (≤ beberapa km): koordinat
 * diproyeksikan ke bidang datar (meter) di sekitar titik acuan, lalu dicek
 * dengan ray casting dan jarak titik-ke-garis. Galat < 0,1% untuk area tambang.
 */
class Geo
{
    private const R = 6371008.8;

    /** @param array<int, array{0: float, 1: float}> $polygon [[lat, lng], …] */
    public static function contains(float $lat, float $lng, array $polygon): bool
    {
        $pts = self::project($polygon, $lat, $lng);
        $inside = false;
        $n = count($pts);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $pts[$i];
            [$xj, $yj] = $pts[$j];
            if (($yi > 0) !== ($yj > 0) && 0 < ($xj - $xi) * (0 - $yi) / (($yj - $yi) ?: 1e-12) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** Jarak (meter) dari titik ke tepi poligon; 0 bila di dalam. */
    public static function distanceToPolygon(float $lat, float $lng, array $polygon): float
    {
        if (count($polygon) < 3) {
            return INF;
        }
        if (self::contains($lat, $lng, $polygon)) {
            return 0.0;
        }
        $pts = self::project($polygon, $lat, $lng);
        $best = INF;
        $n = count($pts);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $best = min($best, self::pointSegment(0, 0, $pts[$j], $pts[$i]));
        }

        return $best;
    }

    /** Luas poligon (m²). */
    public static function area(array $polygon): float
    {
        if (count($polygon) < 3) {
            return 0;
        }
        [$clat, $clng] = self::centroid($polygon);
        $pts = self::project($polygon, $clat, $clng);
        $sum = 0;
        $n = count($pts);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $sum += $pts[$j][0] * $pts[$i][1] - $pts[$i][0] * $pts[$j][1];
        }

        return abs($sum) / 2;
    }

    /** @return array{0: float, 1: float} */
    public static function centroid(array $polygon): array
    {
        $n = max(1, count($polygon));

        return [array_sum(array_column($polygon, 0)) / $n, array_sum(array_column($polygon, 1)) / $n];
    }

    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::R * asin(min(1, sqrt($a)));
    }

    /** @return array<int, array{0: float, 1: float}> titik (x, y) meter relatif terhadap acuan */
    private static function project(array $polygon, float $lat0, float $lng0): array
    {
        $k = cos(deg2rad($lat0));

        return array_map(fn ($p) => [deg2rad($p[1] - $lng0) * self::R * $k, deg2rad($p[0] - $lat0) * self::R], $polygon);
    }

    private static function pointSegment(float $px, float $py, array $a, array $b): float
    {
        [$ax, $ay] = $a;
        [$bx, $by] = $b;
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $len = $dx * $dx + $dy * $dy;
        $t = $len > 0 ? max(0, min(1, (($px - $ax) * $dx + ($py - $ay) * $dy) / $len)) : 0;

        return hypot($px - ($ax + $t * $dx), $py - ($ay + $t * $dy));
    }
}
