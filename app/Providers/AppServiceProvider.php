<?php

namespace App\Providers;

use App\Models\Reservation;
use App\Observers\ReservationSmsObserver;
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
    }
}
