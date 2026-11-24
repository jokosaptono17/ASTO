<?php

namespace App\Http\Controllers\Admin\Exams;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ExamAnswer;
use App\Models\Question;
use App\Models\QuestionSet;
use App\Services\Exams\ExamException;
use App\Services\Exams\QuestionImporter;
use App\Services\ImageService;
use App\Services\Spreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Bank soal: paket, soal, impor/ekspor Excel. */
class BankController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $sets = QuestionSet::withCount([
            'questions', 'questions as active_count' => fn ($q) => $q->where('is_active', true),
            'questions as image_count' => fn ($q) => $q->whereNotNull('image_path'), 'exams',
        ])->when($term, fn ($q) => $q->where('name', 'like', "%{$term}%"))->orderBy('name')->get();

        $hits = mb_strlen($term) >= 3
            ? Question::with('set:id,name')->where('body', 'like', "%{$term}%")->limit(20)->get()
            : collect();

        return view('admin.exams.bank.index', [
            'sets' => $sets, 'q' => $term, 'hits' => $hits,
            'total' => Question::count(), 'active' => Question::where('is_active', true)->count(),
        ]);
    }

    public function storeSet(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:question_sets,name'],
            'description' => ['nullable', 'string', 'max:500'],
        ], ['name.unique' => 'Nama paket sudah ada.']);
        $set = QuestionSet::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('admin.exams.bank.set', $set)->with('toast', 'Paket soal dibuat. Tambahkan soal atau impor dari Excel.');
    }

    public function updateSet(Request $request, QuestionSet $set): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('question_sets', 'name')->ignore($set->id)],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $set->update($data);

        return back()->with('toast', 'Paket soal diperbarui.');
    }

    public function destroySet(QuestionSet $set): RedirectResponse
    {
        if (ExamAnswer::whereIn('question_id', $set->questions()->select('id'))->exists()) {
            return back()->with('toast', 'Paket ini sudah dipakai di lembar jawaban peserta, jadi tidak bisa dihapus. Nonaktifkan soalnya saja.');
        }
        ActivityLog::record('bank.delete', "Menghapus paket soal {$set->name}");
        $set->delete();

        return redirect()->route('admin.exams.bank')->with('toast', 'Paket soal dihapus.');
    }

    public function set(Request $request, QuestionSet $set): View
    {
        $term = trim((string) $request->query('q'));
        $questions = $set->questions()
            ->when($term, fn ($q) => $q->where('body', 'like', "%{$term}%"))
            ->when($request->query('tampil') === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->when($request->query('tampil') === 'gambar', fn ($q) => $q->whereNotNull('image_path'))
            ->withCount(['answers', 'answers as correct_count' => fn ($q) => $q->where('is_correct', true)])
            ->paginate(25)->withQueryString();

        return view('admin.exams.bank.set', [
            'set' => $set->loadCount(['questions', 'questions as active_count' => fn ($q) => $q->where('is_active', true)]),
            'questions' => $questions, 'q' => $term, 'filter' => $request->query('tampil'),
            'exams' => $set->exams()->latest('id')->get(),
        ]);
    }

    public function create(QuestionSet $set): View
    {
        return view('admin.exams.bank.question', ['set' => $set, 'question' => new Question(['options' => ['A' => '', 'B' => '', 'C' => '', 'D' => ''], 'answer' => 'A', 'is_active' => true])]);
    }

    public function edit(Question $question): View
    {
        return view('admin.exams.bank.question', ['set' => $question->set, 'question' => $question, 'used' => $question->answers()->count()]);
    }

    public function store(Request $request, QuestionSet $set, ImageService $images): RedirectResponse
    {
        $question = new Question(['question_set_id' => $set->id, 'sort_order' => (int) $set->questions()->max('sort_order') + 1]);
        $this->save($request, $question, $images);

        return $request->input('next') === 'lagi'
            ? redirect()->route('admin.exams.bank.create', $set)->with('toast', 'Soal disimpan. Lanjut soal berikutnya.')
            : redirect()->route('admin.exams.bank.set', $set)->with('toast', 'Soal ditambahkan.');
    }

    public function update(Request $request, Question $question, ImageService $images): RedirectResponse
    {
        $this->save($request, $question, $images);

        return redirect()->route('admin.exams.bank.set', [$question->question_set_id, 'page' => $request->input('page')])->with('toast', 'Soal diperbarui. Bila kunci diubah, tekan "Hitung ulang nilai" di ujian terkait.');
    }

    public function toggle(Question $question): RedirectResponse
    {
        $question->update(['is_active' => ! $question->is_active]);

        return back()->with('toast', $question->is_active ? 'Soal diaktifkan.' : 'Soal dinonaktifkan; tidak akan keluar di ujian berikutnya.');
    }

    public function destroy(Question $question, ImageService $images): RedirectResponse
    {
        if ($question->answers()->exists()) {
            $question->update(['is_active' => false]);

            return back()->with('toast', 'Soal sudah pernah dijawab peserta, jadi dinonaktifkan (bukan dihapus) agar riwayat nilai tetap utuh.');
        }
        $images->delete($question->image_path);
        $question->delete();

        return back()->with('toast', 'Soal dihapus.');
    }

    public function importForm(): View
    {
        return view('admin.exams.bank.import', ['sets' => QuestionSet::orderBy('name')->get(['id', 'name'])]);
    }

    public function import(Request $request, QuestionImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:xlsx,csv,txt'],
            'set_id' => ['nullable', 'integer', 'exists:question_sets,id'],
        ], ['file.mimes' => 'Gunakan file .xlsx atau .csv.']);
        $file = $request->file('file');
        try {
            $rows = Spreadsheet::read($file->getRealPath(), $file->getClientOriginalExtension(), 'Soal');
            $summary = $importer->import($rows, $request->filled('set_id') ? QuestionSet::find($request->input('set_id')) : null, $request->user()->id);
        } catch (ExamException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['file' => 'File tidak bisa dibaca: '.Str::limit($e->getMessage(), 140)]);
        }
        ActivityLog::record('bank.import', "Impor {$summary['created']} soal dari {$file->getClientOriginalName()}");

        return redirect()->route('admin.exams.bank.import')->with('summary', $summary + ['file' => $file->getClientOriginalName()]);
    }

    public function template(): BinaryFileResponse
    {
        $path = storage_path('app/template-soal-'.Str::random(6).'.xlsx');
        Spreadsheet::writeXlsx($path, [
            ['Paket', 'Pertanyaan', 'Gambar', 'A', 'B', 'C', 'D', 'E', 'Jawaban', 'Pembahasan'],
            ['BEST MEKANIK 2026', 'Torsi pengencangan baut roda Scania P-series adalah …', '', '400 Nm', '550 Nm', '650 Nm', '750 Nm', '', 'C', 'Lihat WIO wheel nut tightening.'],
            ['BEST MEKANIK 2026', 'Komponen pada gambar berikut adalah …', 'https://raw.githubusercontent.com/ppdasto/contoh/main/komponen.png', 'Alternator', 'Starter motor', 'Kompresor AC', 'Water pump', '', 'B', ''],
            ['BEST GL 2026', 'Langkah pertama sebelum melakukan perbaikan unit adalah …', '', 'Memasang LOTO', 'Membuka cover', 'Menyiapkan tools', 'Mengisi form', '', 'A', 'Isolasi energi selalu didahulukan.'],
        ], 'Soal', [22, 60, 34, 22, 22, 22, 22, 14, 10, 40]);

        return response()->download($path, 'Template soal ujian AstoOne.xlsx')->deleteFileAfterSend();
    }

    public function export(QuestionSet $set): BinaryFileResponse
    {
        $rows = [['Paket', 'Pertanyaan', 'Gambar', 'A', 'B', 'C', 'D', 'E', 'F', 'Jawaban', 'Pembahasan', 'Aktif']];
        foreach ($set->questions()->get() as $q) {
            $o = $q->options;
            $rows[] = [$set->name, $q->body, $q->imageUrl() ?? '', $o['A'] ?? '', $o['B'] ?? '', $o['C'] ?? '', $o['D'] ?? '', $o['E'] ?? '', $o['F'] ?? '', $q->answer, $q->explanation ?? '', $q->is_active ? 'YA' : 'TIDAK'];
        }
        $path = storage_path('app/soal-'.Str::random(6).'.xlsx');
        Spreadsheet::writeXlsx($path, $rows, 'Soal', [22, 60, 34, 22, 22, 22, 22, 14, 14, 10, 40, 8]);

        return response()->download($path, 'Soal '.Str::slug($set->name).'.xlsx')->deleteFileAfterSend();
    }

    private function save(Request $request, Question $question, ImageService $images): void
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'options' => ['required', 'array'],
            'options.*' => ['nullable', 'string', 'max:1000'],
            'answer' => ['required', Rule::in(Question::LETTERS)],
            'explanation' => ['nullable', 'string', 'max:3000'],
            'image' => ['nullable', 'image', 'max:8192'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'remove_image' => ['nullable', 'boolean'],
        ]);
        $options = collect(Question::LETTERS)->mapWithKeys(fn ($l) => [$l => trim((string) ($data['options'][$l] ?? ''))])->filter(fn ($v) => $v !== '');
        // Rapatkan huruf bila ada opsi kosong di tengah (A, C → A, B) dan geser kuncinya
        $answerText = $options->get($data['answer']);
        $options = $options->values()->mapWithKeys(fn ($v, $i) => [Question::LETTERS[$i] => $v]);
        if ($options->count() < 2) {
            throw ValidationException::withMessages(['options' => 'Isi minimal 2 opsi jawaban.']);
        }
        if ($answerText === null) {
            throw ValidationException::withMessages(['answer' => 'Kunci jawaban menunjuk opsi yang kosong.']);
        }

        $path = $question->image_path;
        if ($request->hasFile('image')) {
            $images->delete($path);
            $path = $images->storeFit($request->file('image'), 'soal', 1600, 1600);
        } elseif ($request->filled('image_url')) {
            $path = Question::normalizeImageUrl($data['image_url']);
        } elseif ($request->boolean('remove_image')) {
            $images->delete($path);
            $path = null;
        }

        $question->fill([
            'body' => trim($data['body']), 'options' => $options->all(), 'answer' => $options->search($answerText),
            'explanation' => $data['explanation'] ?? null, 'image_path' => $path,
        ])->save();
    }
}
