<?php

namespace App\Services\Mentari;

use App\Models\AppLink;
use Illuminate\Contracts\Session\Session;

/**
 * Ingatan percakapan Mentari: siapa/apa yang terakhir dibicarakan, supaya pertanyaan lanjutan
 * seperti "training dia?", "GL walinya siapa?", "cara pakainya?" bisa dipahami.
 * Disimpan di sesi login (bukan di database), kedaluwarsa 15 menit.
 */
class Memory
{
    private const KEY = 'mentari.memory';

    private const TTL = 900;

    private const PERSON_PRONOUN = '/\b(dia|beliau|orang itu|orangnya|ybs|yang tadi|org itu|mas itu|pak itu)\b/u';

    private const PERSON_SUFFIX = '/\b(wali|seksi|jabatan|grade|lokasi|training|pelatihan|sertifikat|poin|nrp|data|atasan|profil|program|riwayat|anak asuh|mekanik asuh) ?nya\b/u';

    private const APP_SUFFIX = '/\b(cara(?: pakai| login| masuk)?|admin|pic|link|fungsi|kegunaan|guna|alamat|aplikasi|penjelasan) ?nya\b|^(buka(?:kan)?|link|tautan|masuk)(?: aja)?$/u';

    public function __construct(private ?Session $session = null) {}

    /**
     * Tulis ulang pertanyaan lanjutan dengan subjek terakhir. Null = bukan pertanyaan lanjutan.
     *
     * @return array{0:string,1:string}|null [kalimat baru, jenis subjek]
     */
    public function resolve(string $q): ?array
    {
        $m = $this->get();
        if (! $m || preg_match(PeopleDesk::NRP_PATTERN, strtoupper($q)) || preg_match('/\b(saya|aku|ku)\b/u', $q)) {
            return null;
        }
        $person = $m['person'] ?? null;
        $app = $m['app'] ?? null;
        $prefer = $m['last'] ?? null;

        $tryPerson = function () use ($q, $person) {
            if (! $person) {
                return null;
            }
            if (preg_match(self::PERSON_PRONOUN, $q)) {
                return [trim(preg_replace(self::PERSON_PRONOUN, $person['nrp'], $q, 1)), 'person'];
            }
            if (preg_match(self::PERSON_SUFFIX, $q)) {
                return [trim(preg_replace(self::PERSON_SUFFIX, '$1', $q)).' '.$person['nrp'], 'person'];
            }

            return null;
        };
        $tryApp = function () use ($q, $app) {
            if (! $app || ! preg_match(self::APP_SUFFIX, $q, $mm)) {
                return null;
            }
            $word = $mm[1] ?? '';
            $name = $app['name'];
            $text = match (true) {
                str_starts_with($word, 'cara') => 'cara pakai '.$name,
                in_array($word, ['admin', 'pic'], true) => 'siapa admin '.$name,
                in_array($word, ['fungsi', 'kegunaan', 'guna', 'penjelasan', 'aplikasi'], true) => 'apa itu '.$name,
                default => 'buka '.$name,
            };

            return [$text, 'app'];
        };

        return $prefer === 'app' ? ($tryApp() ?? $tryPerson()) : ($tryPerson() ?? $tryApp());
    }

    /** Catat subjek dari jawaban: satu kartu orang, atau satu aplikasi yang disebut. */
    public function remember(array $answer): void
    {
        $people = collect($answer['cards'] ?? [])->where('type', 'person');
        if ($people->count() === 1) {
            $p = $people->first();
            $this->put('person', ['nrp' => $p['nrp'], 'name' => $p['name']]);

            return;
        }
        $appIds = collect($answer['actions'] ?? [])
            ->map(fn ($a) => isset($a['href']) && preg_match('#/buka/(\d+)$#', $a['href'], $m) ? (int) $m[1] : null)
            ->filter()->unique();
        if ($appIds->count() === 1 && ($app = AppLink::find($appIds->first()))) {
            $this->put('app', ['id' => $app->id, 'name' => $app->name]);
        }
    }

    public function forget(): void
    {
        $this->session?->forget(self::KEY);
    }

    private function get(): ?array
    {
        $m = $this->session?->get(self::KEY);

        return is_array($m) && ($m['at'] ?? 0) > time() - self::TTL ? $m : null;
    }

    private function put(string $kind, array $value): void
    {
        if (! $this->session) {
            return;
        }
        $m = $this->get() ?? [];
        $m[$kind] = $value;
        $m['last'] = $kind;
        $m['at'] = time();
        $this->session->put(self::KEY, $m);
    }
}
