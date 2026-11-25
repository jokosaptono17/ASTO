<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** Event ujian: Best Mekanik, Best GL, pre/post test, dll. */
class Exam extends Model
{
    use SoftDeletes;

    /** Hari sebelum isi Tempat sampah dihapus permanen otomatis. */
    public const TRASH_DAYS = 30;

    /** Saran kategori di formulir (kategori lain yang pernah dipakai ikut muncul). */
    public const CATEGORY_SUGGESTIONS = ['Best Mekanik', 'Best GL', 'Pre-test', 'Post-test', 'Kompetisi', 'Sertifikasi', 'Evaluasi berkala'];

    public const STATES = [
        'draft' => ['label' => 'Draf', 'chip' => 'chip bg-white/10 text-mist', 'icon' => 'ri-draft-line'],
        'scheduled' => ['label' => 'Terjadwal', 'chip' => 'chip chip-terjadwal', 'icon' => 'ri-calendar-schedule-line'],
        'open' => ['label' => 'Berlangsung', 'chip' => 'chip chip-tayang', 'icon' => 'ri-broadcast-line'],
        'closed' => ['label' => 'Selesai', 'chip' => 'chip chip-berakhir', 'icon' => 'ri-flag-2-line'],
    ];

    public const RESULT_MODES = [
        'hidden' => ['label' => 'Disembunyikan', 'hint' => 'Peserta hanya melihat "jawaban terkirim". Cocok untuk kompetisi; hasil diumumkan panitia.'],
        'score' => ['label' => 'Nilai saja', 'hint' => 'Peserta langsung melihat nilai, jumlah benar, dan status lulus.'],
        'review' => ['label' => 'Nilai + pembahasan', 'hint' => 'Setelah ujian ditutup, peserta bisa melihat jawaban benar & pembahasan.'],
    ];

    protected $fillable = [
        'title', 'category', 'description', 'code', 'audience', 'duration_minutes', 'opens_at', 'closes_at', 'is_published',
        'shuffle_questions', 'shuffle_options', 'max_attempts', 'pass_score', 'result_mode', 'max_violations', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime', 'closes_at' => 'datetime', 'is_published' => 'boolean', 'archived_at' => 'datetime',
            'shuffle_questions' => 'boolean', 'shuffle_options' => 'boolean',
        ];
    }

    public static function normalizeCode(?string $code): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) $code));
    }

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = self::normalizeCode($value);
    }

    /** Kode acak yang mudah dibacakan: tanpa huruf/angka yang mirip (O/0, I/1). */
    public static function suggestCode(string $title = ''): string
    {
        $prefix = collect(preg_split('/[\s\-_]+/', Str::upper(Str::ascii($title))))
            ->filter(fn ($w) => preg_match('/^[A-Z]/', $w) && ! in_array($w, ['DAN', 'THE', 'UNTUK', 'UJIAN', 'TEST', 'TES'], true))
            ->map(fn ($w) => $w[0])->take(3)->implode('');
        do {
            $code = ($prefix ?: 'UJ').'-'.collect(range(1, 4))->map(fn () => '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'[random_int(0, 31)])->implode('');
        } while (static::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    public function sets(): BelongsToMany
    {
        return $this->belongsToMany(QuestionSet::class)->withPivot('take');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ExamParticipant::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function state(): string
    {
        return match (true) {
            ! $this->is_published => 'draft',
            $this->opens_at && $this->opens_at->isFuture() => 'scheduled',
            $this->closes_at && $this->closes_at->isPast() => 'closed',
            default => 'open',
        };
    }

    /**
     * Saring status langsung di database (setara dengan state()) agar daftar
     * tetap cepat walau event sudah ratusan.
     */
    public function scopeInState(Builder $q, string $state): Builder
    {
        $now = now();
        $started = fn ($w) => $w->whereNull('opens_at')->orWhere('opens_at', '<=', $now);

        return match ($state) {
            'draft' => $q->where('is_published', false),
            'scheduled' => $q->where('is_published', true)->where('opens_at', '>', $now),
            'closed' => $q->where('is_published', true)->where($started)->whereNotNull('closes_at')->where('closes_at', '<', $now),
            'open' => $q->where('is_published', true)->where($started)->where(fn ($w) => $w->whereNull('closes_at')->orWhere('closes_at', '>=', $now)),
            default => $q,
        };
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('archived_at');
    }

    public function scopeArchived(Builder $q): Builder
    {
        return $q->whereNotNull('archived_at');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** Hanya event yang tidak sedang berjalan/menunggu yang boleh diarsipkan. */
    public function canArchive(): bool
    {
        return in_array($this->state(), ['draft', 'closed'], true);
    }

    /** Alasan event belum boleh dipindah ke Tempat sampah (null = boleh). */
    public function trashBlocker(?int $live = null): ?string
    {
        $live ??= $this->attempts()->where('status', 'in_progress')->count();

        return $live > 0 ? "{$live} peserta sedang mengerjakan. Tutup ujian dan tunggu sampai selesai sebelum menghapus." : null;
    }

    /** Tanggal event dihapus permanen dari Tempat sampah. */
    public function purgeDate(): ?\Illuminate\Support\Carbon
    {
        return $this->deleted_at?->copy()->addDays(self::TRASH_DAYS);
    }

    public function isOpen(): bool
    {
        return $this->state() === 'open';
    }

    public function stateInfo(): array
    {
        return self::STATES[$this->state()];
    }

    /** Ujian yang terbit & sedang dibuka (kolom waktu kosong = tanpa batas). */
    public function scopeOpenNow(Builder $q): Builder
    {
        return $q->where('is_published', true)
            ->where(fn ($w) => $w->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('closes_at')->orWhere('closes_at', '>', now()));
    }

    /** Ujian yang boleh dilihat user: ditunjuk, atau terbuka untuk semua yang punya kode. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $q->where('is_published', true)->where(fn ($w) => $w->where('audience', 'open')
            ->orWhereHas('participants', fn ($p) => $p->where('user_id', $user->id)));
    }

    public function isAssigned(User $user): bool
    {
        return $this->participants()->where('user_id', $user->id)->exists();
    }

    /** Jumlah soal per peserta sesuai sumber paket. */
    public function questionCount(): int
    {
        $sets = $this->relationLoaded('sets') ? $this->sets : $this->sets()->withCount(['questions as active_count' => fn ($q) => $q->where('is_active', true)])->get();

        return (int) $sets->sum(function ($s) {
            $available = $s->active_count ?? $s->activeQuestions()->count();

            return $s->pivot->take ? min($s->pivot->take, $available) : $available;
        });
    }

    public function scheduleLabel(): string
    {
        $fmt = fn ($d) => $d->locale('id')->translatedFormat($d->isToday() ? '\h\a\r\i \i\n\i H:i' : 'j M H:i');

        return match (true) {
            $this->opens_at && $this->closes_at => $fmt($this->opens_at).' – '.($this->closes_at->isSameDay($this->opens_at) ? $this->closes_at->format('H:i') : $fmt($this->closes_at)),
            (bool) $this->closes_at => 'sampai '.$fmt($this->closes_at),
            (bool) $this->opens_at => 'mulai '.$fmt($this->opens_at),
            default => 'Tanpa batas waktu',
        };
    }

    public function showsScore(): bool
    {
        return $this->result_mode !== 'hidden';
    }

    /** Pembahasan baru dibuka setelah ujian ditutup agar tidak bocor ke peserta lain. */
    public function showsReview(): bool
    {
        return $this->result_mode === 'review' && $this->closes_at && $this->closes_at->isPast();
    }
}
