<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// El backend se sirve con Apache (mod_php, hilos compartiendo un mismo
// proceso en Windows), no con procesos separados por peticion. Por
// defecto Laravel escribe cada variable de entorno con putenv(), una
// funcion que NO es segura entre hilos: bajo carga concurrente, un hilo
// leyendo env('DB_CONNECTION') puede toparse con una escritura a medias
// de OTRO hilo y fallar la lectura, cayendo al valor por defecto de
// config/database.php ("sqlite") en vez de leer el "mysql" real del
// .env. Esto causaba errores 500 intermitentes ("no such table: ...")
// bajo peticiones concurrentes (el panel/POS disparan varias a la vez).
// Se desactiva antes de crear la app, lo mas temprano posible, para que
// ninguna peticion llegue a usar putenv().
\Illuminate\Support\Env::disablePutenv();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'erp.auth' => \App\Http\Middleware\AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
