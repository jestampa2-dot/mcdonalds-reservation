<?php

use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileReservationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('mobile')->group(function () {
    Route::post('/login', [MobileAuthController::class, 'login'])->middleware('guest');
    Route::post('/register', [MobileAuthController::class, 'register'])->middleware('guest');

    Route::get('/home', [MobileReservationController::class, 'mobileHome']);
    Route::get('/booking-options', [MobileReservationController::class, 'mobileBookingOptions']);
    Route::get('/availability', [MobileReservationController::class, 'mobileAvailability']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [MobileAuthController::class, 'me']);
        Route::post('/logout', [MobileAuthController::class, 'logout']);
        Route::get('/dashboard', [MobileReservationController::class, 'mobileDashboard']);
        Route::post('/reservations', [MobileReservationController::class, 'mobileStore']);
    });
});
