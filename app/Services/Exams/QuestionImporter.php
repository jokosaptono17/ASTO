<?php

namespace App\Services\Exams;

use App\Models\Question;
use App\Models\QuestionSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Impor soal dari Excel/CSV. Mengenali tata letak sheet lama "INTEGRATED_KUMPULAN_SOAL"
 * (No | Pertanyaan | Gambar | Opsi A–D | Jawaban | KodeUjian) maupun template baru
 * (Paket | Pertanyaan | Gambar | A–F | Jawaban | Pembahasan). Kolom dicari dari judulnya.
 * Impor ulang aman: soal yang sama persis di paket yang sama dilewati.
 */
class QuestionImporter
{
    private const HEADERS = [
        'set' => ['kodeujian', 'kode ujian', 'paket', 'paket soal', 'kode', 'kategori'],
        'body' => ['pertanyaan', 'soal', 'question'],
        'image' => ['gambar', 'image', 'url gambar', 'foto'],
        'answer' => ['jawaban', 'kunci', 'kunci jawaban', 'answer'],
        'explanation' => ['pembahasan', 'penjelasan', 'keterangan'],
    ];

    /**
     * @param  list<list<string>>  $rows
     * @return array{created:int, duplicates:int, sets:array<string,int>, skipped:list<array{0:int,1:string}>}
     */
    public function import(array $rows, ?QuestionSet $target = null, ?int $userId = null): array
    {
        [$headerIndex, $map] = $this->detect($rows);
        if ($headerIndex === null) {
            throw new ExamException('Judul kolom tidak dikenali. Pastikan ada kolom "Pertanyaan", "Opsi A"/"A" … dan "Jawaban". Unduh template bila ragu.');
        }
        if (! $target && ! isset($map['set'])) {
            throw new ExamException('File tidak punya kolom paket (KodeUjian/Paket). Pilih paket tujuan sebelum mengimpor.');
        }

        $summary = ['created' => 0, 'duplicates' => 0, 'sets' => [], 'skipped' => []];
        $sets = [];
        $existing = [];
        $batch = [];
        $order = [];

        foreach (array_slice($rows, $headerIndex + 1, null, true) as $i => $row) {
            $line = $i + 1;
            $get = fn ($key) => isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';
            $body = $this->clean($get('body'));
            $options = [];
            foreach ($map['options'] as $letter => $col) {
                $text = $this->clean((string) ($row[$col] ?? ''));
                if ($text !== '') {
                    $options[$letter] = $text;
                }
            }
            if ($body === '' && ! $options) {
                continue; // baris kosong
            }
            if ($body === '') {
                $summary['skipped'][] = [$line, 'Pertanyaan kosong'];

                continue;
            }
            if (count($options) < 2) {
                $summary['skipped'][] = [$line, 'Opsi jawaban kurang dari 2'];

                continue;
            }
            $answer = $this->answerLetter($get('answer'), $options);
            if (! $answer) {
                $summary['skipped'][] = [$line, $get('answer') === '' ? 'Kunci jawaban kosong' : 'Kunci "'.Str::limit($get('answer'), 20).'" tidak cocok dengan opsi'];

                continue;
            }

            if ($target) {
                $set = $target;
            } else {
                $name = trim(preg_replace('/\s+/', ' ', $get('set')));
                if ($name === '' || in_array(Str::upper($name), ['NONE', '-', 'N/A'], true)) {
                    $summary['skipped'][] = [$line, 'Kolom paket/KodeUjian kosong'];

                    continue;
                }
                $set = $sets[Str::upper($name)] ??= QuestionSet::whereRaw('upper(name) = ?', [Str::upper($name)])->first()
                    ?? QuestionSet::create(['name' => $name, 'created_by' => $userId]);
            }

            // Soal sama = teks, gambar, dan opsi sama (teks sama dengan gambar berbeda tetap soal berbeda)
            $existing[$set->id] ??= $set->questions()->get(['body', 'image_path', 'options'])
                ->map(fn ($q) => $this->fingerprint($q->body, $q->image_path, $q->options))->flip()->all();
            $image = Question::normalizeImageUrl($get('image'));
            $key = $this->fingerprint($body, $image, $options);
            if (isset($existing[$set->id][$key])) {
                $summary['duplicates']++;

                continue;
            }
            $existing[$set->id][$key] = true;
            $order[$set->id] ??= (int) $set->questions()->max('sort_order');

            $batch[] = [
                'question_set_id' => $set->id, 'body' => $body,
                'image_path' => $image,
                'options' => json_encode($options, JSON_UNESCAPED_UNICODE), 'answer' => $answer,
                'explanation' => $this->clean($get('explanation')) ?: null,
                'is_active' => true, 'sort_order' => ++$order[$set->id],
                'created_at' => now(), 'updated_at' => now(),
            ];
            $summary['sets'][$set->name] = ($summary['sets'][$set->name] ?? 0) + 1;
            $summary['created']++;
        }

        DB::transaction(function () use ($batch) {
            foreach (array_chunk($batch, 300) as $chunk) {
                Question::insert($chunk);
            }
        });

        return $summary;
    }

    /** Cari baris judul (dalam 10 baris pertama) dan posisi tiap kolom. */
    private function detect(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $index => $row) {
            $norm = array_map(fn ($h) => Str::of((string) $h)->lower()->replace(['_', '.', ':'], ' ')->squish()->toString(), $row);
            $map = ['options' => []];
            foreach ($norm as $col => $h) {
                if ($h === '') {
                    continue;
                }
                foreach (self::HEADERS as $key => $names) {
                    if (! isset($map[$key]) && in_array($h, $names, true)) {
                        $map[$key] = $col;

                        continue 2;
                    }
                }
                if (preg_match('/^(?:opsi|pilihan|option)?\s*([a-f])$/', $h, $m)) {
                    $map['options'][Str::upper($m[1])] ??= $col;
                }
            }
            if (isset($map['body'], $map['answer']) && count($map['options']) >= 2) {
                ksort($map['options']);

                return [$index, $map];
            }
        }

        return [null, []];
    }

    /** Kunci bisa ditulis "A", "a", "A.", "(B)" atau teks opsinya. */
    private function answerLetter(string $raw, array $options): ?string
    {
        $raw = trim($raw);
        if (preg_match('/^\(?([A-Fa-f])[\.\)]?$/', $raw, $m) && isset($options[Str::upper($m[1])])) {
            return Str::upper($m[1]);
        }
        foreach ($options as $letter => $text) {
            if ($raw !== '' && $this->key($text) === $this->key($raw)) {
                return $letter;
            }
        }

        return null;
    }

    private function clean(string $text): string
    {
        return trim(preg_replace("/[ \t]+/", ' ', str_replace("\r\n", "\n", $text)));
    }

    private function fingerprint(string $body, ?string $image, array $options): string
    {
        return md5($this->key($body).'|'.($image ?? '').'|'.implode('|', array_map(fn ($o) => $this->key((string) $o), $options)));
    }

    private function key(string $text): string
    {
        return Str::of($text)->lower()->replaceMatches('/[^\pL\pN]+/u', '')->toString();
    }
}
