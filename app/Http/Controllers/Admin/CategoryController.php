<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Category::create($this->validated($request) + ['sort_order' => (Category::max('sort_order') ?? 0) + 10]);

        return back()->with('toast', 'Kategori ditambahkan.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request));

        return back()->with('toast', 'Kategori disimpan.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return back()->with('toast', 'Kategori dihapus. Aplikasinya pindah ke "Lainnya".');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'icon' => ['required', 'string', 'max:60', 'regex:/^ri-[a-z0-9-]+$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
