<?php

namespace App\Http\Middleware;

use App\Models\Visitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVisitorTracked
{
    public function handle(Request $request, Closure $next): Response
    {
        $visitorId = $request->session()->get('visitor_id');

        if ($visitorId && Visitor::query()->whereKey($visitorId)->exists()) {
            return $next($request);
        }

        $visitor = Visitor::create([
            'ip_address' => (string) $request->ip(),
            'user_agent' => (string) ($request->userAgent() ?? ''),
            'visited_at' => now(),
        ]);

        $request->session()->put('visitor_id', $visitor->id);
        $request->session()->save();

        return $next($request);
    }
}
