<?php

namespace App\Http\Controllers;

use App\Models\AppLink;
use App\Models\AppVisit;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppController extends Controller
{
    public function index(Request $request): View
    {
        $apps = AppLink::active()->ordered()->with('category')->get();
        $favoriteIds = $request->user()->favorites()->pluck('apps.id')->all();

        $groups = Category::orderBy('sort_order')->orderBy('name')->get()
            ->map(fn ($c) => ['category' => $c, 'apps' => $apps->where('category_id', $c->id)->values()])
            ->filter(fn ($g) => $g['apps']->isNotEmpty())->values();
        $uncategorized = $apps->whereNull('category_id')->values();
        if ($uncategorized->isNotEmpty()) {
            $groups->push(['category' => new Category(['name' => 'Lainnya', 'icon' => 'ri-apps-2-line']), 'apps' => $uncategorized]);
        }

        return view('portal.apps', [
            'groups' => $groups,
            'favoriteIds' => $favoriteIds,
            'searchIndex' => $apps->map->toClient()->values(),
            'tab' => $request->query('tab') === 'favorit' ? 'favorit' : 'semua',
        ]);
    }

    public function go(Request $request, AppLink $app): RedirectResponse
    {
        abort_unless($app->is_active, 404);
        AppVisit::create(['app_id' => $app->id, 'user_id' => $request->user()->id]);

        return redirect()->away($app->url);
    }

    public function favorite(Request $request, AppLink $app): JsonResponse
    {
        $result = $request->user()->favorites()->toggle($app->id);

        return response()->json(['favorite' => in_array($app->id, $result['attached'], true)]);
    }
}
