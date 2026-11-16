<?php

use App\Http\Middleware\RegistrarBitacora;
use App\Http\Middleware\VerificarPermiso;
use App\Http\Middleware\VerificarUsuarioActivo;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permiso' => VerificarPermiso::class,
            'usuario.activo' => VerificarUsuarioActivo::class,
        ]);

        // Bitácora de auditoría sobre toda la API.
        $middleware->api(append: [RegistrarBitacora::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // La capa 2 es una API: toda falla se responde en JSON y en español.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => true);

        $exceptions->render(fn (AuthenticationException $e) => response()->json(
            ['message' => 'Debe iniciar sesión para continuar.'], 401));

        $exceptions->render(fn (AccessDeniedHttpException $e) => response()->json(
            ['message' => 'No tiene autorización para realizar esta acción.'], 403));

        $exceptions->render(function (NotFoundHttpException $e) {
            $mensaje = $e->getPrevious() instanceof ModelNotFoundException
                ? 'El registro solicitado no existe o fue eliminado.'
                : 'El recurso solicitado no existe.';

            return response()->json(['message' => $mensaje], 404);
        });

        $exceptions->render(fn (MethodNotAllowedHttpException $e) => response()->json(
            ['message' => 'Operación no permitida sobre este recurso.'], 405));

        $exceptions->render(fn (ThrottleRequestsException $e) => response()->json(
            ['message' => 'Demasiados intentos. Espere un momento antes de volver a intentar.'], 429));

        $exceptions->render(fn (PostTooLargeException $e) => response()->json(
            ['message' => 'El archivo enviado supera el tamaño máximo permitido.'], 413));
    })->create();
