<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Question extends Model
{
    public const LETTERS = ['A', 'B', 'C', 'D', 'E', 'F'];

    protected $fillable = ['question_set_id', 'body', 'image_path', 'options', 'answer', 'explanation', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_active' => 'boolean'];
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(QuestionSet::class, 'question_set_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Str::startsWith($this->image_path, ['http://', 'https://']) ? $this->image_path : Storage::disk('public')->url($this->image_path);
    }

    /** Opsi yang terisi saja, berurutan A, B, C, … */
    public function filledOptions(): array
    {
        return collect($this->options ?? [])->filter(fn ($v) => trim((string) $v) !== '')->sortKeys()->all();
    }

    /**
     * Ubah tautan GitHub "blob" menjadi tautan gambar mentah agar bisa langsung dimuat.
     * https://github.com/u/r/blob/main/a.png?raw=true → https://raw.githubusercontent.com/u/r/main/a.png
     */
    public static function normalizeImageUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || ! Str::startsWith($url, ['http://', 'https://'])) {
            return null;
        }
        if (preg_match('#^https?://github\.com/([^/]+)/([^/]+)/(?:blob|raw)/(.+?)(?:\?raw=true)?$#i', $url, $m)) {
            return "https://raw.githubusercontent.com/{$m[1]}/{$m[2]}/{$m[3]}";
        }
        // Google Drive: /file/d/{id}/view atau open?id={id}
        if (preg_match('#drive\.google\.com/(?:file/d/|open\?id=|uc\?(?:export=\w+&)?id=)([\w-]{20,})#', $url, $m)) {
            return "https://drive.google.com/thumbnail?id={$m[1]}&sz=w1600";
        }

        return $url;
    }
}
