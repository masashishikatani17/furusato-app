<?php

namespace App\Providers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class AuthRedirectServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, function () {
            return new class implements LoginResponseContract {
                /**
                 * Create an HTTP response that represents the object.
                 */
                public function toResponse($request): RedirectResponse
                {
                    return redirect()->intended('/admin/settings');
                }
            };
        });

        $this->app->singleton(LogoutResponseContract::class, function () {
            return new class implements LogoutResponseContract {
                /**
                 * Create an HTTP response that represents the object.
                 */
                public function toResponse($request): RedirectResponse
                {
                    return redirect('/login');
                }
            };
        });
    }
}