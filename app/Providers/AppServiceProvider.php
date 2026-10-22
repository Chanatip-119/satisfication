<?php

namespace App\Providers;

use Illuminate\Auth\Access\Response as GateResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-admin', function ($user) {
            if (in_array((int) $user->role_id, [1, 2, 3], true)) {
                return GateResponse::allow();
            }

            return GateResponse::deny('คุณไม่มีสิทธิ์เข้าถึงเมนูนี้ กรุณาติดต่อผู้ดูแลระบบหากจำเป็นต้องใช้งาน');
        });
    }
}
