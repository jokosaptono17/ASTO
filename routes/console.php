<?php

use Illuminate\Support\Facades\Schedule;

// Lampu status aplikasi. Aktifkan cron:  * * * * * cd /www/wwwroot/plantasto.my.id && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('astoone:check-apps')->everyTenMinutes()->withoutOverlapping();

// Rapikan log kunjungan lebih dari 1 tahun
Schedule::call(function () {
    \App\Models\AppVisit::where('created_at', '<', now()->subYear())->delete();
    \App\Models\CampaignClick::where('created_at', '<', now()->subYear())->delete();
})->weekly()->name('prune-logs');
