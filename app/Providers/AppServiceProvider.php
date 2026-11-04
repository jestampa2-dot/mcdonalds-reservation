<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\Reservation;
use App\Models\User;
use App\Observers\BranchActivityObserver;
use App\Observers\ReservationActivityObserver;
use App\Observers\ReservationSmsObserver;
use App\Observers\UserActivityObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Reservation::observe(ReservationSmsObserver::class);
        Reservation::observe(ReservationActivityObserver::class);
        User::observe(UserActivityObserver::class);
        Branch::observe(BranchActivityObserver::class);
    }
}
