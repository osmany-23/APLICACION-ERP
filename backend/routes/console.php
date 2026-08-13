<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mora automatica por atraso (modulo Clientes). Requiere que el cron real
// del servidor llame a `php artisan schedule:run` cada minuto (estandar de
// Laravel); en un entorno de desarrollo sin cron configurado, correr
// `php artisan sales:apply-late-fees` a mano cuando se quiera probar.
Schedule::command('sales:apply-late-fees')->daily();
