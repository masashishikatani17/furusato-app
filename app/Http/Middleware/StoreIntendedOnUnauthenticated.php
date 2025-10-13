<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class StoreIntendedOnUnauthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($this->shouldStoreIntendedUrl($request)) {
            $url = $this->determineIntendedUrl($request);
            $url = $this->ensureSameOrigin($url);

            $request->session()->put('url.intended', $url);
        }

        return $next($request);
    }

    protected function shouldStoreIntendedUrl(Request $request): bool
    {
        if (! $request->isMethod('post')) {
            return false;
        }

        if ($request->expectsJson()) {
            return false;
        }

        if ($request->routeIs('login')) {
            return false;
        }

        return $this->guard()->guest();
    }

    protected function guard(): Guard
    {
        $guard = config('auth.defaults.guard') ?: 'web';

        return Auth::guard($guard);
    }

    protected function determineIntendedUrl(Request $request): string
    {
        $redirect = (string) $request->input('redirect_to', '');
        $dataId = (int) ($request->input('data_id') ?? $request->query('data_id') ?? 0);
        $anchor = $this->sanitizeAnchor($request->input('origin_anchor') ?? $request->query('origin_anchor'));

        $url = match ($redirect) {
            '', 'input' => $this->buildInputUrl($dataId, $request->boolean('recalc_all')),
            'master' => $this->buildRouteUrl('furusato.master', $dataId),
            'syori' => $this->buildRouteUrl('furusato.syori', $dataId),
            'jigyo' => $this->buildRouteUrl('furusato.details.jigyo', $dataId),
            'fudosan' => $this->buildRouteUrl('furusato.details.fudosan', $dataId),
            'kihukin_details' => $this->buildRouteUrl('furusato.details.kihukin', $dataId),
            'joto_ichiji' => $this->buildRouteUrl('furusato.details.joto_ichiji', $dataId),
            'kojo_seimei_jishin' => $this->buildRouteUrl('furusato.details.kojo_seimei_jishin', $dataId),
            'kojo_jinteki' => $this->buildRouteUrl('furusato.details.kojo_jinteki', $dataId),
            'kojo_iryo' => $this->buildRouteUrl('furusato.details.kojo_iryo', $dataId),
            default => URL::previous() ?: route('data.index'),
        };

        if ($anchor !== '') {
            $url .= '#'.$anchor;
        }

        return $url;
    }

    protected function buildInputUrl(int $dataId, bool $recalcAll): string
    {
        $params = [];

        if ($dataId > 0) {
            $params['data_id'] = $dataId;
        }

        $params['tab'] = $recalcAll ? 'result_details' : 'input';

        return route('furusato.input', $params);
    }

    protected function buildRouteUrl(string $routeName, int $dataId): string
    {
        $params = $dataId > 0 ? ['data_id' => $dataId] : [];

        return route($routeName, $params);
    }

    protected function sanitizeAnchor(?string $anchor): string
    {
        if ($anchor === null) {
            return '';
        }

        return (string) preg_replace('/[^A-Za-z0-9_-]/', '', $anchor);
    }

    protected function ensureSameOrigin(string $url): string
    {
        $default = route('data.index');

        if ($url === '') {
            return $default;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $currentUrl = rtrim(url('/'), '/');
        $allowed = array_filter([$appUrl, $currentUrl]);

        foreach ($allowed as $prefix) {
            if ($prefix !== '' && Str::startsWith($url, $prefix)) {
                return $url;
            }
        }

        return $default;
    }
}