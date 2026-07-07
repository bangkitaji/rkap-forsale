<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user->must_change_password) {
                // Allow logout
                if ($request->routeIs('logout')) {
                    return $next($request);
                }

                // Allow my-profile with security tab
                if ($request->is('my-profile/security') || ($request->routeIs('my-profile') && $request->route('tab') === 'security')) {
                    return $next($request);
                }

                // Allow Livewire requests (vital for components to function!)
                if ($request->is('livewire*') || $request->hasHeader('X-Livewire') || $request->routeIs('livewire.*')) {
                    return $next($request);
                }

                // Redirect other requests to the password change page
                return redirect()->route('my-profile', ['tab' => 'security'])
                    ->with('force_password_change', 'Anda harus mengubah password default Anda sebelum melanjutkan.');
            }
        }

        return $next($request);
    }
}
