<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubdomainMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Only apply this middleware for authenticated users
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        
        // Skip for Super Admin
        if ($user->hasRole('Super Admin')) {
            return $next($request);
        }

        // Get current subdomain
        $fullDomain = $_SERVER['HTTP_HOST'] ?? '';
        $parts = explode('.', $fullDomain);
        $subdomain = $parts[0];
        
        // Find school by subdomain
        $school = School::where('domain', $subdomain)->first();
        
        if (!$school) {
            // If no school found for this subdomain, continue
            return $next($request);
        }

        // Check if user's school_id matches the current subdomain's school
        if ($user->school_id !== $school->id) {
            // Log out the user and redirect to login
            Auth::logout();
            $request->session()->flush();
            $request->session()->regenerate();
            
            return redirect()->route('login')->withErrors([
                'email' => 'You are not authorized to access this school domain.'
            ]);
        }

        return $next($request);
    }
} 