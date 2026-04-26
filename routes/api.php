<?php

use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\MobileOperationsController;
use App\Http\Controllers\Api\MobileProfileController;
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
        Route::post('/reservations/{reservation}/cancel', [MobileReservationController::class, 'mobileCancel']);
        Route::post('/reservations/{reservation}/reschedule', [MobileReservationController::class, 'mobileReschedule']);

        Route::get('/operations', [MobileOperationsController::class, 'mobileOperations']);
        Route::get('/admin/availability-day/{branchCode}/{date}', [MobileOperationsController::class, 'mobileAvailabilityDay'])
            ->where('date', '\d{4}-\d{2}-\d{2}');
        Route::post('/staff/check-in', [MobileOperationsController::class, 'mobileCheckIn']);
        Route::post('/staff/reservations/{reservation}/service-status', [MobileOperationsController::class, 'mobileUpdateServiceStatus']);
        Route::post('/staff/reservations/{reservation}/adjustments', [MobileOperationsController::class, 'mobileUpdateServiceAdjustments']);

        Route::post('/admin/reservations/{reservation}/status', [MobileOperationsController::class, 'mobileUpdateBookingStatus']);
        Route::post('/admin/reservations/{reservation}/crew', [MobileOperationsController::class, 'mobileAssignCrew']);
        Route::post('/admin/users', [MobileOperationsController::class, 'mobileStoreAdminUser']);
        Route::put('/admin/users/{user}', [MobileOperationsController::class, 'mobileUpdateAdminUser']);
        Route::delete('/admin/users/{user}', [MobileOperationsController::class, 'mobileDestroyAdminUser']);
        Route::post('/admin/branches', [MobileOperationsController::class, 'mobileStoreBranch']);
        Route::put('/admin/branches/{branch}', [MobileOperationsController::class, 'mobileUpdateBranch']);
        Route::delete('/admin/branches/{branch}', [MobileOperationsController::class, 'mobileDestroyBranch']);
        Route::post('/admin/branches/{branch}/inventory', [MobileOperationsController::class, 'mobileStoreInventoryItem']);
        Route::put('/admin/inventory-items/{inventoryItem}', [MobileOperationsController::class, 'mobileUpdateInventoryItem']);
        Route::put('/admin/event-types/{eventType}', [MobileOperationsController::class, 'mobileUpdateEventType']);
        Route::put('/admin/packages/{package}', [MobileOperationsController::class, 'mobileUpdatePackage']);
        Route::post('/admin/room-options', [MobileOperationsController::class, 'mobileStoreRoomOption']);
        Route::put('/admin/room-options/{roomOption}', [MobileOperationsController::class, 'mobileUpdateRoomOption']);
        Route::put('/admin/booking-settings', [MobileOperationsController::class, 'mobileUpdateBookingSettings']);

        Route::get('/profile', [MobileProfileController::class, 'show']);
        Route::put('/profile', [MobileProfileController::class, 'update']);
        Route::put('/profile/password', [MobileProfileController::class, 'updatePassword']);
        Route::delete('/profile', [MobileProfileController::class, 'destroy']);
    });
});
