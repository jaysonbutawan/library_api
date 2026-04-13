<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiKey;

class VerifyApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
   public function handle($request, Closure $next)
{
    $apiKey = $request->header('X-API-KEY');

    if (!$apiKey) {
        return response()->json(['message' => 'API key missing'], 401);
    }

    $key = ApiKey::where('api_key', $apiKey)
        ->where('is_active', true)
        ->where(function ($query) {
            $query->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
        })
        ->first();

    if (!$key) {
        return response()->json(['message' => 'Invalid or expired API key'], 401);
    }

    return $next($request);
}
}
