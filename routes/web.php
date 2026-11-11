<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Hrd;
use App\Http\Controllers\Owner\ExecutiveDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Pwa;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Parameter numerik agar `create` tidak tertangkap sebagai {id}.
foreach (['user', 'outlet', 'freelancer', 'template', 'task', 'backup'] as $param) {
    Route::pattern($param, '[0-9]+');
}

Route::get('/', function () {
    return auth()->check() ? redirect()->to(auth()->user()->dashboardRoute()) : redirect()->route('login');
});

Route::get('/offline', fn () => view('offline'))->name('offline');

// ------------------------------------------------------------------ Guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // -------------------------------------------------------------- Semua role
    Route::middleware('role')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    });

    // -------------------------------------------------------------- SuperAdmin
    Route::middleware('role:superadmin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [Admin\UserController::class, 'create'])->name('users.create');
        Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [Admin\UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/{user}/toggle', [Admin\UserController::class, 'toggle'])->name('users.toggle');
        Route::post('users/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset');

        Route::get('backups', [Admin\BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [Admin\BackupController::class, 'store'])->name('backups.store');
        Route::post('backups/restore', [Admin\BackupController::class, 'restore'])->name('backups.restore');
        Route::get('backups/{backup}/download', [Admin\BackupController::class, 'download'])->name('backups.download');
        Route::delete('backups/{backup}', [Admin\BackupController::class, 'destroy'])->name('backups.destroy');

        Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');

        Route::get('logs', [Admin\ActivityLogController::class, 'index'])->name('logs.index');
    });

    // ------------------------------------------- HRD & SuperAdmin (dashboard)
    Route::middleware('role:superadmin,admin_hrd')->prefix('hrd')->name('hrd.')->group(function () {
        Route::get('/dashboard', [Hrd\DashboardController::class, 'index'])->name('dashboard');
    });

    // ---------------------------- Data master & operasional: baca (incl. Owner)
    Route::middleware('role:superadmin,admin_hrd,owner')->prefix('hrd')->name('hrd.')->group(function () {
        Route::get('outlets', [Hrd\OutletController::class, 'index'])->name('outlets.index');
        Route::get('freelancers', [Hrd\FreelancerController::class, 'index'])->name('freelancers.index');
        Route::get('templates', [Hrd\TaskTemplateController::class, 'index'])->name('templates.index');
        Route::get('tasks', [Hrd\TaskAssignmentController::class, 'index'])->name('tasks.index');
        Route::get('attendances', [Hrd\AttendanceController::class, 'index'])->name('attendances.index');
        Route::get('performance', [Hrd\PerformanceController::class, 'index'])->name('performance.index');
    });

    // ------------------------------------- Data master & operasional: kelola
    Route::middleware('role:superadmin,admin_hrd')->prefix('hrd')->name('hrd.')->group(function () {
        Route::get('outlets/create', [Hrd\OutletController::class, 'create'])->name('outlets.create');
        Route::post('outlets', [Hrd\OutletController::class, 'store'])->name('outlets.store');
        Route::get('outlets/{outlet}/edit', [Hrd\OutletController::class, 'edit'])->name('outlets.edit');
        Route::put('outlets/{outlet}', [Hrd\OutletController::class, 'update'])->name('outlets.update');
        Route::delete('outlets/{outlet}', [Hrd\OutletController::class, 'destroy'])->name('outlets.destroy');

        Route::get('freelancers/create', [Hrd\FreelancerController::class, 'create'])->name('freelancers.create');
        Route::post('freelancers', [Hrd\FreelancerController::class, 'store'])->name('freelancers.store');
        Route::get('freelancers/{freelancer}', [Hrd\FreelancerController::class, 'show'])->name('freelancers.show');
        Route::get('freelancers/{freelancer}/edit', [Hrd\FreelancerController::class, 'edit'])->name('freelancers.edit');
        Route::put('freelancers/{freelancer}', [Hrd\FreelancerController::class, 'update'])->name('freelancers.update');
        Route::delete('freelancers/{freelancer}', [Hrd\FreelancerController::class, 'destroy'])->name('freelancers.destroy');
        Route::post('freelancers/{freelancer}/toggle', [Hrd\FreelancerController::class, 'toggle'])->name('freelancers.toggle');
        Route::post('freelancers/{freelancer}/request-reset', [Hrd\FreelancerController::class, 'requestReset'])->name('freelancers.request-reset');

        Route::get('templates/create', [Hrd\TaskTemplateController::class, 'create'])->name('templates.create');
        Route::post('templates', [Hrd\TaskTemplateController::class, 'store'])->name('templates.store');
        Route::get('templates/{template}/edit', [Hrd\TaskTemplateController::class, 'edit'])->name('templates.edit');
        Route::put('templates/{template}', [Hrd\TaskTemplateController::class, 'update'])->name('templates.update');
        Route::delete('templates/{template}', [Hrd\TaskTemplateController::class, 'destroy'])->name('templates.destroy');

        Route::get('tasks/template', [Hrd\TaskAssignmentController::class, 'downloadTemplate'])->name('tasks.template');
        Route::get('tasks/import', [Hrd\TaskAssignmentController::class, 'showImport'])->name('tasks.import.show');
        Route::post('tasks/import', [Hrd\TaskAssignmentController::class, 'import'])->name('tasks.import');
        Route::get('tasks/create', [Hrd\TaskAssignmentController::class, 'create'])->name('tasks.create');
        Route::post('tasks', [Hrd\TaskAssignmentController::class, 'store'])->name('tasks.store');
        Route::post('tasks/generate', [Hrd\TaskAssignmentController::class, 'generate'])->name('tasks.generate');
        Route::get('tasks/{task}/edit', [Hrd\TaskAssignmentController::class, 'edit'])->name('tasks.edit');
        Route::put('tasks/{task}', [Hrd\TaskAssignmentController::class, 'update'])->name('tasks.update');
        Route::post('tasks/{task}/cancel', [Hrd\TaskAssignmentController::class, 'cancel'])->name('tasks.cancel');
        Route::delete('tasks/{task}', [Hrd\TaskAssignmentController::class, 'destroy'])->name('tasks.destroy');

        Route::post('attendances', [Hrd\AttendanceController::class, 'store'])->name('attendances.store');
        Route::post('performance/recalculate', [Hrd\PerformanceController::class, 'recalculate'])->name('performance.recalculate');
    });

    // Detail tugas (baca) untuk SuperAdmin, HRD, dan Owner.
    Route::middleware('role:superadmin,admin_hrd,owner')->prefix('hrd')->name('hrd.')->group(function () {
        Route::get('tasks/{task}', [Hrd\TaskAssignmentController::class, 'show'])->name('tasks.show');
    });

    // ------------------------------------------- Executive dashboard & Laporan
    Route::middleware('role:superadmin,admin_hrd,owner')->group(function () {
        Route::get('/owner/executive-dashboard', [ExecutiveDashboardController::class, 'index'])->name('owner.dashboard');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
        Route::get('/reports/excel', [ReportController::class, 'excel'])->name('reports.excel');
    });

    // -------------------------------------------------------------- Freelancer
    Route::middleware('role:freelancer')->prefix('pwa')->name('pwa.')->group(function () {
        Route::get('/workspace', [Pwa\WorkspaceController::class, 'index'])->name('workspace');

        Route::get('tasks', [Pwa\TaskController::class, 'index'])->name('tasks.index');
        Route::get('tasks/{task}', [Pwa\TaskController::class, 'show'])->name('tasks.show');
        Route::post('tasks/{task}/start', [Pwa\TaskController::class, 'start'])->middleware('throttle:30,1')->name('tasks.start');
        Route::post('tasks/{task}/complete', [Pwa\TaskController::class, 'complete'])->middleware('throttle:30,1')->name('tasks.complete');

        Route::get('attendance', [Pwa\AttendanceController::class, 'show'])->name('attendance');
        Route::post('attendance/check-in', [Pwa\AttendanceController::class, 'checkIn'])->middleware('throttle:20,1')->name('attendance.checkin');
        Route::post('attendance/check-out', [Pwa\AttendanceController::class, 'checkOut'])->middleware('throttle:20,1')->name('attendance.checkout');

        Route::get('performance', [Pwa\PerformanceController::class, 'show'])->name('performance');
    });
});
