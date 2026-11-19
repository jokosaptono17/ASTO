<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AppController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
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

    Route::get('/', HomeController::class)->name('home');
    Route::get('/aplikasi', [AppController::class, 'index'])->name('apps.index');
    Route::get('/buka/{app}', [AppController::class, 'go'])->name('apps.go');
    Route::post('/aplikasi/{app}/favorit', [AppController::class, 'favorite'])->name('apps.favorite');

    Route::get('/info', [InfoController::class, 'index'])->name('info.index');
    Route::get('/info/{announcement}', [InfoController::class, 'show'])->name('info.show');
    Route::get('/kampanye/{campaign}', [InfoController::class, 'campaign'])->name('campaigns.open');

    // Absensi karyawan
    Route::get('/absen', [AttendanceController::class, 'scan'])->name('attendance.scan');
    Route::post('/absen/kode', [AttendanceController::class, 'enterCode'])->middleware('throttle:20,1')->name('attendance.code');
    Route::post('/absen/{code}', [AttendanceController::class, 'store'])->where('code', '[A-Z0-9]{8}')->name('attendance.store');
    Route::get('/absensi-saya', [AttendanceController::class, 'history'])->name('attendance.history');

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

        Route::middleware('can:admin')->group(function () {
            Route::get('jenis', [Admin\AttendanceTypeController::class, 'index'])->name('types');
            Route::post('jenis', [Admin\AttendanceTypeController::class, 'store'])->name('types.store');
            Route::put('jenis/{type}', [Admin\AttendanceTypeController::class, 'update'])->name('types.update');
            Route::delete('jenis/{type}', [Admin\AttendanceTypeController::class, 'destroy'])->name('types.destroy');
        });
    });

    // Panel admin: Admin SHE mengelola kampanye & pengumuman, Administrator mengelola semuanya
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
