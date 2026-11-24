<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mengolah gambar unggahan dengan GD: potong tengah ke rasio tujuan,
 * perkecil, lalu simpan sebagai WebP (atau JPEG bila WebP tidak tersedia)
 * agar ringan dibuka lewat data seluler di site.
 */
class ImageService
{
    public function storeCover(UploadedFile $file, string $dir, int $width, int $height): string
    {
        $src = $this->load($file);
        [$sw, $sh] = [imagesx($src), imagesy($src)];

        $targetRatio = $width / $height;
        if ($sw / $sh > $targetRatio) {
            $cropH = $sh;
            $cropW = (int) round($sh * $targetRatio);
        } else {
            $cropW = $sw;
            $cropH = (int) round($sw / $targetRatio);
        }
        $x = (int) floor(($sw - $cropW) / 2);
        $y = (int) floor(($sh - $cropH) / 2);

        // Jangan memperbesar gambar kecil berlebihan
        $outW = min($width, $cropW);
        $outH = (int) round($outW / $targetRatio);

        $dst = imagecreatetruecolor($outW, $outH);
        imagecopyresampled($dst, $src, 0, 0, $x, $y, $outW, $outH, $cropW, $cropH);
        imagedestroy($src);

        return $this->save($dst, $dir, false);
    }

    /** Gambar soal/diagram: diperkecil agar muat dalam kotak, tanpa dipotong. */
    public function storeFit(UploadedFile $file, string $dir, int $maxW = 1600, int $maxH = 1600): string
    {
        $src = $this->load($file);
        [$sw, $sh] = [imagesx($src), imagesy($src)];
        $scale = min($maxW / $sw, $maxH / $sh, 1);
        $w = max(1, (int) round($sw * $scale));
        $h = max(1, (int) round($sh * $scale));

        $dst = imagecreatetruecolor($w, $h);
        // Latar putih: diagram PNG transparan tetap terbaca di tema gelap
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
        imagedestroy($src);

        return $this->save($dst, $dir, false);
    }

    /** Ikon aplikasi: persegi, transparansi dipertahankan. */
    public function storeIcon(UploadedFile $file, string $dir, int $size = 256): string
    {
        if (Str::lower($file->getClientOriginalExtension()) === 'svg') {
            return $file->storeAs($dir, Str::random(24).'.svg', 'public');
        }

        $src = $this->load($file);
        [$sw, $sh] = [imagesx($src), imagesy($src)];
        $scale = min($size / $sw, $size / $sh, 1);
        $w = max(1, (int) round($sw * $scale));
        $h = max(1, (int) round($sh * $scale));

        $dst = imagecreatetruecolor($size, $size);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagealphablending($dst, true);
        imagecopyresampled($dst, $src, (int) (($size - $w) / 2), (int) (($size - $h) / 2), 0, 0, $w, $h, $sw, $sh);
        imagedestroy($src);

        return $this->save($dst, $dir, true);
    }

    public function delete(?string $path): void
    {
        if ($path && ! Str::startsWith($path, ['http://', 'https://'])) {
            Storage::disk('public')->delete($path);
        }
    }

    private function load(UploadedFile $file): \GdImage
    {
        $data = file_get_contents($file->getRealPath());
        $img = $data !== false ? @imagecreatefromstring($data) : false;
        if (! $img) {
            throw new RuntimeException('Gambar tidak bisa dibaca. Gunakan JPG, PNG, atau WebP.');
        }
        if (! imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        imagealphablending($img, true);
        imagesavealpha($img, true);

        return $this->orient($img, $file);
    }

    /** Putar foto HP sesuai EXIF agar tidak miring. */
    private function orient(\GdImage $img, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array(Str::lower($file->getClientOriginalExtension()), ['jpg', 'jpeg'], true)) {
            return $img;
        }
        $exif = @exif_read_data($file->getRealPath());
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180, 6 => -90, 8 => 90, default => 0,
        };

        return $angle ? (imagerotate($img, $angle, 0) ?: $img) : $img;
    }

    private function save(\GdImage $img, string $dir, bool $alpha): string
    {
        $webp = function_exists('imagewebp');
        $ext = $webp ? 'webp' : ($alpha ? 'png' : 'jpg');
        $path = trim($dir, '/').'/'.now()->format('Ym').'/'.Str::random(28).'.'.$ext;

        ob_start();
        match ($ext) {
            'webp' => imagewebp($img, null, 82),
            'png' => imagepng($img, null, 7),
            default => imagejpeg($img, null, 84),
        };
        $binary = ob_get_clean();
        imagedestroy($img);

        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
