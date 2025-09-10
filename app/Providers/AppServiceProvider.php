<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Models\Customer;
use App\Services\Phone\Nigeria as NigerianPhone;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Resolve the route model binding for the Customer model
        Route::bind('customer', fn($value) => Customer::where('id', $value)
                                                    ->orWhere('phone_number', app()->make(NigerianPhone::class)->convert($value))
                                                    ->firstOrFail());
    }
}
