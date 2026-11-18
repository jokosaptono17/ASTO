<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AppController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InfoController;
use App\Http\Controllers\MentariController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/offline', 'portal.offline')->name('offline');
Route::post('/mentari/chat', [MentariController::class, 'chat'])->middleware('throttle:30,1')->name('mentari.chat');

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

    Route::get('/profil', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profil/pin', [ProfileController::class, 'updatePin'])->name('profile.pin');

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
