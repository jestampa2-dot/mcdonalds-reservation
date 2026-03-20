<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReservationController;

Route::get('/', [ReservationController::class, 'home'])->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [ReservationController::class, 'dashboard'])->name('dashboard');
    Route::get('/reservations/create', [ReservationController::class, 'create'])->name('reservations.create');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::post('/reservations/{reservation}/reschedule', [ReservationController::class, 'reschedule'])->name('reservations.reschedule');
    Route::get('/reservations/{reservation}/pass', [ReservationController::class, 'pass'])->name('reservations.pass');
    Route::get('/reservations/{reservation}/payment-proof', [ReservationController::class, 'paymentProof'])->name('reservations.payment-proof');
    Route::get('/availability', [ReservationController::class, 'availability'])->name('availability.index');

    Route::get('/admin/dashboard', [ReservationController::class, 'adminDashboard'])->name('admin.dashboard');
    Route::post('/admin/reservations/{reservation}/status', [ReservationController::class, 'updateBookingStatus'])->name('admin.reservations.status');
    Route::post('/admin/reservations/{reservation}/crew', [ReservationController::class, 'assignCrew'])->name('admin.reservations.crew');
    Route::post('/admin/users/{user}/role', [ReservationController::class, 'updateUserRole'])->name('admin.users.role');
    Route::post('/admin/branches', [ReservationController::class, 'storeBranch'])->name('admin.branches.store');

    Route::get('/staff/dashboard', [ReservationController::class, 'staffDashboard'])->name('staff.dashboard');
    Route::post('/staff/check-in', [ReservationController::class, 'checkIn'])->name('staff.check-in');
    Route::post('/staff/reservations/{reservation}/service-status', [ReservationController::class, 'updateServiceStatus'])->name('staff.reservations.service-status');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
