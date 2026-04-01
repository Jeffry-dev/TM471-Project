<?php

namespace App\Http\Controllers;

use App\Models\ChatLog;
use App\Models\Visitor;
use Illuminate\Http\Request;

class InteractionTrackingController extends Controller
{
    public function ping(Request $request)
    {
        $visitorId = $request->session()->get('visitor_id');

        return response()->json([
            'tracked' => true,
            'visitorId' => $visitorId ? (int) $visitorId : null,
        ]);
    }

    public function visitors()
    {
        return Visitor::query()
            ->orderByDesc('visited_at')
            ->limit(500)
            ->get()
            ->map(fn (Visitor $visitor) => [
                'id' => $visitor->id,
                'ipAddress' => $visitor->ip_address,
                'userAgent' => $visitor->user_agent,
                'visitedAt' => $visitor->visited_at?->toISOString(),
            ])
            ->all();
    }

    public function chatLogs()
    {
        return ChatLog::query()
            ->with('visitor')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn (ChatLog $log) => [
                'id' => $log->id,
                'question' => $log->question,
                'response' => $log->response,
                'visitorId' => $log->visitor_id,
                'visitorIp' => $log->visitor?->ip_address,
                'createdAt' => $log->created_at?->toISOString(),
            ])
            ->all();
    }
}
