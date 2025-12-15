<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckMlToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Define the Expected Token
        // It's best practice to store this in your .env file
        $expectedToken = env('ML_SERVICE_TOKEN');

        // 2. Check the token
        // We look for the token in the request header (e.g., 'X-ML-Token')
        $providedToken = $request->header('X-ML-Token');

        // --- Security Check ---
        if ($providedToken !== $expectedToken || empty($providedToken)) {
            // Log the unauthorized attempt for security monitoring
            Log::warning('Unauthorized access attempt to ML data API.', [
                'ip' => $request->ip(),
                'provided_token' => $providedToken,
            ]);

            // Return an immediate 401 Unauthorized response
            return response()->json([
                'message' => 'Unauthorized: Invalid ML Service Token.',
            ], 401);
        }

        // If the token is valid, proceed with the request
        return $next($request);
    }
}
