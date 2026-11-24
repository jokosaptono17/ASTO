<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AppController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InfoController;
use App\Http\Controllers\MentariController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/offline', 'portal.offline')->name('offline');
Route::post('/mentari/chat', [MentariController::class, 'chat'])->middleware('throttle:30,1')->name('mentari.chat');

// Link QR absensi boleh dibuka sebelum login: bukti scan disimpan, lalu diarahkan ke halaman masuk
Route::get('/absen/{code}', [AttendanceController::class, 'show'])->where('code', '[A-Z0-9]{8}')->name('attendance.checkin');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'show'])->name('login');
    Route::post('/masuk', [AuthController::class, 'check'])->middleware('throttle:20,1')->name('login.check');
    Route::get('/masuk/pin', [AuthController::class, 'pinForm'])->name('login.pin');
    Route::post('/masuk/pin', [AuthController::class, 'pin'])->middleware('throttle:20,1')->name('login.pin.submit');
    Route::get('/masuk/ganti-nrp', [AuthController::class, 'forget'])->name('login.forget');
    Route::get('/aktivasi', [AuthController::class, 'activateForm'])->name('activate');
    Route::post('/aktivasi', [AuthController::class, 'activate'])->middleware('throttle:20,1')->name('activate.submit');
});

Route::middleware('auth')->group(function () {
    Route::post('/keluar', [AuthController::class, 'logout'])->name('logout');

    // Masuk dengan AstoOne → MekanikPintar (LMS)
    Route::get('/sso/mekanikpintar', [\App\Http\Controllers\SsoController::class, 'show'])->middleware('throttle:30,1')->name('sso.mekanikpintar');
    Route::post('/sso/mekanikpintar', [\App\Http\Controllers\SsoController::class, 'approve'])->middleware('throttle:20,1')->name('sso.mekanikpintar.approve');
    Route::post('/sso/mekanikpintar/batal', [\App\Http\Controllers\SsoController::class, 'cancel'])->name('sso.mekanikpintar.cancel');
    Route::post('/sso/mekanikpintar/ganti-akun', [\App\Http\Controllers\SsoController::class, 'switchAccount'])->name('sso.mekanikpintar.switch');

    Route::get('/', HomeController::class)->name('home');
    Route::get('/aplikasi', [AppController::class, 'index'])->name('apps.index');
    Route::get('/buka/{app}', [AppController::class, 'go'])->name('apps.go');
    Route::post('/aplikasi/{app}/favorit', [AppController::class, 'favorite'])->name('apps.favorite');

    Route::get('/info', [InfoController::class, 'index'])->name('info.index');
    Route::get('/info/{announcement}', [InfoController::class, 'show'])->name('info.show');
    Route::get('/kampanye/{campaign}', [InfoController::class, 'campaign'])->name('campaigns.open');

    // Absensi karyawan
    Route::post('/kampanye/{campaign}/popup', [\App\Http\Controllers\PopupController::class, 'seen'])->middleware('throttle:30,1')->name('popup.seen');
    Route::post('/kampanye/{campaign}/popup/tutup', [\App\Http\Controllers\PopupController::class, 'close'])->middleware('throttle:30,1')->name('popup.close');
    // Poin HEBAT
    Route::get('/poin', [\App\Http\Controllers\PointController::class, 'index'])->name('points.index');
    Route::get('/poin/hadiah', [\App\Http\Controllers\PointController::class, 'rewards'])->name('points.rewards');
    Route::post('/poin/hadiah/{reward}/tukar', [\App\Http\Controllers\PointController::class, 'redeem'])->middleware('throttle:20,1')->name('points.redeem');
    Route::get('/poin/kupon/{redemption}', [\App\Http\Controllers\PointController::class, 'coupon'])->name('points.coupon');

    Route::get('/absen', [AttendanceController::class, 'scan'])->name('attendance.scan');
    Route::get('/absen/lokasi', [AttendanceController::class, 'locate'])->middleware('throttle:60,1')->name('attendance.locate');
    Route::post('/absen/kode', [AttendanceController::class, 'enterCode'])->middleware('throttle:20,1')->name('attendance.code');
    Route::post('/absen/{code}', [AttendanceController::class, 'store'])->where('code', '[A-Z0-9]{8}')->name('attendance.store');
    Route::get('/absensi-saya', [AttendanceController::class, 'history'])->name('attendance.history');

    // Ujian Event (Best Mekanik, Best GL, pre/post test)
    Route::get('/ujian', [ExamController::class, 'index'])->name('exams.index');
    Route::post('/ujian/kode', [ExamController::class, 'enterCode'])->middleware('throttle:12,1')->name('exams.code');
    Route::get('/ujian/{exam}', [ExamController::class, 'show'])->whereNumber('exam')->name('exams.show');
    Route::post('/ujian/{exam}/mulai', [ExamController::class, 'start'])->whereNumber('exam')->middleware('throttle:12,1')->name('exams.start');
    Route::get('/ujian/sesi/{attempt}', [ExamController::class, 'room'])->name('exams.room');
    Route::post('/ujian/sesi/{attempt}/jawab', [ExamController::class, 'answer'])->middleware('throttle:300,1')->name('exams.answer');
    Route::post('/ujian/sesi/{attempt}/sinkron', [ExamController::class, 'sync'])->middleware('throttle:60,1')->name('exams.sync');
    Route::post('/ujian/sesi/{attempt}/pelanggaran', [ExamController::class, 'violation'])->middleware('throttle:60,1')->name('exams.violation');
    Route::post('/ujian/sesi/{attempt}/kumpulkan', [ExamController::class, 'submit'])->name('exams.submit');
    Route::get('/ujian/hasil/{attempt}', [ExamController::class, 'result'])->name('exams.result');

    Route::get('/profil', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profil/pin', [ProfileController::class, 'updatePin'])->name('profile.pin');

    // Absensi untuk host: GL, Admin SHE, Administrator
    Route::prefix('admin/absensi')->name('admin.attendance.')->middleware('can:host-attendance')->group(function () {
        Route::get('/', [Admin\AttendanceSessionController::class, 'index'])->name('index');
        Route::get('baru', [Admin\AttendanceSessionController::class, 'create'])->name('create');
        Route::post('/', [Admin\AttendanceSessionController::class, 'store'])->name('store');
        Route::get('rekap', [Admin\AttendanceRecapController::class, 'index'])->name('recap');
        Route::get('rekap/ekspor', [Admin\AttendanceRecapController::class, 'export'])->name('recap.export');
        Route::get('cari-karyawan', [Admin\AttendanceSessionController::class, 'people'])->name('people');
        Route::get('poster-qr', [Admin\AttendanceSessionController::class, 'poster'])->name('poster');
        Route::get('{session}', [Admin\AttendanceSessionController::class, 'show'])->whereNumber('session')->name('show');
        Route::get('{session}/layar', [Admin\AttendanceSessionController::class, 'screen'])->whereNumber('session')->name('screen');
        Route::get('{session}/feed', [Admin\AttendanceSessionController::class, 'feed'])->whereNumber('session')->name('feed');
        Route::get('{session}/ubah', [Admin\AttendanceSessionController::class, 'edit'])->whereNumber('session')->name('edit');
        Route::put('{session}', [Admin\AttendanceSessionController::class, 'update'])->whereNumber('session')->name('update');
        Route::delete('{session}', [Admin\AttendanceSessionController::class, 'destroy'])->whereNumber('session')->name('destroy');
        Route::post('{session}/tutup', [Admin\AttendanceSessionController::class, 'close'])->whereNumber('session')->name('close');
        Route::post('{session}/hadir', [Admin\AttendanceSessionController::class, 'addRecord'])->whereNumber('session')->name('records.store');
        Route::delete('{session}/hadir/{record}', [Admin\AttendanceSessionController::class, 'removeRecord'])->whereNumber('session')->name('records.destroy');
        Route::get('{session}/ekspor', [Admin\AttendanceSessionController::class, 'export'])->whereNumber('session')->name('export');

        // Jadwal rutin & area GPS: Administrator dan Admin SHE
        Route::middleware('can:manage-content')->group(function () {
            Route::get('jadwal', [Admin\AttendanceScheduleController::class, 'index'])->name('schedules');
            Route::post('jadwal', [Admin\AttendanceScheduleController::class, 'store'])->name('schedules.store');
            Route::put('jadwal/{schedule}', [Admin\AttendanceScheduleController::class, 'update'])->name('schedules.update');
            Route::delete('jadwal/{schedule}', [Admin\AttendanceScheduleController::class, 'destroy'])->name('schedules.destroy');
            Route::get('lokasi', [Admin\AttendanceLocationController::class, 'index'])->name('locations');
            Route::get('lokasi/baru', [Admin\AttendanceLocationController::class, 'create'])->name('locations.create');
            Route::post('lokasi', [Admin\AttendanceLocationController::class, 'store'])->name('locations.store');
            Route::get('lokasi/{location}', [Admin\AttendanceLocationController::class, 'edit'])->name('locations.edit');
            Route::put('lokasi/{location}', [Admin\AttendanceLocationController::class, 'update'])->name('locations.update');
            Route::delete('lokasi/{location}', [Admin\AttendanceLocationController::class, 'destroy'])->name('locations.destroy');
        });

        Route::middleware('can:admin')->group(function () {
            Route::get('jenis', [Admin\AttendanceTypeController::class, 'index'])->name('types');
            Route::post('jenis', [Admin\AttendanceTypeController::class, 'store'])->name('types.store');
            Route::put('jenis/{type}', [Admin\AttendanceTypeController::class, 'update'])->name('types.update');
            Route::delete('jenis/{type}', [Admin\AttendanceTypeController::class, 'destroy'])->name('types.destroy');
        });
    });

    // Panel admin: Admin SHE mengelola kampanye & pengumuman, Administrator mengelola semuanya
    // Poin HEBAT — Administrator & Admin SHE (persetujuan & penyesuaian: Administrator)
    Route::prefix('admin/poin')->name('admin.points.')->middleware('can:manage-points')->group(function () {
        Route::get('/', Admin\Points\OverviewController::class)->name('index');
        Route::get('pemberian', [Admin\Points\GrantController::class, 'index'])->name('grants.index');
        Route::get('pemberian/baru', [Admin\Points\GrantController::class, 'create'])->name('grants.create');
        Route::post('pemberian', [Admin\Points\GrantController::class, 'store'])->name('grants.store');
        Route::get('pemberian/{grant}', [Admin\Points\GrantController::class, 'show'])->name('grants.show');
        Route::put('pemberian/{grant}', [Admin\Points\GrantController::class, 'update'])->name('grants.update');
        Route::delete('pemberian/{grant}', [Admin\Points\GrantController::class, 'destroy'])->name('grants.destroy');
        Route::post('pemberian/{grant}/setujui', [Admin\Points\GrantController::class, 'approve'])->middleware('can:admin')->name('grants.approve');
        Route::post('pemberian/{grant}/tolak', [Admin\Points\GrantController::class, 'reject'])->middleware('can:admin')->name('grants.reject');
        Route::get('cari-karyawan', [Admin\Points\GrantController::class, 'people'])->name('people');
        Route::resource('hadiah', Admin\Points\RewardController::class)->except('show')->parameters(['hadiah' => 'reward'])->names('rewards');
        Route::get('kupon', [Admin\Points\RedemptionController::class, 'index'])->name('redemptions.index');
        Route::post('kupon/cari', [Admin\Points\RedemptionController::class, 'lookup'])->name('redemptions.lookup');
        Route::get('kupon/{redemption}', [Admin\Points\RedemptionController::class, 'show'])->name('redemptions.show');
        Route::post('kupon/{redemption}/serahkan', [Admin\Points\RedemptionController::class, 'fulfill'])->name('redemptions.fulfill');
        Route::post('kupon/{redemption}/batalkan', [Admin\Points\RedemptionController::class, 'cancel'])->name('redemptions.cancel');
        Route::get('riwayat', [Admin\Points\LedgerController::class, 'index'])->name('ledger');
        Route::get('riwayat/ekspor', [Admin\Points\LedgerController::class, 'export'])->name('ledger.export');
        Route::post('penyesuaian', [Admin\Points\LedgerController::class, 'adjust'])->middleware('can:admin')->name('adjust');
        Route::get('aturan', [Admin\Points\LedgerController::class, 'rules'])->name('rules');
        Route::put('aturan', [Admin\Points\LedgerController::class, 'saveRules'])->name('rules.update');
    });

    // Ujian Event — Administrator & Admin SHE
    Route::prefix('admin/ujian')->name('admin.exams.')->middleware('can:manage-exams')->group(function () {
        $c = Admin\Exams\ExamController::class;
        $b = Admin\Exams\BankController::class;
        Route::get('bank', [$b, 'index'])->name('bank');
        Route::post('bank', [$b, 'storeSet'])->name('bank.store');
        Route::get('bank/impor', [$b, 'importForm'])->name('bank.import');
        Route::post('bank/impor', [$b, 'import'])->name('bank.import.store');
        Route::get('bank/template', [$b, 'template'])->name('bank.template');
        Route::get('bank/soal/{question}', [$b, 'edit'])->name('bank.edit');
        Route::put('bank/soal/{question}', [$b, 'update'])->name('bank.update');
        Route::patch('bank/soal/{question}/aktif', [$b, 'toggle'])->name('bank.toggle');
        Route::delete('bank/soal/{question}', [$b, 'destroy'])->name('bank.destroy');
        Route::get('bank/{set}', [$b, 'set'])->name('bank.set');
        Route::put('bank/{set}', [$b, 'updateSet'])->name('bank.update-set');
        Route::delete('bank/{set}', [$b, 'destroySet'])->name('bank.destroy-set');
        Route::get('bank/{set}/baru', [$b, 'create'])->name('bank.create');
        Route::post('bank/{set}/soal', [$b, 'store'])->name('bank.question.store');
        Route::get('bank/{set}/ekspor', [$b, 'export'])->name('bank.export');

        Route::get('cari-karyawan', [$c, 'people'])->name('people');
        Route::get('/', [$c, 'index'])->name('index');
        Route::get('baru', [$c, 'create'])->name('create');
        Route::post('/', [$c, 'store'])->name('store');
        Route::get('{exam}', [$c, 'show'])->whereNumber('exam')->name('show');
        Route::get('{exam}/ubah', [$c, 'edit'])->whereNumber('exam')->name('edit');
        Route::put('{exam}', [$c, 'update'])->whereNumber('exam')->name('update');
        Route::delete('{exam}', [$c, 'destroy'])->whereNumber('exam')->name('destroy');
        Route::post('{exam}/terbit', [$c, 'publish'])->whereNumber('exam')->name('publish');
        Route::post('{exam}/buka-tutup', [$c, 'toggleOpen'])->whereNumber('exam')->name('toggle');
        Route::get('{exam}/feed', [$c, 'feed'])->whereNumber('exam')->name('feed');
        Route::get('{exam}/layar', [$c, 'screen'])->whereNumber('exam')->name('screen');
        Route::get('{exam}/ekspor', [$c, 'export'])->whereNumber('exam')->name('export');
        Route::post('{exam}/hitung-ulang', [$c, 'regrade'])->whereNumber('exam')->name('regrade');
        Route::get('{exam}/peserta/{attempt}', [$c, 'attempt'])->whereNumber('exam')->name('attempt');
        Route::post('{exam}/peserta/{attempt}', [$c, 'attemptAction'])->whereNumber('exam')->name('attempt.action');
    });

    Route::prefix('admin')->name('admin.')->middleware('can:manage-content')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::patch('kampanye/{campaign}/toggle', [Admin\CampaignController::class, 'toggle'])->name('campaigns.toggle');
        Route::resource('kampanye', Admin\CampaignController::class)->except('show')
            ->parameters(['kampanye' => 'campaign'])->names('campaigns');
        Route::resource('pengumuman', Admin\AnnouncementController::class)->except('show')
            ->parameters(['pengumuman' => 'announcement'])->names('announcements');

        Route::middleware('can:admin')->group(function () {
            Route::post('aplikasi/{app}/geser', [Admin\AppLinkController::class, 'move'])->name('apps.move');
            Route::resource('aplikasi', Admin\AppLinkController::class)->except('show')
                ->parameters(['aplikasi' => 'app'])->names('apps');
            Route::resource('kategori', Admin\CategoryController::class)->only(['store', 'update', 'destroy'])
                ->parameters(['kategori' => 'category'])->names('categories');

            Route::get('pengguna/template', [Admin\UserController::class, 'template'])->name('users.template');
            Route::get('pengguna/ekspor', [Admin\UserController::class, 'export'])->name('users.export');
            Route::post('pengguna/impor', [Admin\UserController::class, 'import'])->name('users.import');
            Route::post('pengguna/{user}/reset-pin', [Admin\UserController::class, 'resetPin'])->name('users.reset-pin');
            Route::resource('pengguna', Admin\UserController::class)->except('show')
                ->parameters(['pengguna' => 'user'])->names('users');

            Route::post('mentari/uji', [Admin\MentariFaqController::class, 'test'])->name('mentari.test');
            Route::resource('mentari', Admin\MentariFaqController::class)->only(['index', 'store', 'update', 'destroy'])
                ->parameters(['mentari' => 'faq'])->names('mentari');

            Route::get('pengaturan', [Admin\SettingController::class, 'edit'])->name('settings');
            Route::put('pengaturan', [Admin\SettingController::class, 'update'])->name('settings.update');
        });
    });
});
