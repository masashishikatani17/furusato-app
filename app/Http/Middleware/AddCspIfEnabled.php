<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddCspIfEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        // まず常に次へ（必ずレスポンスを握る）
        $response = $next($request);

        // 診断用マーカー（必ず付ける）
        $response->headers->set('X-CSP-MW', 'hit');

        // OFF のときは診断ヘッダのみで終了
        if (!config('csp.enabled')) {
            return $response;
        }

        // ── 自前でヘッダ付与（開発向け：inline/eval 許容）
        // 必要に応じてここを厳格化（本番）：'unsafe-inline' / 'unsafe-eval' を外す
        $policy = implode(' ', [
            "default-src 'self';",
            "base-uri 'self';",
            "frame-ancestors 'self';",
            "object-src 'none';",
            "img-src 'self' data: blob:;",
            "font-src 'self' data:;",
            "media-src 'self' data: blob:;",
            "connect-src 'self' https: wss:;",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval';",
            "style-src 'self' 'unsafe-inline';",
        ]);
        $header = config('csp.report_only', true)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';
        $response->headers->set($header, $policy);
        return $response;
    }
}