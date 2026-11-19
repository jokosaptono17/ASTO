<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan portal yang bisa diubah admin tanpa menyentuh kode.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'site_name' => 'AstoOne',
        'site_tagline' => 'Portal aplikasi Plant ASTO',
        'slogan' => 'KITA SATU, KITA BISA, KITA HEBAT',
        'help_whatsapp' => '6281313333315',
        'help_message' => 'Halo Admin AstoOne, saya butuh bantuan.',
        'mentari_greeting' => 'Halo! Saya Mentari 👋 robot mekanik Plant ASTO. Mau buka aplikasi apa, atau ada yang bisa saya bantu?',
        'campaign_section_title' => 'Info & Kampanye',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = Cache::rememberForever('settings', fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('settings');
    }

    /**
     * Foto latar hero (beranda desktop & halaman masuk). Bawaan: hutan berkabut;
     * bisa diganti admin di Pengaturan.
     *
     * @return array{src: string, srcset: ?string}
     */
    public static function hero(): array
    {
        $path = static::get('hero_image');
        if ($path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return ['src' => \Illuminate\Support\Facades\Storage::disk('public')->url($path), 'srcset' => null];
        }

        return ['src' => '/images/hero-default.webp', 'srcset' => '/images/hero-default-960.webp 960w, /images/hero-default.webp 1920w'];
    }

    public static function whatsappNumber(): string
    {
        $number = preg_replace('/\D+/', '', (string) static::get('help_whatsapp'));

        return str_starts_with($number, '0') ? '62'.substr($number, 1) : $number;
    }

    public static function whatsappLink(?string $text = null): string
    {
        return 'https://wa.me/'.static::whatsappNumber().'?text='.rawurlencode($text ?? static::get('help_message'));
    }
}
