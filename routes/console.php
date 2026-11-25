<?php

use Illuminate\Support\Facades\Schedule;

// Lampu status aplikasi. Aktifkan cron:  * * * * * cd /www/wwwroot/plantasto.my.id && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('astoone:check-apps')->everyTenMinutes()->withoutOverlapping();

// Rapikan log kunjungan lebih dari 1 tahun
Schedule::call(function () {
    \App\Models\AppVisit::where('created_at', '<', now()->subYear())->delete();
    \App\Models\CampaignClick::where('created_at', '<', now()->subYear())->delete();
    // Jejak pertanyaan Mentari disimpan 90 hari saja
    \App\Models\MentariLog::where('created_at', '<', now()->subDays(90))->delete();
})->weekly()->name('prune-logs');

// Tempat sampah event ujian: dihapus permanen setelah 30 hari
Schedule::call(fn () => app(\App\Services\Exams\ExamService::class)->purgeTrash())->daily()->name('purge-exam-trash');
