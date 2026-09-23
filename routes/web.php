<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SubDepartmentController;
use App\Http\Controllers\WorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Master Data (Hanya dapat diakses oleh Super Admin)
    Route::middleware('super-admin')->group(function () {
        // Master Data Bagian (Departments)
        Route::resource('departments', DepartmentController::class)->except(['create', 'show', 'edit']);

        // Master Data Sub Bagian (Sub-Departments)
        Route::get('sub-departments/by-department/{department}', [SubDepartmentController::class, 'byDepartment'])->name('sub-departments.by-department');
        Route::resource('sub-departments', SubDepartmentController::class)->except(['create', 'show', 'edit']);

        // Roles
        Route::resource('roles', RoleController::class)->except(['create', 'show', 'edit']);

        // Profil Perusahaan (Company Profile)
        Route::get('company-profile', [CompanyProfileController::class, 'edit'])->name('company-profile.edit');
        Route::post('company-profile', [CompanyProfileController::class, 'update'])->name('company-profile.update');

        // Pengaturan Jam Kerja (Work Hours)
        Route::get('work-hours', [WorkScheduleController::class, 'edit'])->name('work-hours.edit');
        Route::post('work-hours', [WorkScheduleController::class, 'update'])->name('work-hours.update');
    });

    // Data Pegawai (Employees)
    Route::post('employees/{employee}/reset-password', [EmployeeController::class, 'resetPassword'])->name('employees.reset-password');
    Route::resource('employees', EmployeeController::class)->except(['create', 'show', 'edit']);

    // Kehadiran (Attendances)
    Route::get('attendances', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::post('attendances', [AttendanceController::class, 'store'])->name('attendances.store');
    Route::put('attendances/{attendance}', [AttendanceController::class, 'update'])->name('attendances.update');
    Route::delete('attendances/{attendance}', [AttendanceController::class, 'destroy'])->name('attendances.destroy');
    Route::post('attendances/check-in', [AttendanceController::class, 'checkIn'])->name('attendances.check-in');
    Route::post('attendances/check-out', [AttendanceController::class, 'checkOut'])->name('attendances.check-out');
});

require __DIR__.'/settings.php';
