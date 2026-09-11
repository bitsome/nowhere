<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // 비로그인 API 요청은 리다이렉트하지 않고 401 JSON으로 응답한다.
        // (기본값은 route('login')을 즉시 평가하는데 이 앱에는 login 라우트가 없어 500이 난다)
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : config('app.frontend_url'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            return response()->view('errors.404', status: 404);
        });
    })->create();
