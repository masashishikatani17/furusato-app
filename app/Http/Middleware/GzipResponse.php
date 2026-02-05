<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HTML/JSON などテキスト系レスポンスを gzip 圧縮する（artisan serve 対策）。
 * - Cloud9(vfs/preview) 経由でも Content Download を減らすのが目的
 * - PDF/バイナリ/ストリーミング系は除外
 */
final class GzipResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // ストリーム/ファイル送信は除外（誤圧縮防止）
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }

        // 既にエンコード済みなら何もしない
        if ($response->headers->has('Content-Encoding')) {
            return $response;
        }

        // リクエスト側が gzip を受け入れていないなら何もしない
        $accept = (string) $request->headers->get('Accept-Encoding', '');
        if (stripos($accept, 'gzip') === false) {
            return $response;
        }

        // 失敗系/空/リダイレクトは対象外
        if (! $response->isOk()) {
            return $response;
        }

        // バイナリ系・PDF系・ダウンロード系は除外（誤圧縮防止）
        $path = '/' . ltrim((string) $request->path(), '/');
        if (str_starts_with($path, '/pdf/')) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        $isText =
            str_starts_with($contentType, 'text/') ||
            str_contains($contentType, 'application/json') ||
            str_contains($contentType, 'application/javascript') ||
            str_contains($contentType, 'application/xml') ||
            str_contains($contentType, 'application/xhtml+xml');

        if (! $isText) {
            return $response;
        }

        $content = $response->getContent();
        if (!is_string($content) || $content === '') {
            return $response;
        }

        // 小さすぎるものは圧縮しない（CPU節約）
        if (strlen($content) < 1024) {
            return $response;
        }

        // gzip圧縮（レベル6：バランス型）
        $gz = gzencode($content, 6);
        if ($gz === false) {
            return $response;
        }

        $response->setContent($gz);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Vary', 'Accept-Encoding', false);
        $response->headers->set('Content-Length', (string) strlen($gz));

        return $response;
    }
}