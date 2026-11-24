<?php

namespace App\Services\Mentari;

use App\Models\PointTransaction;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use App\Services\Points\PointService;

/** Mentari soal Poin HEBAT: "poin saya", "tukar poin", "hadiah apa saja". */
class PointsDesk
{
    public function answer(string $q, User $viewer): ?array
    {
        if (! preg_match('/\b(poin|point|hebat|hadiah|reward|kupon|tukar)\b/u', $q)) {
            return null;
        }
        // "cara tukar hadiah", "bagaimana dapat poin" → panduan/AI
        if (preg_match('/\b(cara|bagaimana|kenapa|mengapa|dapat|mendapat|mengumpulkan|aturan|apa itu|level)\b/u', $q) && ! preg_match('/\b(saldo|berapa)\b/u', $q)) {
            return null;
        }
        $lv = PointService::level($viewer);
        $fmt = fn ($n) => number_format($n, 0, ',', '.');

        if (preg_match('/\b(hadiah|katalog|tukar|reward)\b/u', $q) && ! preg_match('/\b(saya|ku|aku|saldo)\b/u', $q)) {
            $rewards = Reward::available()->limit(6)->get();

            return $this->reply($rewards->isEmpty() ? 'Katalog hadiah belum diisi tim admin.' : "Saldo Anda {$fmt($viewer->points_balance)} poin. Beberapa hadiah di katalog:", $rewards->isEmpty() ? [] : [[
                'type' => 'list', 'title' => 'Katalog hadiah',
                'items' => $rewards->map(fn ($r) => ['title' => $r->name, 'subtitle' => $fmt($r->cost).' poin'.($r->stock !== null ? ' · stok '.$r->stock : ''), 'meta' => $viewer->points_balance >= $r->cost ? 'Bisa' : null, 'href' => route('points.rewards').'#hadiah-'.$r->id])->all(),
            ]], [['label' => 'Buka katalog', 'href' => route('points.rewards')]]);
        }

        $last = PointTransaction::where('user_id', $viewer->id)->latest('id')->limit(5)->get();
        $coupons = Redemption::where('user_id', $viewer->id)->where('status', 'pending')->count();
        $text = "Saldo Poin HEBAT Anda {$fmt($viewer->points_balance)} poin · level {$lv['current']['name']}"
            .($lv['next'] ? ' ('.$fmt($lv['next']['min'] - $lv['earned']).' poin lagi menuju '.$lv['next']['name'].')' : '').'.'
            .($coupons ? " Ada {$coupons} kupon yang belum diambil." : '');

        return $this->reply($text, $last->isEmpty() ? [] : [[
            'type' => 'list', 'title' => 'Transaksi terakhir',
            'items' => $last->map(fn ($t) => ['title' => $t->description, 'subtitle' => $t->created_at->locale('id')->translatedFormat('j M, H:i'), 'meta' => ($t->amount > 0 ? '+' : '−').$fmt(abs($t->amount))])->all(),
        ]], [['label' => 'Dompet poin', 'href' => route('points.index')], ['label' => 'Tukar hadiah', 'href' => route('points.rewards')]]);
    }

    private function reply(string $text, array $cards = [], array $actions = []): array
    {
        return ['reply' => $text, 'cards' => $cards, 'actions' => $actions, 'intent' => 'points'];
    }
}
