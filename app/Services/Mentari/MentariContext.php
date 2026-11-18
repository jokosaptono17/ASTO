<?php

namespace App\Services\Mentari;

use App\Models\Announcement;
use App\Models\AppLink;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Data yang boleh diketahui Mentari tentang pengguna dan isi portal.
 * Kontraknya sama dengan Mentari di MekanikPintar (lihat docs/mentari.md),
 * sehingga satu layanan AI bisa melayani keduanya.
 */
class MentariContext
{
    public function for(?User $user): array
    {
        $now = now();
        $hour = (int) $now->format('G');
        $base = [
            'app' => 'astoone',
            'role' => $user?->role ?? 'guest',
            'now' => $now->format('Y-m-d H:i'),
            'today_label' => $now->locale('id')->translatedFormat('l, j M Y'),
            'greeting' => match (true) {
                $hour < 11 => 'Selamat pagi',
                $hour < 15 => 'Selamat siang',
                $hour < 18 => 'Selamat sore',
                default => 'Selamat malam',
            },
        ];

        if (! $user) {
            return $base;
        }

        $portal = Cache::remember('mentari:portal', 60, fn () => [
            'apps' => AppLink::active()->ordered()->with('category')->get()
                ->map(fn (AppLink $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'tagline' => $a->tagline,
                    'category' => $a->category?->name,
                    'url' => route('apps.go', $a, false),
                    'status' => $a->health_check ? $a->health_status : null,
                ])->all(),
            'announcements' => Announcement::live()->take(5)->get()
                ->map(fn (Announcement $a) => [
                    'title' => $a->title,
                    'level' => $a->level,
                    'date' => $a->publishedAt()?->format('Y-m-d'),
                    'summary' => mb_strimwidth(strip_tags($a->body), 0, 220, '…'),
                    'url' => route('info.show', $a, false),
                ])->all(),
            'campaigns' => Campaign::live()->take(6)->get()
                ->map(fn (Campaign $c) => ['title' => $c->title, 'subtitle' => $c->subtitle, 'url' => route('campaigns.open', $c, false)])->all(),
        ]);

        return $base + [
            'name' => $user->name,
            'first_name' => $user->firstName(),
            'nrp' => $user->nrp,
            'department' => $user->department,
            'position' => $user->position,
            'favorites' => $user->favorites()->pluck('name')->all(),
        ] + $portal;
    }
}
