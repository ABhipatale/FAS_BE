<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanyAccess
{
    /**
     * Handle an incoming request to ensure user belongs to the same company
     * when accessing company-specific resources.
     */
    public function handle(Request $request, Closure $next, $guard = null): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // If the route has a user parameter, check company ownership
        if ($request->route('id')) {
            $requestedUserId = $request->route('id');
            
            // Only check for user-related routes
            $currentPath = $request->path();
            if (str_contains($currentPath, '/users/') || str_contains($currentPath, '/attendance/user/')) {
                $requestedUser = \App\Models\User::find($requestedUserId);
                
                if ($requestedUser && $user->company_id !== $requestedUser->company_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized: Access denied to this resource'
                    ], 403);
                }
            }
        }

        // For routes that should be scoped to company
        $currentPath = $request->path();
        if (str_contains($currentPath, '/users') || str_contains($currentPath, '/attendance') || 
            str_contains($currentPath, '/shifts') || str_contains($currentPath, '/face-descriptor')) {
            
            // Add company_id to the request for scoping queries
            $request->merge(['company_id' => $user->company_id]);
        }

        return $next($request);
    }
}