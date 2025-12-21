<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Admin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // User từ Sanctum
        $user = $request->user();

        // Nếu user null → chưa đăng nhập
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // Nếu không phải admin
        if ($user->role_id !== 1 && $user->role_id !== 12) {
            return response()->json([
                'message' => 'Unauthorized — you are not admin.'
            ], 403);
        }

        return $next($request);
    }
}
